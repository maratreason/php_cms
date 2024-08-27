<?php

namespace core\user\controller;

class IndexController extends BaseUser
{
    protected $name;

    protected function inputData()
    {
        parent::inputData();

        $sales = $this->model->get('sales', [
            'where' => ['visible' => 1],
            'order' => ['menu_position']
        ]);

        return compact('sales');
    }
}
