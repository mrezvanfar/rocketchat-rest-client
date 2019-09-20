<?php
namespace RocketChat;

use Httpful\Request;

class RocketChat{
    private $apiURL;
    private $adminUser="admin_username";
    private $adminPassword="admin_password";
    public $username;
    private $password;
    public $userId;
    public $authToken;
    public $nickname;
    public $email;

    public function __construct($apiURL,$username, $password, $fields = array()){
        Request::ini(Request::init()->sendsJson()->expectsJson());
        $this->adminUser= env('CHAT_ADMIN_USER');
        $this->adminPassword= env('CHAT_ADMIN_PASSWORD');
        $this->apiURL=$apiURL;
        $this->username = $username;
        $this->password = $password;
        if( isset($fields['nickname']) ) {
            $this->nickname = $fields['nickname'];
        }
        if( isset($fields['email']) ) {
            $this->email = $fields['email'];
        }
    }


    private function checkResponseValidity($response,$type=1)
    {
        if ($type==2){
            if($response->code == 200 && isset($response->body->status) && $response->body->status == 'success')
                return true;
        }
        else{
            if($response->code == 200 && isset($response->body->success) && $response->body->success == true )
                return true;
        }
        return false;
    }

    /**
     * @return bool
     * login the user and initialize appropriate token and id
     */
    public function login() {
        $response = Request::post( $this->apiURL . 'login' )
            ->body(array( 'user' => $this->username, 'password' => $this->password ))
            ->send();

        if($this->checkResponseValidity($response,2)) {
            $this->userId = $response->body->data->userId;
            $this->authToken = $response->body->data->authToken;
            return true;
        } else {
            echo( $response->body->message . "\n" );
            return false;
        }
    }

    /**
     *  Initialize http request header to use the user id and token for further access
     */
    public function initRequestToken(){
        Request::ini(Request::init()
            ->addHeader('X-Auth-Token', $this->authToken)
            ->addHeader('X-User-Id', $this->userId));
    }


    /**
     * @param $username
     * @param $password
     * @param $email
     * @param $nickname
     * @return bool  true if the user registered successfully or exists
     * function would used for registering new user
     */
    public function registerUser($username,$password,$email,$nickname) {
        $response = Request::post( $this->apiURL . 'users.register' )
            ->body(array(
                'name' => $nickname,
                'email' => $email,
                'username' => $username,
                'pass' => $password,
            ))
            ->send();

        if($this->checkResponseValidity($response) ) {
            return true;
        } else if( strcmp($response->body->error,"Username is already in use")==0) {
            return true;
        }else{
            //echo( $response->body->error . "\n" );
            echo '<h1>Error: ';
            print_r( $response);
            exit;
            echo '</h1>';
            return false;
        }
    }


    /**
     * @return bool
     * try to login user in rocketchat if the exists, otherwise will register user in rocketchat
     */
    public function loginRegisterIfNotExist() {
        $admin = new RocketChat($this->apiURL , $this->adminUser, $this->adminPassword);
        $admin->initRequestToken();
        if( $admin->login() ) {
            if($admin->registerUser($this->username,$this->password,$this->email,$this->nickname)){
                return true;
            }
        }
        return false;
    }


    /**
     * @return int
     * Get the number of unread message in all rooms and channels
     */
    public function getUnreadMessagesCount(){
        $this->initRequestToken();
        $response = Request::get( $this->apiURL . 'subscriptions.get' )->send();
        $sum=0;
        foreach ($response->body->update as $value)
            $sum+=$value->unread;
        return $sum;
    }

}