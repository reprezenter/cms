<?php

class Auth {

    const ROLE_ID_USER = 1;
    const ROLE_ID_ADMIN = 2;

    private static $instance;

    public static function getInstance($api) {
        if (is_null(static::$instance)) {
            static::$instance = new Auth($api);
        }
        return static::$instance;
    }

    public function __construct($api) {
        $this->api = $api;
        if (count($this->urlAllowed()) === 0)
            return true;
        $urlAllowed = array_map(function($v) {
            return str_replace('.html', '', $v);
        }, $this->urlAllowed());
        $url = ltrim(str_replace(array($this->api->getConfig()['rootFolder'], '.html'), '', $_SERVER['REQUEST_URI']), '/');

        if (!$this->hasIdentity() && !is_numeric(array_search($url, $urlAllowed))) {
            $this->api->addSessionMsg($this->api->t('Log in'), 'success');
            header('Location: /' . $this->api->getConfig()['rootFolder'] . '/login.html');
            die();
        }
        if ($this->hasIdentity() && is_numeric(array_search($url, $urlAllowed))) {
            $this->api->addSessionMsg($this->api->t('Already logged in'), 'success');
            header('Location: /' . $this->api->getConfig()['rootFolder'] . '/');
            die();
        }
    }

    protected function __clone() {
        
    }

    public function __wakeup() {
        throw new \Exception('Cannot unserialize a singleton.');
    }

    public function urlAllowed() {
        if (isset($this->api->getConfig()['authAllowedUrl']) && is_array($this->api->getConfig()['authAllowedUrl'])) {
            return $this->api->getConfig()['authAllowedUrl'];
        }
        return array();
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
        header('Location: /' . $this->api->getConfig()['rootFolder']);
        die();
    }

}
