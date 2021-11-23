<?php

class Form {

    public function __construct($api) {
        $this->api = $api;
    }

    public function registerFormSubmitted() {
        $formData = $this->api->getFormData('register');
    }

    public function loginFormSubmitted() {
        $formData = $this->api->getFormData('login');
    }

}
