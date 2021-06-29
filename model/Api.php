<?php

class Api {

    public function __construct() {
        $filename = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'config.php';
        require $filename;
        $this->config = [];
        $tokens = token_get_all(file_get_contents($filename));
        foreach ($tokens as $token) {
            if ($token[0] == T_VARIABLE) {
                $varName = str_replace('$', '', $token[1]);
                $this->config[$varName] = ${$varName};
            }
        }
    }

    public function setParams($params) {
        $this->params = $params;
    }

    public function getParams() {
        return $this->params;
    }

    public function setPath($path) {
        $this->path = $path;
    }

    public function getTemplate() {
        return $this->template;
    }

    public function setTemplate($template) {
        $this->template = $template;
    }

    public function getPath() {
        return $this->path;
    }

    public function getConfig() {
        return $this->config;
    }

    public function setTemplateVars($templateVars) {
        $this->templateVars = $templateVars;
    }

    public function getTemplateVars($name) {
        if (!isset($this->templateVars[$name])) {
            $this->templateVars['title'] = $this->config['defaultPageTitle'];
            $this->templateVars['description'] = $this->config['defaultPageDescription'];
        }
        return $this->templateVars[$name];
    }

    public function getLangs() {
        return array(
            '' => array('name' => 'polski', 'flag' => 'pl.png', 'short' => 'pl'),
            'ua' => array('name' => 'Український', 'flag' => 'ua.png', 'short' => 'ua'),
            'ru' => array('name' => 'русский', 'flag' => 'ru.png', 'short' => 'ru'),
            'en' => array('name' => 'english', 'flag' => 'gb.png', 'short' => 'en'),
        );
    }

    public function langPrefix($withSlash = false) {
        $pathExploded = explode('/', ltrim($this->path, '/'));
        $return = $pathExploded[0];
        if (!array_key_exists($return, $this->getLangs())) {
            $return = '';
        }
        if ($withSlash && strlen($return)) {
            $return = '/' . $return;
        }
        return $return;
    }

    public function langShort() {
        $pathExploded = explode('/', ltrim($this->path, '/'));
        if (!array_key_exists($pathExploded[0], $this->getLangs())) {
            $return = '';
        }

        $return = $this->getLangs()[$pathExploded[0]]['short'];

        return $return;
    }

    public function t($string) {
        if ($this->langFile) {
            if (isset($this->langFile[$string])) {
                return $this->langFile[$string];
            }
            return $string;
        }

        $langFile = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'langs' . DIRECTORY_SEPARATOR . $this->langPrefix() . '.php';

        if (file_exists($langFile)) {
            require_once $langFile;
            $this->langFile = $array;
            return $this->t($string);
        }
        return $string;
    }

}
