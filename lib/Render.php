<?php

class Render {

    public function __construct($path) {
        require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'Api.php';
        require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'config.php';
        if ($rootFolder) {
            $path = str_replace($rootFolder, '', $path);
        }
        $this->path = '/'. ltrim(rtrim($path, '/'), '/');
        $this->api = new \Api();
    }

    public function getApi() {
        return $this->api;
    }

    public function content() {

        $filename = PUBLIC_PATH . '/content/' . $this->getTemplateName();
        $params = array();
        if (strpos($filename, '?')) {
            $exploded = explode('?', $filename);
            $filename = $exploded[0];
            foreach ($_GET as $key => $value) {
                $params[$key] = $value;
            }
        }
        $post = filter_input_array(INPUT_POST);
        $get = filter_input_array(INPUT_GET);
        $filename .= '.phtml';
        if (!file_exists($filename)) {
            if ($_GET['create'] === '1') {
                $path = $filename;
                $file = fopen($path, "w");
                echo fwrite($file, '<h1></h1>' . PHP_EOL . '<p></p>');
                fclose($file);
            }
            die($filename);
            http_response_code(404);
            include('404.php');
            die();
            //throw new \Exception('View template not found: ' . $filename);
        }
        $api = $this->api;
        $api->setParams(array('get' => $get, 'post' => $post));
        $fileContent = file_get_contents($filename);
        $api->setTemplate($filename);
        ob_start();
        require_once $filename;
        $tokens = token_get_all(file_get_contents($filename));
        $fileVars = array();
        foreach ($tokens as $token) {
            if ($token[0] == T_VARIABLE) {
                $varName = str_replace('$', '', $token[1]);
                $fileVars[$varName] = ${$varName};
            }
        }
        $api->setTemplateVars($fileVars);
        ob_end_clean();
        ob_start();
        if (!strpos($filename, 'ajax')) {
            include PUBLIC_PATH . '/content/layout/default.phtml';
        }
        ob_end_flush();
    }

    private function getTemplateName() {
        return str_replace(array('/', '.html'), array('_', ''), $this->path);
    }

}
