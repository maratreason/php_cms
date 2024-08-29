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
        $paginationWhere = $this->createWhere($set, $table);

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

        $this->createPagination($set, $table, $paginationWhere, $limit);

        $query = "SELECT $fields FROM $table $join $where $order $limit";

        if (!empty($set['return_query'])) return $query;

        $res = $this->query($query);

        if (isset($set['join_structure']) && $set['join_structure'] && $res) {
            $res = $this->joinStructure($res, $table);
        }

        return $res;
    }

    /**
     * Пагинация
     * < 1 2 3 ... 19 20 >
     *
     * @param $set
     * @param $table
     * @param $where
     * @return void
     */
    protected function createPagination($set, $table, $where, &$limit)
    {
        if (!empty($set['patination'])) {
            $this->postNumber = isset($set['pagination']['qty']) ? (int) $set['pagination']['qty'] : QTY;
            $this->linksNumber = isset($set['pagination']['qty_links']) ? (int) $set['pagination']['qty_links'] : QTY_LINKS;
            $this->page = !is_array($set['pagination']) ? (int) $set['pagination'] : (int)($set['pagination']['page'] ?? 1);

            if ($this->page > 0 && $this->postNumber > 0) {
                $this->totalCount = $this->getTotalCount($table, $where);
                $this->numberPages = (int) ceil($this->totalCount / $this->postNumber);
                $limit = 'LIMIT ' . ($this->page - 1) * $this->postNumber . ',' . $this->postNumber;
            }
        }
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
            $join_tables = isset($join_arr['tables']) ? $join_arr['tables'] : '';
            $query = 'DELETE ' . $table . $join_tables . ' FROM ' . $table . ' ' . $join . ' ' . $where;
        }

        return $this->query($query, 'u');
    }

    /**
     * Построение Union запросов
     *
     * @param string $table
     * @param array $set
     * @return this chaining
     */
    public function buildUnion($table, $set)
    {
        if (array_key_exists('fields', $set) && $set['fields'] === null) return $this;

        if (!array_key_exists('fields', $set) || empty($set['fields'])) {
            $set['fields'] = [];
            $columns = $this->showColumns($table);

            unset($columns['id_row'], $columns['multi_id_row']);

            foreach ($columns as $row => $item) {
                $set['fields'][] = $row;
            }
        }

        $this->union[$table] = $set;

        $this->union[$table]['return_query'] = true;

        return $this;
    }

    public function getUnion($set = [])
    {
        if (!$this->union) return false;

        $unionType = ' UNION ' . (!empty($set['type']) ? strtoupper($set['type']) . ' ' : '');

        $maxCount = 0;
        $maxTableCount = '';

        foreach ($this->union as $key => $item) {
            $count = count($item['fields']);
            $joinFields = '';

            if (!empty($item['join'])) {
                foreach ($item['join'] as $table => $data) {
                    if (array_key_exists('fields', $data) && $data['fields']) {
                        $count += count($data['fields']);
                        $joinFields = $table;
                    } else if (!array_key_exists('fields', $data) || (!$joinFields['data']) || $data['fields'] === null) {
                        $columns = $this->showColumns($table);
                        unset($columns['id_row'], $columns['multi_id_row']);
                        $count += count($columns);

                        foreach ($columns as $field => $value) {
                            $this->union[$key]['join'][$table]['fields'][] = $field;
                        }

                        $joinFields = $table;
                    }
                }
            } else {
                $this->union[$key]['no_concat'] = true;
            }

            if ($count > $maxCount || ($count === $maxCount && $joinFields)) {
                $maxCount = $count;
                $maxTableCount = $key;
            }

            $this->union[$key]['lastJoinTable'] = $joinFields;
            $this->union[$key]['countFields'] = $count;
        }

        $query = '';

        if ($maxCount && $maxTableCount) {
            $query .= '(' . $this->get($maxTableCount, $this->union[$maxTableCount]) . ')';
            unset($this->union[$maxTableCount]);
        }

        foreach ($this->union as $key => $item) {
            if (isset($item['countFields']) && $item['countFields'] < $maxCount) {

                for ($i = 0; $i < $maxCount - $item['countFields']; $i++) {
                    if ($item['lastJoinTable']) {
                        $item['join'][$item['lastJoinTable']]['fields'][] = null;
                    } else {
                        $item['fields'][] = null;
                    }
                }
                
            }

            $query && $query .= $unionType;
        
            $query .= '(' . $this->get($key, $item) . ')';
        }

        $order = $this->createOrder($set);

        $limit = !empty($set['limit']) ? 'LIMIT ' . $set['limit'] : '';

        if (method_exists($this, 'createPagination')) {
            $this->createPagination($set, "($query)", $limit);
        }

        $query .= " $order $limit";
        $this->union = [];
        
        return $this->query(trim($query));
    }

    /**
     * Метод показывает данные каждого поля таблицы в виде ассоциативного массива
     *
     * @param string $table
     * @return array
     */
    public final function showColumns($table)
    {
        if (!isset($this->tableRows[$table]) || !$this->tableRows[$table]) {
            $checkTable = $this->createTableAlias($table);

            if (isset($this->tableRows[$checkTable['table']])) {
                return $this->tableRows[$checkTable['alias']] = $this->tableRows[$checkTable['table']];
            }

            $query = "SHOW COLUMNS FROM {$checkTable['table']}";
            $res = $this->query($query);

            $this->tableRows[$checkTable['table']] = [];

            if (isset($res)) {
                foreach ($res as $row) {
                    $this->tableRows[$checkTable['table']][$row['Field']] = $row;
                    // Если первичный ключ еще не найден
                    if ($row['Key'] === 'PRI') {
                        // Если ячейки id_row нет, то она создастся и в нее придет поле $row['Field']
                        if (!isset($this->tableRows[$checkTable['table']]['id_row'])) {
                            $this->tableRows[$checkTable['table']]['id_row'] = $row['Field'];
                        } else { // если id_row существует, то не перезаписываем, а создаем ячейку multi_id_row(массив первичных ключей) и добавим в нее ячейку id_row
                            if (!isset($this->tableRows[$checkTable['table']]['multi_id_row'])) {
                                $this->tableRows[$checkTable['table']]['multi_id_row'][] = $this->tableRows[$checkTable['table']]['id_row'];
                            }
        
                            $this->tableRows[$checkTable['table']]['multi_id_row'][] = $row['Field'];
                        }
                    } 
                }
            }
        }

        if (isset($checkTable) && $checkTable['table'] !== $checkTable['alias']) {
            return $this->tableRows[$checkTable['alias']] = $this->tableRows[$checkTable['table']];
        }
        // $result = [id, id_row, name, content, img, gallery_img]
        return $this->tableRows[$table];
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
