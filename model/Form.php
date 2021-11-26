<?php

class Form {

    public function __construct($api) {
        $this->api = $api;
    }

    public function registerFormSubmitted() {
        $formData = $this->api->getFormData('register');
        unset($formData['password_confirm']);
        unset($formData['register']);
        $formData['password'] = md5($formData['password']);
        $formData['role_id'] = Auth::ROLE_ID_USER;
        $statement = $this->api->getConnection()->prepare("INSERT INTO user(identity, credential, role_id)
                                                     VALUES(:email, :password, :role_id)");
        $ok = $statement->execute($formData);
        if ($ok) {
            $this->api->addSessionMsg($this->api->t('Rejestracja przebiegła pomyślnie, możesz się zalogować'), 'success');
            header('Location: /' . $this->api->getConfig()['rootFolder'] . '/login.html');
            die();
        }
    }

    public function loginFormSubmitted() {
        $formData = $this->api->getFormData('login');
        $ok = $this->api->getAuth()->authenticate($formData);
        if (is_array($ok)) {
            $this->api->addSessionMsg($this->api->t('Zalogowano pomyślnie'), 'success');
            header('Location: /' . $this->api->getConfig()['rootFolder'] . '/');
            die();
        } else {
            $this->api->addSessionMsg($this->api->t('Niepoprawny login lub hasło'), 'error');
            header('Location: /' . $this->api->getConfig()['rootFolder'] . '/login.html');
            die();
        }
    }

}
