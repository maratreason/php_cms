<?php

namespace core\admin\controller;

use core\base\controller\BaseController;
use core\base\model\UserModel;

class LoginController extends BaseController
{
    protected $model;

    protected function inputData()
    {
        $this->model = UserModel::instance();
    }
}
