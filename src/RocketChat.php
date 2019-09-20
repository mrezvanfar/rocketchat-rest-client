<?php
namespace RocketChat;

use Httpful\Request;

class RocketChat extends ChatConnection{
    private $apiURL;
    private $adminUser="a";
    private $adminPassword="1";
    public $username;
    private $password;
    public $userId;
    public $authToken;
    public $nickname;
    public $email;

    public function __construct($apiURL,$username, $password, $fields = array()){
        Request::ini(Request::init()->sendsJson()->expectsJson());
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


    private function checkResponseValidity($response)
    {
        if($response->code == 200 && isset($response->body->status) && $response->body->status == 'success')
            return true;
        return false;
    }

    public function login() {
        $response = Request::post( $this->apiURL . 'login' )
            ->body(array( 'user' => $this->username, 'password' => $this->password ))
            ->send();

        if(checkResponseValidity($response)) {
            $this->userId = $response->body->data->userId;
            $this->authToken = $response->body->data->authToken;
            return true;
        } else {
            echo( $response->body->message . "\n" );
            return false;
        }
    }


    public function initRequestToken(){
        Request::ini(Request::init()
                    ->addHeader('X-Auth-Token', $this->authToken)
                    ->addHeader('X-User-Id', $this->userId));
    }

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
            print_r( $response->body->error);
            echo '</h1>';
            return false;
        }
    }


    public function loginRegisterIfNotExist() {
        $admin = new RocketChat($this->api, $this->adminUser, $this->adminPassword);
        $admin->initRequestToken();
        if( $admin->login() ) {
            if($admin->registerUser($this->username,$this->password,$this->email,$this->nickname)){
                return true;
            }
        }
        return false;
    }





}