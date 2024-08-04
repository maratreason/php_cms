<?php

namespace core\admin\controller;

use core\base\settings\Settings;

class AddController extends BaseAdmin
{
    protected $action = 'add';

    protected function inputData()
    {
        if (!$this->userId) $this->execBase();

        $this->checkPost();
        $this->createTableData();
        $this->createForeignData();
        $this->createMenuPosition();
        $this->createRadio();
        $this->createOutputData();
        
        // Эмуляция данных для показа в add/teachers
        // $this->data = [
        //     'name' => 'Masha',
        //     'img' => '1.jpg',
        //     'gallery_img' => json_encode(['2.jpg', '3.jpg'])
        // ];

    }

    protected function createForeignProperty($arr, $rootItems)
    {
        if (in_array($this->table, $rootItems['tables'])) {
            $this->foreignData[$arr['COLUMN_NAME']][0]['id'] = 0;
            $this->foreignData[$arr['COLUMN_NAME']][0]['name'] = $rootItems['name'];
        }

        $where = '';
        $operand = '';

        $columns = $this->model->showColumns($arr['REFERENCED_TABLE_NAME']);

        $name = '';

        if ($columns['name']) {
            $name = 'name';
        } else {
            foreach ($columns as $key => $value) {
                if (strpos($key, 'name') !== false) {
                    $name = $key . ' as name';
                }
            }

            if (!$name) $name = $columns['id_row'] . ' as name';
        }

        if ($this->data) {
            if ($arr['REFERENCED_TABLE_NAME'] === $this->table) {
                // $this->columns['id_row'] => вернет значение. Например 'id'
                $where[$this->columns['id_row']] = $this->data[$this->columns['id_row']];
                $operand[] = '<>'; // <> => не равно
            }
        }
        
        $foreign = $this->model->get($arr['REFERENCED_TABLE_NAME'], [
            'fields' => [$arr['REFERENCED_COLUMN_NAME'] . ' as id', $name],
            'where' => $where,
            'operand' => $operand
        ]);

        if ($foreign) {
            if (!empty($this->foreignData[$arr['COLUMN_NAME']])) {
                foreach ($foreign as $value) {
                    // добавляем динамически значение $value
                    $this->foreignData[$arr['COLUMN_NAME']][] = $value;
                }
            } else {
                $this->foreignData[$arr['COLUMN_NAME']] = $foreign;
            }
        }
    }

    /**
     * Метод получения данных из связанных таблиц
     * @param boolean $settings
     * @return mixed
     */
    protected function createForeignData($settings = false)
    {
        if (!$settings) $settings = Settings::instance();

        $rootItems = $settings::get('rootItems');
        $keys = $this->model->showForeignKeys($this->table);

        if (!empty($keys)) {
            foreach ($keys as $item) {
                // Получаем ключи
                $this->createForeignProperty($item, $rootItems);
            }
        } else if ($this->columns['parent_id']) {
            $arr['COLUMN_NAME'] = 'parent_id';
            $arr['REFERENCED_COLUMN_NAME'] = $this->columns['id_row'];
            $arr['REFERENCED_TABLE_NAME'] = $this->table;

            $this->createForeignProperty($arr, $rootItems);
        }

        return;
    }

    protected function createMenuPosition($settings = false)
    {
        if (!empty($this->columns['menu_position'])) {
            $where = '';

            if (!$settings) $settings = Settings::instance();

            $rootItems = $settings::get('rootItems');

            if ($this->columns['parent_id']) {
                if (in_array($this->table, $rootItems['tables'])) {
                    $where = 'parent_id IS NULL OR parent_id = 0';
                } else {
                    $parent = $this->model->showForeignKeys($this->table, 'parent_id');

                    if ($parent) {
                        if (isset($parent['REFERENCED_TABLE_NAME']) && $this->table === $parent['REFERENCED_TABLE_NAME']) {
                            $where = 'parent_id IS NULL OR parent_id = 0';
                        } else {
                            if (isset($parent['REFERENCED_TABLE_NAME'])) {
                                $columns = $this->model->showColumns($parent['REFERENCED_TABLE_NAME']);
                                // Если в колонке родительской таблицы есть parent_id, то запускаем сортировку
                                if ($columns['parent_id']) {
                                    $order[] = 'parent_id';
                                } else {
                                    $order[] = $parent['REFERENCED_COLUMN_NAME'];
                                }
        
                                $id = $this->model->get($parent['REFERENCED_TABLE_NAME'], [
                                    'fields' => [$parent['REFERENCED_COLUMN_NAME']],
                                    'order' => $order,
                                    'limit' => '1'
                                ])[0][$parent['REFERENCED_COLUMN_NAME']];
        
                                if ($id) $where = ['parent_id' => $id];
                            }
                        }
                    } else {
                        $where = 'parent_id IS NULL OR parent_id = 0';
                    }
                }
            }

            $menu_pos = $this->model->get($this->table, [
                'fields' => ['COUNT(*) as count'],
                'where' => $where,
                'no_concat' => true // Не стыковать таблицы
            ])[0]['count'] + 1; // И сразу увеличиваем позицию меню, т.к. мы добавляем.

            for ($i = 1; $i <= $menu_pos; $i++) {
                $this->foreignData['menu_position'][$i - 1]['id'] = $i;
                $this->foreignData['menu_position'][$i - 1]['name'] = $i;
            }
        }

        return;
    }
}