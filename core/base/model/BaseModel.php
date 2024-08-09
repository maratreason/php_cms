<?php

namespace core\base\model;

use core\base\exceptions\DbException;
use mysqli;

/**
 * Класс методов, которые работают с базой данных.
 */
abstract class BaseModel extends BaseModelMethods
{
    protected $db;

    protected function connect()
    {
        $this->db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($this->db->connect_error) {
            throw new DbException('Ошибка подключения к базе данных: ' . $this->db->connect_errno . ' ' . $this->db->connect_error);
        }

        $this->db->query("SET NAMES UTF8");
    }

    /**
     * Основной метод запроса к БД
     *
     * @param string $query
     * @param string $crud = 'r - SELECT / c - INSERT / u - UPDATE / d - DELETE 
     * @param boolean $return_id
     * @return array|bool|mixed
     * @throws DbException
     */
    public final function query($query, $crud = 'r', $return_id = false)
    {
        $result = $this->db->query($query);

        if ($this->db->affected_rows === -1) {
            throw new DbException('Ошибка в SQL запросе: ' . $query . ' - ' . $this->db->errno . ' ' . $this->db->error);
        }

        switch ($crud) {
            case 'r':
                if ($result->num_rows) {
                    $res = [];

                    for ($i = 0; $i < $result->num_rows; $i++) {
                        $res[] = $result->fetch_assoc();
                    }

                    return $res;
                }

                return false;
                break;
            case 'c':
                if ($return_id) {
                    return $this->db->insert_id;
                }

                return true;
                break;
            case 'u':
                return true;
                break;
            default:
                return true;
                break;
        }
    }

    /**
     * Получение данных из таблицы
     * 
     * [
     *  'fields' => ['id', 'name'],
     *  'no_concat' => false/true. Если true не присоединять имя таблицы к полям и where 
     *  'where' => ['fio' => 'Smirnova', 'name' => 'Apple', 'surname' => 'Sergeevna'],
     *  'operand' => ['=', '<>'],
     *  'condition' => ['AND'],
     *  'order' => ['fio', 'name'],
     *  'order_direction' => ['ASC', 'DESC'],
     *  'limit' => '1',
     *  'join' => [
     *      'join_table1' => [
     *           'table' => 'join_table1',
     *           'fields' => ['id as j_id', 'name as j_name'],
     *           'type' => 'left', // left join
     *           'where' => ['name' => 'Samsung'],
     *           'operand' => ['='],
     *           'condition' => ['OR'],
     *           'on' => ['id', 'parent_id'],
     *           'group_condition' => 'AND'
     *       ],
     *       'join_table2' => [
     *           'table' => 'join_table2',
     *           'fields' => ['id as j2_id', 'name as j2_name'],
     *           'type' => 'left', // left join
     *           'where' => ['name' => 'Galaxy x8'],
     *           'operand' => ['='],
     *           'condition' => ['OR'],
     *           'on' => [
     *               'table' => 'categories',
     *               'fields' => ['id']
     *           ]
     *       ]
     *   ]
     * ]
     *
     * @param $table таблица
     * @param array $set параметры запроса
     * @return $query
     */
    public final function get($table, $set = [])
    {
        $fields = $this->createFields($set, $table);
        $order = $this->createOrder($set, $table);
        $where = $this->createWhere($set, $table);

        if (empty($where)) {
            $new_where = true;
        } else {
            $new_where = false;
        }

        $join_arr = $this->createJoin($set, $table, $new_where);

        $fields .= $join_arr['fields'];
        $join = $join_arr['join'];
        $where .= $join_arr['where'];

        // обрезаем последнюю запятую "id,name,price," => "id,name,price"
        $fields = rtrim($fields, ',');
        $limit = isset($set['limit']) ? 'LIMIT ' . $set['limit'] : '';
        $query = "SELECT $fields FROM $table $join $where $order $limit";

        $res = $this->query($query);

        if (isset($set['join_structure']) && $set['join_structure'] && $res) {
            $res = $this->joinStructure($res, $table);
        }

        return $res;
    }

    /**
     * Добавление данных в БД
     *
     * @param $table таблица для вставки данных
     * @param array $set массив параметров:
     * fields => [поле => значение]; если не указан, то обрабатывается $_POST[поле => значение]
     * разрешена передача например NOW() в качестве Mysql функции обычно строкой
     * files => [поле => значение]; можно подать массив вида [поле => [массив значений]]
     * except => ['исключение 1', 'исключение 2'] - исключает данные элементы массива из добавления в запрос
     * return_id => true|false - возвращать или нет идентификатор вставленной записи
     * @return mixed
     */
    public final function add($table, $set = [])
    {
        $set['fields'] = (!empty($set['fields']) && is_array($set['fields'])) ? $set['fields'] : $_POST;
        $set['files'] = (!empty($set['files']) && is_array($set['files'])) ? $set['files'] : false;

        // Если все пусто то не заносим в базу данных
        if (!$set['fields'] && !$set['files']) return false;

        $set['return_id'] = isset($set['return_id']) ? true : false;
        $set['except'] = (!empty($set['except']) && is_array($set['except'])) ? $set['except'] : false;
        // получаем массив
        $insert_arr = $this->createInsert($set['fields'], $set['files'], $set['except']);
        $query = "INSERT INTO $table {$insert_arr['fields']} VALUES {$insert_arr['values']}";

        return $this->query($query, 'c', $set['return_id']);

    }

