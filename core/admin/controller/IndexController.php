<?php

namespace core\admin\controller;

use core\base\controller\BaseController;
use core\base\settings\Settings;

class IndexController extends BaseController
{
    protected function inputData()
    {
        // $db = Model::instance();
        // $table = 'teachers';
        // $result = $db->get($table, [
        //     'fields' => ['name' => 'new Katya 3'],
        // ]);

        // exit('I am admin panel');

        $redirect = PATH . Settings::get('routes')['admin']['alias'] . '/show';

        $this->redirect($redirect);
    }
}







// $files['gallery_img'] = ['new_red.jpg'];
// $files['img'] = 'main_img.jpg';

// $_POST['name'] = 'Masha';

// $result = $db->add($table, [
//     'fields' => ['name' => 'new Katya 3', 'content' => 'hello1'],
//     'files' => $files,
// ]);

// $_POST['id'] = 4;
// $_POST['name'] = 'Natalya';
// $_POST['content'] = "<p>Какой-то контент O'really</p>";
// $_POST['img'] = 'new_red_color.jpg';
// $_POST['gallery_img'] = json_encode(['new_red.jpg', 'new_green.png']);

// $result = $db->edit($table, [
//     'fields' => ['id' => 5, 'name' => 'Elizaveta']
// ]);

// Без 'fields' => [] удаляется запись. Иначе ставится null в поля, указанные в fields
// $result = $db->delete($table, [
//     // 'fields' => ['name', 'content', 'img'],
//     'where' => ['id' => 11],
//     'join' => [
//         [   'table' => 'students',
//             'on' => ['student_id', 'id']
//         ]
//     ]
// ]);

// exit('id = ' . $result['id'] . ' Name = ' . $result['name']);