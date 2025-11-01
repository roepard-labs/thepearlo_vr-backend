<?php
class UserRegister {
    public $first_name;
    public $last_name;
    public $phone;
    public $username;
    public $email;
    public $password;

    public function __construct($data) {
        $this->first_name = $data['first_name'];
        $this->last_name = $data['last_name'];
        $this->phone = $data['phone'];
        $this->username = $data['username'];
        $this->email = $data['email'];
        $this->password = $data['password'];
    }
}
