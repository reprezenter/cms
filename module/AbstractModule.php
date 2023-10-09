<?php

class AbstractModule {

    public function __construct($api) {
        $this->api = $api;
    }

    public function getRouteMatch() {
        return [];
    }

}
