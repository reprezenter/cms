<?php

class Password {

    const UPPERCASE = '/[A-Z]/';
    const LOWERCASE = '/[a-z]/';
    const SPECIALS = '/[!@#$%^&*()\-_=+{};:,<.>0-9]/';
    const LENGTH = 8;
    const ERROR_MESSAGE = 'Hasło zbyt proste. Mimimalna długość to 8 naków. Wymagane przynajmniej 2 wielkie litery, znak specjalny.';

    public function validate($value, $name, $formName, $api) {
        //uppercace
        $matches = array();
        if (preg_match_all(self::UPPERCASE, $value, $matches) < 2) {
            return self::ERROR_MESSAGE;
        }

        //special chars and numbers
        $matches = array();
        if (preg_match_all(self::SPECIALS, $value, $matches) < 1) {
            return self::ERROR_MESSAGE;
        }

        //length        
        if (strlen($value) < self::LENGTH) {
            return self::ERROR_MESSAGE;
        }

        return true;
    }

}
