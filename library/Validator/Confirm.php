<?php

class Confirm {

    const ERROR_MESSAGE = 'Podany ciąg znaków nie jest taki sam.';

    public function validate($value, $name, $formName, $api) {
        $params = isset($api->getParams()['post']) ? $api->getParams()['post'] : $api->getParams()['get'];

        if (!isset($api->getFormConfig()[$formName]['validator_options'][$name]['matchTo'])) {
            throw new ErrorException('Confirm validator requires matchTo option in validator_options config');
        }
        if ($value != $params[$api->getFormConfig()[$formName]['validator_options'][$name]['matchTo']]) {
            return self::ERROR_MESSAGE;
        }
        return true;
    }

}
