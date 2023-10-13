<?php

class Form {

    public function __construct($api) {
        $this->api = $api;
    }

    public function editFormSubmitted() {
        $formData = $this->api->getFormData('module_blog_edit');
        unset($formData['module_blog_edit']);
        unset($formData['image']);
        $formData['category_id'] = 0;
        $formData['slug'] = $this->api::getSlug($formData['title']);
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
            $this->api->addSessionMsg($this->api->t('Zapisano'), 'success');
            header('Location: /admin/blog/index.html');
            die();
        }
    }

}
