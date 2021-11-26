<?php

class Auth {

    const ROLE_ID_USER = 1;

    private static $instance;

    public static function getInstance($api) {
        if (is_null(static::$instance)) {
            static::$instance = new Auth($api);
        }
        return static::$instance;
    }

    public function __construct($api) {
        $this->api = $api;
    }

    protected function __clone() {
        
    }

    public function __wakeup() {
        throw new \Exception('Cannot unserialize a singleton.');
    }

    public function authenticate(array $data) {
        $connection = $this->api->getConnection();
        $statement = $connection->prepare("SELECT * FROM `user` WHERE identity = :identity AND credential = MD5(:credential)");
        $statement->execute(array(':identity' => $data['email'],
            ':credential' => $data['password']));
        $user = $statement->fetch();
        if (is_array($user)) {
            unset($user['credential']);
            $_SESSION['user'] = $user;
        }
        return $user;
    }

    public function hasIdentity() {
        return is_array($_SESSION['user']);
    }

    public function getIdentity() {
        return $_SESSION['user'];
    }

    public function logOut() {
        $_SESSION['user'] = null;
        header('Location: /' . $this->api->getConfig()['rootFolder'] . '/');
        die();
    }

}
