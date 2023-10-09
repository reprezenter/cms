<?php

class Blog extends AbstractModule {

    public function getRouteMatch() {
        return [
            'blog'
        ];
    }

    public function getCategories() {
        $connection = $this->api->getConnection();
        $statement = $connection->prepare("SELECT * FROM `blog_category` ORDER BY `order_id` DESC");
        $statement->execute(array());
        $categories = $statement->fetchAll();
        return $categories;
    }

    public function getEntries() {
        $connection = $this->api->getConnection();
        $statement = $connection->prepare("SELECT * FROM `blog` ORDER BY `order_id` DESC");
        $statement->execute(array());
        $categories = $statement->fetchAll();
        return $categories;
    }

    public function getEntryByUrl() {
        $urlExploded = explode('/blog/', rtrim($_SERVER['REQUEST_URI'], '/'));
        if (count($urlExploded) > 1) {
            if (strpos($urlExploded[1], '.')) {
                $slug = (explode('.', $urlExploded[1]))[0];
            } else {
                $slug = rtrim($urlExploded[1], '/');
            }
        }
        $connection = $this->api->getConnection();
        $statement = $connection->prepare("SELECT * FROM `blog` WHERE slug = :slug");
        $statement->execute(array(':slug' => $slug));
        $entry = $statement->fetch();
        return $entry;
    }

}
