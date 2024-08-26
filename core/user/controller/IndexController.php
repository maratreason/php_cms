<?php

namespace core\user\controller;

use core\admin\model\Model;
use core\base\controller\BaseController;

class IndexController extends BaseController
{
    protected $name;

    protected function inputData()
    {
        $this->init();

        $header = $this->render(TEMPLATE . 'header');
        $content = $this->render();
        $footer = $this->render(TEMPLATE . 'footer');

        return $this->render(TEMPLATE . 'templater', compact('header', 'content', 'footer'));
    }
}