    /**
     * Редактирование данных
     *
     * @param string $table
     * @param array $set
     * @return mixed
     */
    public final function edit($table, $set = [])
    {
        $where = '';
        $set['fields'] = (!empty($set['fields']) && is_array($set['fields'])) ? $set['fields'] : $_POST;
        $set['files'] = (!empty($set['files']) && is_array($set['files'])) ? $set['files'] : false;

        if (!$set['fields'] && !$set['files']) return false;

        $set['except'] = (!empty($set['except']) && is_array($set['except'])) ? $set['except'] : false;

        if (empty($set['all_rows'])) {
            if (!empty($set['where'])) {
                $where = $this->createWhere($set);
            } else {
                $columns = $this->showColumns($table);

                if (!$columns) return false;
                // Если первичный ключ у нас есть и в массиве есть такая же ячейка какая есть в id_row
                if (isset($columns['id_row']) && isset($set['fields'][$columns['id_row']])) {
                    $where = 'WHERE ' . $columns['id_row'] . '=' . $set['fields'][$columns['id_row']];
                    // После получения данных разрегистрируем
                    unset($set['fields'][$columns['id_row']]);
                }
            }
        }

        $update = $this->createUpdate($set['fields'], $set['files'], $set['except']);
        $query = "UPDATE $table SET $update $where";

        return $this->query($query, 'u');
    }

    /**
     * Удаление данных из БД
     * 
     * [
     *  'fields' => ['id', 'name'],
     *  'where' => ['fio' => 'Smirnova', 'name' => 'Apple', 'surname' => 'Sergeevna'],
     *  'operand' => ['=', '<>'],
     *  'condition' => ['AND'],
     *  'join' => [
     *       [
     *           'table' => 'join_table1',
     *           'fields' => ['id as j_id', 'name as j_name'],
     *           'type' => 'left', // left join
     *           'where' => ['name' => 'Samsung'],
     *           'operand' => ['='],
     *           'condition' => ['OR'],
     *           'on' => ['id', 'parent_id'],
     *           'group_condition' => 'AND'
     *       ],
     *       'join_table2' => [
     *           'table' => 'join_table2',
     *           'fields' => ['id as j2_id', 'name as j2_name'],
     *           'type' => 'left', // left join
     *           'where' => ['name' => 'Galaxy x8'],
     *           'operand' => ['='],
     *           'condition' => ['OR'],
     *           'on' => [
     *               'table' => 'categories',
     *               'fields' => ['id']
     *           ]
     *       ]
     *   ]
     * ]
     *
     * @param string $table - таблица
     * @param array $set
     * @return mixed
     */
    public final function delete($table, $set)
    {
        $table = trim($table);
        // Обязательно еще передаем $table. Если будем делать join при удалении.
        $where = $this->createWhere($set, $table);
        $columns = $this->showColumns($table);

        if (!$columns) return false;

        if (isset($set['fields']) && is_array($set['fields']) && !empty($set['fields'])) {
            if (isset($columns['id_row'])) {
                $fields = [];
                // [0 => 'id']
                $key = array_search($columns['id_row'], $set['fields']);

                if ($key !== false) unset($set['fields'][$key]);

                foreach ($set['fields'] as $field) {
                    $fields[$field] = $columns[$field]['Default'];
                }

                $update = $this->createUpdate($fields, false, false);
                $query = "UPDATE $table SET $update $where";
            }
        } else {
            $join_arr = $this->createJoin($set, $table);
            $join = $join_arr['join'];
            $join_tables = $join_arr['tables'];
            $query = 'DELETE ' . $table . $join_tables . ' FROM ' . $table . ' ' . $join . ' ' . $where;
        }

        return $this->query($query, 'u');
    }

    /**
     * Метод показывает данные каждого поля таблицы
     *
     * @param string $table
     * @return array
     */
    public final function showColumns($table)
    {
        if (!isset($this->tableRows[$table]) || !$this->tableRows[$table]) {
            $query = "SHOW COLUMNS FROM $table";
            $res = $this->query($query);

            if (isset($res)) {
                foreach ($res as $row) {
                    $this->tableRows[$table][$row['Field']] = $row;
                    // Если первичный ключ еще не найден
                    if ($row['Key'] === 'PRI') {
                        // Если ячейки id_row нет, то она создастся и в нее придет поле $row['Field']
                        if (!isset($this->tableRows[$table]['id_row'])) {
                            $this->tableRows[$table]['id_row'] = $row['Field'];
                        } else { // если id_row существует, то не перезаписываем, а создаем ячейку multi_id_row(массив первичных ключей) и добавим в нее ячейку id_row
                            if (!isset($this->tableRows[$table]['multi_id_row'])) {
                                $this->tableRows[$table]['multi_id_row'][] = $this->tableRows[$table]['id_row'];
                            }
                            // после этого следущим элементом в массив первичных ключей добавляем ячейку $row['Field'].
                            $this->tableRows[$table]['multi_id_row'][] = $row['Field'];
                        }
                    } 
                }
            }

            return $this->tableRows[$table]; // $result = [id, id_row, name, content, img, gallery_img]
        }
    }

    /**
     * Выдача всех таблиц из БД.
     * Метод нужен для провекри наличия вспомогательных таблиц.
     * @return array - список таблиц.
     */
    public final function showTables()
    {
        $query = 'SHOW TABLES';
        $tables = $this->query($query);
        $table_arr = [];

        if (!empty($tables)) {
            foreach ($tables as $table) {
                 // убираем первый элемент массива через функцию reset()
                $table_arr[] = reset($table);
            }
        }
        // Возвращаем список таблиц
        return $table_arr;
    }
}
