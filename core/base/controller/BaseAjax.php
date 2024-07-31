<?php

namespace core\base\controller;

use core\base\settings\Settings;

class BaseAjax extends BaseController
{
    public function route()
    {
        $route = Settings::get('routes');
        $controller = $route['user']['path'] . 'AjaxController';
        $data = $this->isPost() ? $_POST : $_GET;

        if (isset($data['ADMIN_MODE'])) {
            unset($data['ADMIN_MODE']);
            $controller = $route['admin']['path'] . 'AjaxController';
        }

        $ajax = new $controller;
        $ajax->createAjaxData();

        return ($ajax->ajax());
    }

    protected function createAjaxData($data)
    {
        $this->data = $data;
    }

}