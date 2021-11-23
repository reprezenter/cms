<?php

class StripTags {

    public function filter($value) {
        return strip_tags($value);
    }

}
