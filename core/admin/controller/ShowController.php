<?php

namespace core\admin\controller;

use core\base\settings\Settings;

class ShowController extends BaseAdmin
{
    protected function inputData()
    {
        if (!$this->userId) $this->execBase();

        $this->createTableData();
        $this->createData();

        return $this->expansion();
    }

    protected function createData($arr = [])
    {
        $fields = [];
        // Сортировка
        $order = [];
        // Нарпавление сортировки
        $order_direction = [];

        if (!$this->columns['id_row']) return $this->data = [];

        $fields[] = $this->columns['id_row'] . ' as id';

        if ($this->columns['name']) $fields['name'] = 'name';

        if (!empty($this->columns['img'])) $fields['img'] = 'img';

        if (count($fields) < 3) {
            foreach ($this->columns as $key => $item) {
                if (!$fields['name'] && strpos($key, 'name') !== false) {
                    $fields['name'] = $key . ' as name';
                }

                if (empty($fields['img']) && strpos($key, 'img') !== false) {
                    $fields['img'] = $key . ' as img';
                }
            }
        }

        if (!empty($arr['fields'])) {
            if (is_array($arr['fields'])) {
                // Склеиваем массивы
                $fields = Settings::instance()->arrayMergeRecursive($fields, $arr['fields']);
            } else {
                $fields[] = $arr['fields'];
            }
        }

        if (!empty($this->columns['parent_id'])) {
            if (!in_array('parent_id', $fields)) $fields[] = 'parent_id';
            $order[] = 'parent_id';
        }

        if (!empty($this->columns['menu_position'])) {
            $order[] = 'menu_position';
        } else if (!empty($this->columns['date'])) {
            if ($order) {
                $order_direction = ['ASC', 'DESC'];
            } else {
                $order_direction[] = 'DESC';
            }

            $order[] = 'date';
        }

        if (!empty($arr['order'])) {
            if (is_array($arr['order'])) {
                $order = Settings::instance()->arrayMergeRecursive($order, $arr['order']);
            } else {
                $order[] = $arr['order'];
            }
        }

        if (!empty($arr['order_direction'])) {
            if (is_array($arr['order_direction'])) {
                $order_direction = Settings::instance()->arrayMergeRecursive($order_direction, $arr['order_direction']);
            } else {
                $order_direction[] = $arr['order_direction'];
            }
        }

        $this->data = $this->model->get($this->table, [
            'fields' => $fields,
            'order' => $order,
            'order_direction' => $order_direction,
        ]);
    }
}
