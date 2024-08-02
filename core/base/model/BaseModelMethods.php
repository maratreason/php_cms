<?php

namespace core\base\model;

/**
 * Класс методов, которые строят запросы.
 */
abstract class BaseModelMethods
{
    protected $sqlFunc = ['NOW()'];

    protected function createFields($set, $table = false)
    {
        $set['fields'] = (!empty($set['fields']) && is_array($set['fields'])) ? $set['fields'] : ['*'];
        $table = ($table && !isset($set['no_concat'])) ? $table . '.' : '';
        $fields = '';

        foreach ($set['fields'] as $field) {
            $fields .= $table . $field . ',';
        }

        return $fields;
    }

    protected function createOrder($set, $table = false)
    {
        $table = ($table && !isset($set['no_concat'])) ? $table . '.' : '';
        $order_by = '';

        if (!empty($set['order']) && is_array($set['order'])) {
            $set['order_direction'] = (!empty($set['order_direction']) && is_array($set['order_direction'])) ? $set['order_direction'] : ['ASC'];
            $order_by = 'ORDER BY ';
            $direct_count = 0;

            foreach ($set['order'] as $order) {
                if (isset($set['order_direction'][$direct_count])) {
                    $order_direction = strtoupper($set['order_direction'][$direct_count]);
                    $direct_count++;
                } else {
                    // положим предыдущее значение для остальных полей. Если последнее DESC, то дальше у всех сортировка будет DESC
                    $order_direction = strtoupper($set['order_direction'][$direct_count - 1]);
                }

                if (is_int($order)) {
                    // не делать сортировку
                    $order_by .= $order . ' ' . $order_direction . ',';
                } else {
                    // "ORDER BY id ASC, name DESC"
                    $order_by .= $table . $order . ' ' . $order_direction . ',';
                }

            }

            $order_by = rtrim($order_by, ',');
        }

        return $order_by;
    }

    protected function createWhere($set, $table = false, $instruction = 'WHERE')
    {
        $table = ($table && !isset($set['no_concat'])) ? $table . '.' : '';
        $where = '';

        if (!empty($set['where']) && is_string($set['where'])) {
            return $instruction . ' ' . trim($set['where']);
        }

        if (!empty($set['where']) && is_array($set['where'])) {
            // name = 'Masha', fio LIKE 'Smirnova'
            $set['operand'] = !empty($set['operand']) && is_array($set['operand']) ? $set['operand'] : ['='];
            // name = 'Masha' AND fio LIKE 'Smirnova'
            $set['condition'] = !empty($set['condition']) && is_array($set['condition']) ? $set['condition'] : ['AND'];
            $where = $instruction;

            $o_count = 0;
            $c_count = 0;

            foreach ($set['where'] as $key => $item) {
                $where .= ' ';

                if (isset($set['operand'][$o_count])) {
                    $operand = $set['operand'][$o_count];
                    $o_count++;
                } else {
                    $operand = $set['operand'][$o_count - 1];
                }

                if (isset($set['condition'][$c_count])) {
                    $condition = $set['condition'][$c_count];
                    $c_count++;
                } else {
                    $condition = $set['condition'][$c_count - 1];
                }

                // "=, <>, IN, NOT IN, LIKE, IN (SELECT * FROM table)"
                if ($operand === 'IN' || $operand === 'NOT IN') {
                    // Если сначала стоит SELECT то кладем переменную в $in_str
                    // strpos() ищет первое вхождение подстроки
                    if (is_string($item) && strpos($item, 'SELECT') === 0) {
                        $in_str = $item;
                    } else {
                        // если например fields = ['id' => '1, 2, 3, 4', 'name' => 'Masha, Olya, Sveta']
                        // Поэтому нужно разобрать строку
                        if (is_array($item)) $temp_item = $item;
                        // Иначе, если значения это массив данных, то разделяем их по запятой
                        else $temp_item = explode(',', $item);

                        $in_str = '';

                        foreach ($temp_item as $v) {
                            // 'Masha, ' => 'Masha,'
                            $in_str .= "'" . addslashes(trim($v)) . "',";
                        }
                    }

                    $where .= $table . $key . ' ' . $operand . ' (' . trim($in_str, ',') . ') ' . $condition;

                } else if (strpos($operand, 'LIKE') !== false) {
                    $like_template = explode('%', $operand);

                    foreach ($like_template as $lt_key => $lt) {
                        if (!$lt) {
                            if (!$lt_key) {
                                $item = '%' . $item;
                            } else {
                                $item .= '%';
                            }
                        }
                    }

                    $where .= $table . $key . ' LIKE ' . "'" . addslashes($item) . "' $condition";

                } else {
                    // WHERE id = (SELECT id FROM students)
                    // Если SELECT стоит на первой позиции, значит мы хотим сделать вложенный запрос
                    if (strpos($item, 'SELECT') === 0) {
                        $where .= $table . $key . $operand . '(' . $item . ") $condition";
                    } else {
                        $where .= $table . $key . $operand . "'" . $item . "' $condition";
                    }
                }
            }
            // Убираем из строки последнее AND, OR
            // Находим последнее вхождение AND в строке
            $where = substr($where, 0, strrpos($where, $condition));
        }

        return $where;
    }

