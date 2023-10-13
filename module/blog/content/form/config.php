<?php

return array(
    'module_blog_edit' => array(
        'filter' => array(
            'title' => array('StripTags'),
            'short_content' => array('StripTags'),
        ),
        'validator' => array(
            'title' => array('Required'),
            'short_content' => array('Required'),
            'content' => array('Required'),
            'order_id' => array('Required'),
        ),
    ),
);
