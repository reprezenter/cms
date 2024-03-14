<?php

class Form {

    public function __construct($api) {
        $this->api = $api;
    }

    public function editFormSubmitted() {
        $formData = $this->api->getFormData('module_blog_edit');
        $id = $this->api->getParams()['get']['id'];

        unset($formData['module_blog_edit']);
        unset($formData['image']);
        $formData['category_id'] = 0;
        $formData['slug'] = $this->api::getSlug($formData['title']);

        if ($id) {
            $formData['id'] = $id;
            $statement = $this->api->getConnection()->prepare("UPDATE blog SET category_id=:category_id, title=:title, short_content=:short_content, content=:content, slug=:slug, order_id=:order_id WHERE id=:id;");
            $ok = $statement->execute($formData);
        } else {
            $statement1 = $this->api->getConnection()->prepare("SELECT * FROM `blog` WHERE slug = :slug");
            $statement1->execute(array(':slug' => $formData['slug']));
            $entry = $statement1->fetch();
            if ($entry) {
                $this->api->addSessionMsg($this->api->t('Wpis o takim tytule już isntnieje'), 'error');
                header('Location: /admin/blog/index.html');
                die();
            }
            $statement = $this->api->getConnection()->prepare("INSERT INTO blog(category_id, title, short_content, content, slug, order_id)
                                                     VALUES(:category_id, :title, :short_content, :content, :slug, :order_id)");
            $ok = $statement->execute($formData);
            $insertId = $this->api->getConnection()->lastInsertId();
            if ($ok) {
                $id = $this->api->getModule('Blog')->getTmpId();
                $entityType = $this->api->getModule('Blog')->getEntityId();
                $oldname = PUBLIC_PATH . DIRECTORY_SEPARATOR . Image::ENTITY_FOLDER . $entityType . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
                $newname = PUBLIC_PATH . DIRECTORY_SEPARATOR . Image::ENTITY_FOLDER . $entityType . DIRECTORY_SEPARATOR . $insertId . DIRECTORY_SEPARATOR;
                $ok = rename($oldname, $newname);
            }
        }

        $this->api->addSessionMsg($this->api->t('Zapisano'), 'success');
        header('Location: /admin/blog/index.html');
        die();
    }

}
