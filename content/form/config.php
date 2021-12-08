<?php

return array(
    'register' => array(
        'filter' => array(
            'email' => array('StripTags'),
        ),
        'validator' => array(
            'email' => array('Email'),
            'password' => array('Password'),
            'password_confirm' => array('Confirm'),
        ),
        'validator_options' => array(
            'password_confirm' => array('matchTo' => 'password'),
        ),
    ),
    'login' => array(
        'filter' => array(
            'email' => array('StripTags'),
        ),
        'validator' => array()),
    'contact' => array(
        'filter' => array(
            'email' => array('StripTags'),
        ),
        'validator' => array(
            'email' => array('Required', 'Email'),
            'name' => array('Required'),
            'content' => array('Required'),
            'accept' => array('Required'),
        ),
    ),
);
