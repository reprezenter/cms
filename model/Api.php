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

    public function form($name) {
        $this->formError = array($name => array());
        if ($this->formSubmitted($name) && $this->formValidate($name)) {

            require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'Form.php';
            $form = new Form($this);
            if (method_exists('Form', $name . 'FormSubmitted')) {
                $form->{$name . 'FormSubmitted'}();
            } else {
                throw new ErrorException('Function ' . $name . 'FormSubmitted not implemented');
            }
        } else {
            $api = $this;
            $filename = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . $name . '.phtml';
            include ($filename);
        }
    }

    public function formSubmitted($name) {
        if (isset($this->params['post'][$name])) {
            return 'post';
        } if (isset($this->params['get'][$name])) {
            return 'get';
        }
        return false;
    }

    public function getFormConfig() {
        return include PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'config.php';
    }

    public function getFormData($name, $filedName = false) {
        if ($this->formSubmitted($name) && isset($this->params[$this->formSubmitted($name)])) {
            if ($filedName) {
                if (isset($this->params[$this->formSubmitted($name)][$filedName])) {
                    return $this->params[$this->formSubmitted($name)][$filedName];
                }
                return '';
            }
            return $this->params[$this->formSubmitted($name)];
        }
        if ($filedName) {
            return '';
        }
        return array();
    }

    public function formValidate($name) {
        $this->filterForm($name);
        $isValid = true;
        $config = $this->getFormConfig();
        if (isset($config[$name]['validator']) && is_array($config[$name]['validator'])) {
            $validators = $config[$name]['validator'];
            $data = $this->getFormData($name);
            foreach ($data as $key => $value) {
                if (array_key_exists($key, $validators)) {
                    foreach ($validators[$key] as $validatorName) {
                        $val = $this->getFormData($name)[$key];
                        $fildValid = $this->validateValue($val, $key, $name, $validatorName);

                        if ($fildValid !== true) {
                            $isValid = false;
                            $this->formError[$name][$key][] = $fildValid;
                        }
                    }
                }
            }
        }
        return $isValid;
    }

    public function getFormErrors($formName, $filedName = false) {
        if ($filedName) {
            if (isset($this->formError[$formName][$filedName])) {
                return $this->formError[$formName][$filedName];
            }
            return false;
        }

        return $this->formError[$formName];
    }

    public function filterForm($name) {
        $config = include PUBLIC_PATH . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'form' . DIRECTORY_SEPARATOR . 'config.php';
        if (isset($config[$name]['filter']) && is_array($config[$name]['filter'])) {
            $filters = $config[$name]['filter'];
            $data = $this->getFormData($name);
            foreach ($data as $key => $value) {
                if (array_key_exists($key, $filters)) {
                    foreach ($filters[$key] as $filterName) {
                        $this->getFormData($name)[$key] = $this->filterValue($this->params[$this->formSubmitted($name)][$key], $filterName);
                    }
                }
            }
        }
    }

    public function filterValue($value, $filerName) {
        require_once PUBLIC_PATH . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Filter' . DIRECTORY_SEPARATOR . $filerName . '.php';
        $filter = new $filerName();
        return $filter->filter($value);
    }

    public function validateValue($value, $name, $formName, $validatorName) {
        $classPath = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Validator' . DIRECTORY_SEPARATOR . $validatorName . '.php';
        if (!file_exists($classPath)) {
            throw new ErrorException('Validator class ' . $validatorName . ' not exist');
        }
        require_once $classPath;
        $validator = new $validatorName();

        return $validator->validate($value, $name, $formName, $this);
    }

}
