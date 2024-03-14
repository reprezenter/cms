<?php

class Render {

    public function __construct($path) {
        require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'Api.php';
        require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'config.php';
        if ($rootFolder) {
            $path = str_replace($rootFolder, '', $path);
        }
        $this->path = '/' . ltrim(rtrim($path, '/'), '/');
        $this->api = new Api();
    }

    public function getApi() {
        return $this->api;
    }

    public function content() {
        $modules = $this->api->getModules();
        $moduleRutes = [];
        require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'AbstractModule.php';
        foreach ($modules as $module) {
            require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . $module;
            $className = str_replace('.php', '', $module);
            $instance = new $className($this->api);
            $moduleRutes += $instance->getRouteMatch();
            $this->api->addModule($className, $instance);
        }
        $isAdmin = is_numeric(strpos($_SERVER['REQUEST_URI'], '/admin/'));
        $url = str_replace('/admin/', '', $_SERVER['REQUEST_URI']);
        $uri = ltrim(rtrim($url, '/'), '/');
        if (strpos($uri, '/')) {
            $_moduleRoute = (explode('/', $uri))[0];
        } else {
            $_moduleRoute = $uri;
        }
        if (strpos($_moduleRoute, '?')) {
            $__moduleRoute = explode('?', $_moduleRoute)[0];
        } else {
            $__moduleRoute = $_moduleRoute;
        }
        $moduleRoute = str_replace(array('.html'), '', $__moduleRoute);
        if (is_numeric(array_search($moduleRoute, $moduleRutes)) && $this->api->getModule($moduleRoute)->getAllowRoute()) {
            if ($isAdmin) {
                $filename = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . $moduleRoute . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . $this->getTemplateName([$moduleRoute]);
            } else {
                $filename = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . $moduleRoute . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . $moduleRoute;
            }
        } else {
            $filename = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . $this->getTemplateName();
        }
        if (trim($_SERVER['REQUEST_URI'], '/') == 'admin') {
            $filename = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . '_admin';
        }
        if ($isAdmin) {
            if (!$this->api->getAuth()->hasIdentity()) {
                header('Location: /login.html');
            } else {
                if ($this->api->getAuth()->getIdentity()['role_id'] != $this->api->getAuth()::ROLE_ID_ADMIN) {
                    http_response_code(403);
                    include('403.php');
                    die();
                }
            }
        }
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
        if (strpos($filename, 'ajax')) {
            include PUBLIC_PATH . '/content/layout/empty.phtml';
        } elseif ($isAdmin) {
            include PUBLIC_PATH . '/content/layout/admin.phtml';
        } else {
            include PUBLIC_PATH . '/content/layout/default.phtml';
        }
        ob_end_flush();
    }

    private function getTemplateName($skip = []) {
        $skip[] = 'admin';
        $path = $this->path;
        foreach ($skip as $part) {
            $path = str_replace('/' . $part, '', $path);
        }
        $path = str_replace('.html', '', $path);
        return str_replace(array('/'), array('_'), $path);
    }

}
