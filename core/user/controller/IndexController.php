<?php

namespace core\user\controller;

use core\admin\model\Model;
use core\base\controller\BaseController;

class IndexController extends BaseController
{
    protected $name;

    protected function inputData()
    {
        $name = 'Ivan';

        $this->init();

        $content = $this->render('', compact('name'));
        $header = $this->render(TEMPLATE . 'header');
        $footer = $this->render(TEMPLATE . 'footer');

        $model = Model::instance();

        $res = $model->get('goods', [
            'where' => ['id' => '37,38'],
            'operand' => ['IN'],
            'join' => [
                'goods_filters' => [
                    'fields' => null,
                    'on' => ['id', 'teachers']
                ],
                'filters f' => [
                    'fields' => ['name as student_name', 'content'],
                    'on' => ['students', 'id']
                ],
                'filters' => [
                    'on' => ['parent_id', 'id']
                ],
            ],
            'join_structure' => true,
            'order' => ['id'],
            'order_direction' => ['DESC']
        ]);

        exit(print_arr($res));

        return compact('header', 'content', 'footer');
    }

    protected function outputData()
    {
        $vars = func_get_arg(0);
        
        $this->page = $this->render(TEMPLATE . 'templater', $vars);
    }
}
