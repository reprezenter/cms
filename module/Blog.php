<?php

require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'Image.php';

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

    public function getEntityId() {
        return Image::ENTITY_ID_BLOG;
    }

    public function getTmpId() {
        return Image::TMP_SESSiON_PREFIX . $this->api->getAuth()->getIdentity()['id'];
    }

    public function uploader() {

        $id = $this->api->getParams()['get']['id']; //resource id
        $entityType = $this->api->getParams()['get']['e_id']; //entity id
        if ((!$id || !$entityType) && !$this->api->getParams()['get']['del']) {
            return json_encode(array(
                'error' => 'Niepoprawny ardes url'
            ));
        }
        $dstPath = PUBLIC_PATH . DIRECTORY_SEPARATOR . Image::ENTITY_FOLDER . $entityType . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;

        if ($_FILES && $_FILES["images"]["error"]) {
            if (!is_dir($dstPath)) {

                Image::mkdir_r($dstPath);
            }

            Image::clearFolderContent($dstPath);

            foreach ($_FILES["images"]["error"] as $key => $error) {
                if ($error == UPLOAD_ERR_OK) {
                    $name = Image::safeFileName($_FILES["images"]["name"][$key]);
                    move_uploaded_file($_FILES["images"]["tmp_name"][$key], $dstPath . $name);
                }
            }
        }
        if ($this->api->getParams()['get']['del']) {
            unlink($dstPath . $this->api->getParams()['get']['file']);
            array_map('unlink', glob($dstPath . 'images' . DIRECTORY_SEPARATOR . '*'));
        }
        $images = array();
        if (is_dir($dstPath)) {
            $files = scandir($dstPath);
            foreach ($files as $file) {
                if (!is_dir($dstPath . DIRECTORY_SEPARATOR . $file)) {
                    $relativePath = Image::ENTITY_FOLDER . $entityType . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . $file;
                    $image = new Image($relativePath);
                    $image->setOptions(array('type' => 'resize', 'width' => Image::ENTITY_1_IMAGE_WIDTH));
                    $images[] = array(
                        'deleteUrl' => '/admin/blog/ajax/uploader.html?del=1&file='. $file .'&id=' . $this->api->getModule('Blog')->getTmpId() . '&e_id=' . $this->api->getModule('Blog')->getEntityId(),
                        'id' => $id,
                        'e_id' => $entityType,
                        'file' => $file,
                        'src' => $image->getDestFileRelativePath()
                    );
                }
            }
        }
        return json_encode(array(
            $images
        ));
    }   

}
