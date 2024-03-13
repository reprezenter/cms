<?php

class Required {

    const ERROR_MESSAGE = 'Pole wymagane.';

    public function validate($value, $name, $formName, $api) {
        if (strlen($value) === 0) {
            return self::ERROR_MESSAGE;
        }
        return true;
    }

}
