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
    public $role;
    public $discussionId;

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
        if( isset($fields['role']) ) {
            $this->role = $fields['role'];
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
        try{
            $response = Request::post( $this->apiURL . 'login' )
                ->body(array( 'user' => $this->username, 'password' => $this->password ))
                ->send();
        }catch (\Exception $e){
            return false;
        }

        if($this->checkResponseValidity($response,2)) {
            $this->userId = $response->body->data->userId;
            $this->authToken = $response->body->data->authToken;
            return true;
        } else {
            echo( $response->body->message . "\n" );
            return false;
        }
    }


    public function logout() {
        $this->initRequestToken();
        try {
            $response = Request::get($this->apiURL . 'logout')
                ->send();
        }catch (\Exception $e){
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
     * @param $role
     * function would used for creating new user
     */
    public function createUser($username,$password,$email,$nickname,$role) {
        try {
            $response = Request::post($this->apiURL . 'users.create')
                ->body(array(
                    'name' => $nickname,
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'roles' => array($role),
                ))
                ->send();
        }catch (\Exception $e){
            return false;
        }

//        print_r( $response);
//        exit;


        if($this->checkResponseValidity($response)){
            return true;
        }else if( strpos($response->body->error,"is already in use")!=false) {
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
     * @param $username
     * @param $password
     * @param $email
     * @param $nickname
     * @return bool  true if the user registered successfully or exists
     * function would used for registering new user
     */
    public function registerUser($username,$password,$email,$nickname) {
        try {
            $response = Request::post($this->apiURL . 'users.register')
                ->body(array(
                    'name' => $nickname,
                    'email' => $email,
                    'username' => $username,
                    'pass' => $password,
                ))
                ->send();
        }catch (\Exception $e){
            return false;
        }

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


    public function loginCreateIfNotExist() {
        $admin = new RocketChat($this->apiURL , $this->adminUser, $this->adminPassword);
        if( $admin->login() ) {
            $admin->initRequestToken();
            if($admin->createUser($this->username,$this->password,$this->email,$this->nickname,$this->role)){
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

    public function addDiscussion($username){
        $flag=$this->checkIfRoomExist($username);
        if(!$flag) {
            $response = Request::post($this->apiURL . 'rooms.createDiscussion')
                ->body(array(
                    'prid' => 'GENERAL',
                    't_name' => $username,
                    'users' => array($username),
                ))
                ->send();
            if ($this->checkResponseValidity($response)) {
                $flag=$response->body->discussion->name;
            } else {
                //echo( $response->body->error . "\n" );
                echo '<h1>Error: ';
                print_r($response);
                exit;
                echo '</h1>';
                return false;
            }
        }
        return $flag;
    }


    public function createDiscussion($username){
        $admin = new RocketChat($this->apiURL , $this->adminUser, $this->adminPassword);
        if( $admin->login() ) {
            $admin->initRequestToken();
            $this->discussionId=$admin->addDiscussion($username);
        }
        return false;
    }


    public function checkIfRoomExist($room){
        try {
            $response = Request::get($this->apiURL . 'rooms.getDiscussions?roomId=GENERAL')
                ->send();
        }catch (\Exception $e){
            return false;
        }

        if($this->checkResponseValidity($response) ) {
            foreach ($response->body->discussions as $value){
                if(trim($value->fname)===$room){
                    return $value->name;
                }
            }
        }else{
            echo '<h1>Error: ';
            print_r( $response);
            echo '</h1>';
            //exit;
        }
        return false;
    }

    public function getRoomList(){
        $admin = new RocketChat($this->apiURL , $this->adminUser, $this->adminPassword);
        if( $admin->login() ) {
            $admin->initRequestToken();
            $admin->roomList();
        }
        return false;
    }


    public function getMessages($roomId,$start,$end){
//        $response = Request::get($this->apiURL . 'groups.history?roomId='.$roomId)
//            ->send();
        try {
            $response = Request::get($this->apiURL . 'groups.history?roomId=' . $roomId . "&latest=" . $end . "&oldest=" . $start)
                ->send();
        }catch (\Exception $e){
            return false;
        }

        if($this->checkResponseValidity($response)) {
            return $response;
        } else {
            //echo( $response->body->message . "\n" );
            return false;
        }


    }

    public function getRoomMessage($room,$start,$end){
        $admin = new RocketChat($this->apiURL , $this->adminUser, $this->adminPassword);

        if( $admin->login() ) {
            $admin->initRequestToken();
            if (($roomId=$admin->checkIfRoomExist($room))!=false){
                return $admin->getMessages($roomId,$start,$end);
            }

        }
        return $room;//.$roomId." ";
    }
}