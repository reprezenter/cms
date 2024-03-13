<?php

class Email {

    const ERROR_MESSAGE = 'Niepoprawny email.';

    public function validate($value, $name, $formName, $api) {
        $exploded = explode('@', $value);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || !checkdnsrr($exploded[1], 'MX')) {
            return self::ERROR_MESSAGE;
        }
        return true;
    }

}
