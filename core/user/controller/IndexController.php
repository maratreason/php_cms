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

        $res = $model->get('teachers', [
            'where' => ['id' => '37,38'],
            'operand' => ['IN'],
            'join' => [
                'stud_teachers' => ['on' => ['id', 'teachers']],
                'students' => [
                    'fields' => ['name'],
                    'on' => ['students', 'id']
                ],
            ],
            'join_structure' => true
        ]);

        print_arr($res);

        exit($res);

        return compact('header', 'content', 'footer');
    }

    protected function outputData()
    {
        $vars = func_get_arg(0);
        
        $this->page = $this->render(TEMPLATE . 'templater', $vars);
    }
}