    protected function createJoin($set, $table, $new_where = false)
    {
        $fields = '';
        $join = '';
        $where = '';
        $tables = '';

        if (isset($set['join'])) {

            $join_table = $table;

            foreach ($set['join'] as $key => $item) {
                // 'join' => [0 => ['fields' => '']...]
                if (is_int($key)) {
                    if (!isset($item['table'])) {
                        continue;
                    } else {
                        $key = $item['table'];
                    }
                }

                if (isset($join)) $join .= ' ';

                if (isset($item['on'])) {

                    $join_fields = [];

                    switch (2) {
                        // если в fields лежат 2 элемента то все ок.
                        // 'on' => ['parent_id', 'id']
                        case (!empty($item['on']['fields']) && is_array($item['on']['fields']) && count($item['on']['fields'])):
                            $join_fields = $item['on']['fields'];
                            break;

                        case (is_array($item['on']) && count($item['on'])):
                            $join_fields = $item['on'];
                            break;

                        default:
                            // выйти из второго уровня цикла и перекинуть на следующую итерацию foreach
                            continue 2;
                            break;
                    }

                    if (!isset($item['type'])) {
                        $join .= 'LEFT JOIN ';
                    } else {
                        $join .= trim(strtoupper($item['type'])) . ' JOIN ';
                    }

                    $join .= $key . ' ON ';

                    if (isset($item['on']['table'])) {
                        $join .= $item['on']['table'];
                    } else {
                        $join .= $join_table;
                    }
                    // $key - таблица
                    $join .= '.' . $join_fields[0] . '=' . $key . '.' . $join_fields[1];
                    $join_table = $key;
                    $tables .= ', ' . trim($join_table);

                    if ($new_where) {
                        if (!empty($item['where'])) {
                            $new_where = false;
                        }

                        $group_condition = 'WHERE';
                    } else {
                        $group_condition = isset($item['group_condition']) ? strtoupper($item['group_condition']) : 'AND';
                    }

                    $fields .= $this->createFields($item, $key);
                    $where .= $this->createWhere($item, $key, $group_condition);
                }
            }
        }

        return compact('fields', 'join', 'where', 'tables');
    }

    /****************************************************************************
     * Методы вставки, редактирования и удаления с базы данных
     ****************************************************************************/

    protected function createInsert($fields, $files, $except)
    {
        $insert_arr = [];
        $insert_arr['fields'] = '(';
        $array_type = array_keys($fields)[0];

        if (is_int($array_type)) {
            $check_fields = false;
            $count_fields = 0;

            foreach ($fields as $i => $item) {
                $insert_arr['values'] .= '(';

                if (!$count_fields) $count_fields = count($fields[$i]);

                $j = 0;

                foreach($item as $row => $value) {
                    if (!empty($except) && in_array($row, $except)) continue;

                    if (!$check_fields) $insert_arr['fields'] .= $row . ',';

                    if (in_array($value, $this->sqlFunc)) {
                        $insert_arr['values'] .= $value . ',';
                    } else if ($value == 'NULL' || $value === NULL) {
                        $insert_arr['values'] .= "NULL" . ',';
                    } else {
                        $insert_arr['values'] .= "'" . addslashes($value) . "',";
                    }

                    $j++;

                    if ($j === $count_fields) break;
                }

                if ($j < $count_fields) {
                    for (; $j < $count_fields; $j++) {
                        $insert_arr['values'] .= 'NULL,';
                    }
                }

                $insert_arr['values'] = rtrim($insert_arr['values'], ',') . '),';

                if (!$check_fields) $check_fields = true;
            }
        } else {
            $insert_arr['values'] = '(';

            if (!empty($fields)) {
                foreach($fields as $row => $value) {
                    if (!empty($except) && in_array($row, $except)) continue;

                    $insert_arr['fields'] .= $row . ',';

                    if (in_array($value, $this->sqlFunc)) {
                        $insert_arr['values'] .= $value . ',';
                    } else if ($value == 'NULL' || $value === NULL) {
                        $insert_arr['values'] .= "NULL" . ',';
                    } else {
                        $insert_arr['values'] .= "'" . addslashes($value) . "',";
                    }
                }
            }

            if (!empty($files)) {
                foreach ($files as $row => $file) {
                    $insert_arr['fields'] .= $row . ',';

                    if (is_array($file)) {
                        $insert_arr['values'] .= "'" . addslashes(json_encode($file)) . "',";
                    } else {
                        $insert_arr['values'] .= "'" . addslashes($file) . "',";
                    }
                }
            }

            $insert_arr['values'] = rtrim($insert_arr['values'], ',') . ')';
        }

        $insert_arr['fields'] = rtrim($insert_arr['fields'], ',') . ')';
        $insert_arr['values'] = rtrim($insert_arr['values'], ',');

        return $insert_arr;
    }

    protected function createUpdate($fields, $files, $except)
    {
        $update = '';

        if ($fields) {
            foreach ($fields as $row => $value) {
                // если поле есть в массиве $except, то переходим на следующую итерацию, т.к. это поле не нужно добавлять в бд
                if ($except && in_array($row, $except)) continue;

                $update .= $row . '=';

                if (in_array($value, $this->sqlFunc)) {
                    $update .= $value . ',';
                } elseif($value === NULL) {
                    $update .= "NULL" . ',';
                } else {
                    $update .= "'". addslashes($value) . "',";
                }

            }
        }

        if ($files) {
            foreach ($files as $row => $file) {
                $update .= $row . '=';

                if (is_array($file)) {
                    $update .= "'" . addslashes(json_encode($file)) . "',";
                } else {
                    $update .= "'" . addslashes($file) . "',";
                }
            }
        }

        return rtrim($update, ',');
    }

}
