<?php

namespace core\admin\controller;

use core\admin\model\Model;
use core\base\controller\BaseController;
use core\base\exceptions\RouteException;
use core\base\settings\Settings;
use libraries\FileEdit;

abstract class BaseAdmin extends BaseController
{
    protected $model;
    // Какую таблицу данных подключить. Из какой таблицы показывать данные
    protected $table;
    protected $columns;
    protected $foreignData;

    protected $adminPath;
    
    protected $menu;
    protected $title;

    protected $alias;
    protected $fileArray;

    protected $messages;
    protected $settings;

    protected $translate;
    protected $blocks = [];

    protected $templateArr;
    protected $formTemplates;
    protected $noDelete;

    protected function inputData()
    {
        if (!MS_MODE) {
            if (preg_match('/msie|trident.+?rv\s*:/i', $_SERVER['HTTP_USER_AGENT'])) {
                exit('Вы используете устаревшую версию браузера. Пожалуйста обновитесь до актуальной версии');
            }
        }
        // Защищаем страницы
        $this->checkAuth(true);

        $this->init(true);
        $this->title = 'VG engine';

        if (!$this->model) $this->model = Model::instance();
        if (!$this->menu) $this->menu = Settings::get('projectTables');
        // Без PATH ссылка будет /admin/admin/teachers...
        if (!$this->adminPath) $this->adminPath = PATH . Settings::get('routes')['admin']['alias'] . '/';
        if (!$this->templateArr) $this->templateArr = Settings::get('templateArr');
        if (!$this->formTemplates) $this->formTemplates = Settings::get('formTemplates');
        if (!$this->messages) $this->messages = include $_SERVER['DOCUMENT_ROOT'] . PATH . Settings::get('messages') . 'informationMessages.php';

        $this->sendNoCacheHeaders();
    }

    protected function outputData()
    {
        if (!$this->content) {
            $args = func_get_arg(0);
            $vars = $args ? $args : [];

            // if (!$this->template) $this->template = ADMIN_TEMPLATE . 'show';
            $this->content = $this->render($this->template, $vars);
        }

        $this->header = $this->render(ADMIN_TEMPLATE . 'include/header');
        $this->footer = $this->render(ADMIN_TEMPLATE . 'include/footer');

        return $this->render(ADMIN_TEMPLATE . 'layout/default');
    }

    protected function sendNoCacheHeaders()
    {
        header("Last-Modified: " . gmdate("D, d m Y H:i:s") . " GMT");
        // Cache-Control переопределяют Expires In. Expires In уже давно устарели. Почитать про это.
        header("Cache-Control: no-cache, must-revalidate");
        // Каждый раз загружать с сервера, а не показывать из кэша
        header("Cache-Control: max-age=0");
        // Для Internet explorer. post-check - показывает данные пользователю, но их обязатльно надо проверить. А pre-check обязательно проверяет данные перед показом пользователю
        header("Cache-Control: post-check=0,pre-check=0");
    }

    protected function execBase()
    {
        self::inputData();
    }

    protected function createTableData($settings = false)
    {
        if (!$this->table) {
            if ($this->parameters) {
                $this->table = array_keys($this->parameters)[0];
            } else {
                if (!$settings) $settings = Settings::instance();

                $this->table = $settings::get('defaultTable');
            }
        }

        $this->columns = $this->model->showColumns($this->table);

        if (!$this->columns) new RouteException('Не найдены поля в таблице - ' . $this->table);
    }

    protected function expansion($args = [], $settings = false)
    {
        $filename = explode('_', $this->table);
        $className = '';

        foreach ($filename as $item) $className .= ucfirst($item);

        if (!$settings) {
            $path = Settings::get('expansion');
        } else if (is_object($settings)) {
            $path = $settings::get('expansion');
        } else {
            $path = $settings;
        }

        $class = $path . $className . 'Expansion';

        // Если класс expansion, то подключаем класс
        if (is_readable($_SERVER['DOCUMENT_ROOT'] . PATH . $class . '.php')) {
            $class = str_replace('/', '\\', $class);
            $exp = $class::instance();

            foreach ($this as $name => $value) {
                // Сохраняем ссылки на каждое из свойств $this
                // тем самым через $this->title в TeachersExpansion мы поменяем и исходное свойство $this->title
                $exp->$name = &$this->$name;
            }

            return $exp->expansion($args);
            // Если файл expansion, то подключаем файл
        } else {
            $file = $_SERVER['DOCUMENT_ROOT'] . PATH . $path . $this->table . '.php';

            extract($args);

            if (is_readable($file)) return include $file;
        }

        return false;
    }

    protected function createOutputData($settings = false)
    {
        if (!$settings) $settings = Settings::instance();

        $blocks = $settings::get('blockNeedle');

        $this->translate = $settings::get('translate');

        if (!$blocks || !is_array($blocks)) {
            foreach ($this->columns as $name => $item) {
                if ($name === 'id_row') continue;
                // Если пусто в $translate = ['content' => []] => ['content' => ['content']]
                if (!isset($this->translate[$name])) $this->translate[$name][] = $name;

                $this->blocks[0][] = $name;
            }

            return;
        }
        // $default = ['vg-rows']
        $default = array_keys($blocks)[0];

        foreach ($this->columns as $name => $item) {

            if ($name === 'id_row') continue;

            $insert = false;

            foreach ($blocks as $block => $value) {
                if (!empty($this->blocks) && !array_key_exists($block, $this->blocks)) $this->blocks[$block] = [];

                if (in_array($name, $value)) {
                    $this->blocks[$block][] = $name;
                    // Вставка произошла
                    $insert = true;
                    break;
                }
            }
            // Если вставка не произошла, то вставляем в дефолтный блок
            if (!$insert) $this->blocks[$default][] = $name;
            // Если пусто в $blockNeedle = ['vg-rows' => ['content']]
            if (!isset($this->translate[$name])) $this->translate[$name][] = $name;
        }

        return;
    }

    protected function createRadio($settings = false)
    {
        if (!$settings) $settings = Settings::instance();

        $radio = $settings::get('radio');

        if (!empty($radio)) {
            foreach ($this->columns as $name => $item) {
                if (!empty($radio[$name])) {
                    $this->foreignData[$name] = $radio[$name];
                }
            }
        }
    }

    protected function checkPost($settings = false)
    {
        if (!$settings) $settings = Settings::instance();

        if ($this->isPost()) {
            $this->clearPostFields($settings);
            $this->table = $this->clearStr($_POST['table']);

            unset($_POST['table']);

            if (!empty($this->table)) {
                $this->createTableData($settings);
                $this->editData();
            }
        }
    }

    protected function countChar($str, $counter, $answer, $arr = [])
    {
        // Мультибайтовый длина строки
        if (mb_strlen($str) > $counter) {
            // Функция описана в libraries/functions.php
            $str_res = mb_str_replace('$1', '"' . $answer . '"', $this->messages['count']);
            $str_res = mb_str_replace('$2', '"' . $counter . '"', $str_res);
            $_SESSION['res']['answer'] = '<div class="error">' . $str_res . '</div>';

            $this->addSessionData($arr);
        }
    }

    protected function emptyFields($str, $answer, $arr = [])
    {
        if (empty($str)) {
            $_SESSION['res']['answer'] = '<div class="error">' . $this->messages['empty'] . ' ' . $answer . '</div>';
            $this->addSessionData($arr);
        }
    }

    protected function clearPostFields($settings, &$arr = [])
    {
        if (empty($arr)) $arr = &$_POST;

        if (!$settings) $settings = Settings::instance();

        $id = (!empty($this->columns['id_row']) && !empty($_POST[$this->columns['id_row']])) ? $_POST[$this->columns['id_row']] : false;
        $validate = $settings::get('validation');
        $answer = '';

        if (!$this->translate) $this->translate = $settings::get('translate');

        foreach ($arr as $key => $item) {
            if (is_array($item)) {
                $this->clearPostFields($settings, $item);
            } else {
                if (is_numeric($item)) {
                    $arr[$key] = $this->clearNum($item);
                }

                if (!empty($validate)) {
                    if (isset($validate[$key])) {
                        if (!empty($this->translate[$key])) {
                            $answer = $this->translate[$key][0];
                        } else {
                            $answer = $key;
                        }

                        if (!empty($validate[$key]['crypt'])) {

                            if ($id) {
                                if (empty($item)) {
                                    unset($arr[$key]);
                                    continue;
                                }

                                $arr[$key] = md5($item);
                            }

                        }

                        if (!empty($validate[$key]['empty'])) $this->emptyFields($item, $answer, $arr);

                        if (!empty($validate[$key]['trim'])) $arr[$key] = trim($item);

                        if (!empty($validate[$key]['int'])) $arr[$key] = $this->clearNum($item);

                        if (!empty($validate[$key]['count'])) $this->countChar($item, $validate[$key]['count'], $answer, $arr);

                    }
                }
            }
        }

        return true;
    }

    protected function editData($returnId = false)
    {
        $id = false;
        $method = 'add';
        $where = [];

        if (!empty($_POST['return_id'])) $returnId = true;

        if (!empty($_POST[$this->columns['id_row']])) {

            $id = is_numeric($_POST[$this->columns['id_row']])
                ? $this->clearNum($_POST[$this->columns['id_row']])
                : $this->clearStr($_POST[$this->columns['id_row']]);

            if ($id) {
                $where = [$this->columns['id_row'] => $id];
                $method = 'edit';
            }
        }

        foreach ($this->columns as $key => $item) {
            if ($key === 'id_row') continue;

            if ($item['Type'] === 'date' || $item['Type'] === 'datetime') {
                !isset($_POST[$key]) && $_POST[$key] = 'NOW()';
            }
        }

        $this->createFiles($id);
        $this->createAlias($id);
        $this->updateMenuPosition($id);

        $except = $this->checkExceptFields();

        $res_id = $this->model->$method($this->table, [
            'files' => $this->fileArray,
            'where' => $where,
            'return_id' => true,
            'except' => $except
        ]);

        if (!$id && $method === 'add') {
            $_POST[$this->columns['id_row']] = $res_id;
            $answerSuccess = $this->messages['addSuccess'];
            $answerFail = $this->messages['addFail'];
        } else {
            $answerSuccess = $this->messages['editSuccess'];
            $answerFail = $this->messages['editFail'];
        }

        $this->checkManyToMany();

        // Передаем все объявленные переменные в expansion()
        $this->expansion(get_defined_vars());

        $result = $this->checkAlias($_POST[$this->columns['id_row']]);

        if (isset($res_id)) {
            $_SESSION['res']['answer'] = '<div class="success">' . $answerSuccess . '</div>';

            if (!$returnId) $this->redirect();

            return $_POST[$this->columns['id_row']];
        } else {
            $_SESSION['res']['answer'] = '<div class="error">' . $answerFail . '</div>';

            if (!$returnId) $this->redirect();
        }

    }

    protected function checkExceptFields($arr = [])
    {
        if (!$arr) $arr = $_POST;

        $except = [];

        if ($arr) {
            foreach ($arr as $key => $item) {
                if (!isset($this->columns[$key])) $except[] = $key;
            }
        }

        return $except;
    }

    protected function createFiles($id = null)
    {
        $fileEdit = new FileEdit();
        $this->fileArray = $fileEdit->addFile($this->table);

        if ($id) {
            $this->checkFiles($id);
        }

        if (!empty($_POST['js-sorting']) && $this->fileArray) {
            foreach($_POST['js-sorting'] as $key => $item) {
                if (!empty($item) && !empty($this->fileArray[$key])) {
                        $fileArr = json_decode($item);
                    if ($fileArr) {
                        $this->fileArray[$key] = $this->sortingFiles($fileArr, $this->fileArray[$key]);
                    }
                }
            }
        }
    }

    protected function sortingFiles($fileArr, $arr) {
        $res = [];

        foreach ($fileArr as $file) {
            if (!is_numeric($file)) {
                $file = substr($file, strlen(PATH . UPLOAD_DIR));
            } else {
                $file = $arr[$file];
            }

            if (isset($file) && in_array($file, $arr)) {
                $res[] = $file;
            }
        }

        return $res;
    }

    protected function updateMenuPosition($id = false)
    {
        if (isset($_POST['menu_position'])) {
            $where = false;

            if ($id && $this->columns['id_row']) $where = [$this->columns['id_row'] => $id];

            if (array_key_exists('parent_id', $_POST)) {
                $this->model->updateMenuPosition($this->table, 'menu_position', $where, $_POST['menu_position'], ['where' => 'parent_id']);
            } else {
                $this->model->updateMenuPosition($this->table, 'menu_position', $where, $_POST['menu_position']);
            }
        }
    }

    protected function createAlias($id = false)
    {
        if (isset($this->columns['alias'])) {
            if (!isset($_POST['alias'])) {
                if ($_POST['name']) {
                    $alias_str = $this->clearStr($_POST['name']);
                } else {
                    foreach ($_POST as $key => $item) {
                        if (strpos($key, 'name') !== false && $item) {
                            $alias_str = $this->clearStr($item);
                            break;
                        }
                    }
                }
            } else {
                $alias_str = $_POST['alias'] = $this->clearStr($_POST['alias']);
            }

            $textModify = new \libraries\TextModify();
            $alias = $textModify->translit($alias_str);
            $where['alias'] = $alias;
            $operand[] = '=';

            if ($id) {
                $where[$this->columns['id_row']] = $id;
                $operand[] = '<>';
            }

            $res_alias = $this->model->get($this->table, [
                'fields' => ['alias'],
                'where' => $where,
                'operand' => $operand,
                'limit' => '1'
            ])[0] ?? false;

            if (!$res_alias) {
                $_POST['alias'] = $alias;
            } else {
                $this->alias = $alias;
                $_POST['alias'] = '';
            }

            if ($_POST['alias'] && $id) {
                // checkOldAlias() в EditController. Он нужен только там.
                method_exists($this, 'checkOldAlias') && $this->checkOldAlias($id);
            }

        }

    }

    protected function checkAlias($id)
    {
        if ($id) {
            if ($this->alias) {
                $this->alias .= '-' . $id;
                $this->model->edit($this->table, [
                    'fields' => ['alias' => $this->alias],
                    'where' => [$this->columns['id_row'] => $id]
                ]);
                
                return true;
            }
        }

        return false;
    }

    protected function checkOldAlias($id)
    {
        $tables = $this->model->showTables();

        if (!empty($tables) && in_array('old_alias', $tables)) {

            $old_alias = $this->model->get($this->table, [
                'fields' => ['alias'],
                'where' => [$this->columns['id_row'] => $id]
            ])[0]['alias'];

            if (!empty($old_alias) && $old_alias !== $_POST['alias']) {

                $this->model->delete('old_alias', [
                    'where' => ['alias' => $old_alias, 'table_name' => $this->table]
                ]);

                $this->model->delete('old_alias', [
                    'where' => ['alias' => $_POST['alias'], 'table_name' => $this->table]
                ]);

                $this->model->add('old_alias', [
                    'fields' => ['alias' => $old_alias, 'table_name' => $this->table, 'table_id' => $id]
                ]);
            }
        }
    }

    protected function createOrderData($table)
    {
        $columns = $this->model->showColumns($table);

        if (!isset($columns)) {
            throw new RouteException('Отсутствуют поля в таблице ' . $table);
        }

        $name = '';
        $order_name = '';

        if ($columns['name']) {
            $order_name = $name = 'name';
        } else {
            foreach ($columns as $key => $value) {
                if (strpos($key, 'name') !== false) {
                    $order_name = $key;
                    $name = $key . ' as name';
                }
            }

            if (!$name) $name = $columns['id_row'] . ' as name';
        }

        $parent_id = '';
        $order = [];

        if (!empty($columns['parent_id'])) $order[] = $parent_id = 'parent_id';

        if (!empty($columns['menu_position'])) {
            $order[] = 'menu_position';
        } else {
            $order[] = $order_name;
        }
        
        return compact('name', 'parent_id', 'order', 'columns');
    }

    protected function createManyToMany($settings = false)
    {
        if (!$settings) $settings = $this->settings ?: Settings::instance();

        $manyToMany = $settings::get('manyToMany');
        $blocks = $settings::get('blockNeedle');

        if (isset($manyToMany)) {
            foreach ($manyToMany as $mTable => $tables) {
                // ответ 1, 0 или false, если ничего не нашлось
                $targetKey = array_search($this->table, $tables);

                if ($targetKey !== false) {
                    // Ключ другой таблицы, с которой будем связываться
                    $otherKey = $targetKey ? 0 : 1;

                    // Нужно ли делать вывод данных в checkboxlist
                    $checkboxList = $settings::get('templateArr')['checkboxlist'];
                    
                    if (!$checkboxList || !in_array($tables[$otherKey], $checkboxList)) continue;

                    if (!isset($this->translate[$tables[$otherKey]])) {
                        if ($settings::get('projectTables')[$tables[$otherKey]]) {
                            $this->translate[$tables[$otherKey]] = [$settings::get('projectTables')[$tables[$otherKey]]['name']];
                        }
                    }

                    $orderData = $this->createOrderData($tables[$otherKey]);

                    $insert = false;

                    if ($blocks) {
                        foreach ($blocks as $key => $item) {
                            if (in_array($tables[$otherKey], $item)) {
                                $this->blocks[$key][] = $tables[$otherKey];
                                $insert = true;
                                break;
                            }
                        }
                    }
                    // В первую ячейку $this->blocks кладем название таблицы
                    if (!$insert) $this->blocks[array_keys($this->blocks)[0]][] = $tables[$otherKey];

                    $foreign = [];

                    // Работаем в режиме редактирования
                    if ($this->data) {
                        // Получаем данные связанные с другой таблицей
                        $res = $this->model->get($mTable, [
                            'fields' => [$tables[$otherKey] . '_' . $orderData['columns']['id_row']], // fields => ['goods . _id']
                            'where' => [$this->table . '_' . $this->columns['id_row'] => $this->data[$this->columns['id_row']]]
                        ]);

                        if ($res) {
                            // формируем массив $foreign
                            foreach ($res as $item) {
                                $foreign[] = $item[$tables[$otherKey] . '_' . $orderData['columns']['id_row']];
                            }
                        }
                    }

                    if (isset($tables['type']) && $tables['type']) {
                        $data = $this->model->get($tables[$otherKey], [
                            'fields' => [$orderData['columns']['id_row'] . ' as id', $orderData['name'], $orderData['parent_id']],
                            'order' => $orderData['order']
                        ]);

                        if ($data) {
                            $this->foreignData[$tables[$otherKey]][$tables[$otherKey]]['name'] = 'Выбрать';

                            foreach ($data as $item) {
                                // Если это родитель
                                if ($tables['type'] === 'root' && isset($orderData['parent_id'])) {
                                    if ($item[$orderData['parent_id']] === null) $this->foreignData[$tables[$otherKey]][$tables[$otherKey]]['sub'][] = $item;
                                
                                    // Если хотим увидеть только детей
                                } else if ($tables['type'] === 'child' && isset($orderData['parent_id'])) {
                                    if ($item[$orderData['parent_id']] !== null) $this->foreignData[$tables[$otherKey]][$tables[$otherKey]]['sub'][] = $item;
                                
                                    // Сваливаем всё, и родительские и дочерние категории
                                } else {
                                    $this->foreignData[$tables[$otherKey]][$tables[$otherKey]]['sub'][] = $item;
                                }
                                
                                if (in_array($item['id'], $foreign)) $this->data[$tables[$otherKey]][$tables[$otherKey]][] = $item['id'];
                            }
                        }
                    } elseif ($orderData['parent_id']) {
                        $parent = $tables[$otherKey];

                        $keys = $this->model->showForeignKeys($tables[$otherKey]);

                        if ($keys) {
                            foreach ($keys as $item) {
                                if ($item['COLUMN_NAME'] === 'parent_id') {
                                    $parent = $item['REFERENCED_TABLE_NAME'];
                                    break;
                                }
                            }
                        }

                        if ($parent === $tables[$otherKey]) {
                            $data = $this->model->get($tables[$otherKey], [
                                'fields' => [$orderData['columns']['id_row'] . ' as id', $orderData['name'], $orderData['parent_id']],
                                'order' => $orderData['order']
                            ]);

                            if ($data) {
                                // функция key() возвращает ключ массива. Пока $key не будет null
                                while(($key = key($data)) !== null) {
                                    if (!isset($data[$key]['parent_id'])) {
                                        $this->foreignData[$tables[$otherKey]][$data[$key]['id']]['name'] = $data[$key]['name'];
                                        unset($data[$key]);
                                        reset($data); // сбрасываем обход массива на начало.

                                        continue;
                                    } else {
                                        // Делаем множественное вложение
                                        if ($this->foreignData[$tables[$otherKey]][$data[$key][$orderData['parent_id']]]) {
                                            $this->foreignData[$tables[$otherKey]][$data[$key][$orderData['parent_id']]]['sub'][$data[$key]['id']] = $data[$key];

                                            if (in_array($data[$key]['id'], $foreign)) $this->data[$tables[$otherKey]][$data[$key][$orderData['parent_id']]][] = $data[$key]['id'];

                                            unset($data[$key]);
                                            reset($data);

                                            continue;

                                        } else {
                                            foreach ($this->foreignData[$tables[$otherKey]] as $id => $item) {
                                                $parent_id = $data[$key][$orderData['parent_id']];

                                                if (isset($item['sub']) && $item['sub'] && isset($item['sub'][$parent_id])) {
                                                    $this->foreignData[$tables[$otherKey]][$id]['sub'][$data[$key]['id']] = $data[$key];

                                                    if (in_array($data[$key]['id'], $foreign))
                                                        $this->data[$tables[$otherKey]][$id][] = $data[$key]['id'];

                                                    unset($data[$key]);
                                                    reset($data);

                                                    continue 2; // выйти из внутреннего цикла
                                                }
                                            }
                                        }

                                        next($data);
                                    }
                                }
                            }
                        } else {
                            $parentOrderData = $this->createOrderData($parent);

                            $data = $this->model->get($parent, [
                                'fields' => [$parentOrderData['name']],
                                'join' => [
                                    $tables[$otherKey] => [
                                        'fields' => [$orderData['columns']['id_row'] . ' as id', $orderData['name']],
                                        'on' => [$parentOrderData['columns']['id_row'], $orderData['parent_id']]
                                    ]
                                ],
                                'join_structure' => true
                            ]);

                            foreach ($data as $key => $item) {
                                if (isset($item['join'][$tables[$otherKey]]) && $item['join'][$tables[$otherKey]] && isset($item['name'])) {
                                    $this->foreignData[$tables[$otherKey]][$key]['name'] = $item['name'];
                                    $this->foreignData[$tables[$otherKey]][$key]['sub'] = $item['join'][$tables[$otherKey]];

                                    foreach ($item['join'][$tables[$otherKey]] as $value) {
                                        if (in_array($value['id'], $foreign)) $this->data[$tables[$otherKey]][$key][] = $value['id'];
                                    }
                                }
                            }
                        }

                    } else {
                        $data = $this->model->get($tables[$otherKey], [
                            'fields' => [$orderData['columns']['id_row'] . ' as id', $orderData['name'], $orderData['parent_id']],
                            'order' => $orderData['order']
                        ]);

                        if ($data) {
                            $this->foreignData[$tables[$otherKey]][$tables[$otherKey]]['name'] = 'Выбрать';

                            foreach ($data as $item) {
                                $this->foreignData[$tables[$otherKey]][$tables[$otherKey]]['sub'][] = $item;

                                if (in_array($item['id'], $foreign)) $this->data[$tables[$otherKey]][$tables[$otherKey]][] = $item['id'];
                            }
                        }
                    }

                }
            }

        }
    }

    protected function checkManyToMany($settings = false)
    {
        if (!$settings) $settings = $this->settings ?: Settings::instance();

        $manyToMany = $settings::get('manyToMany');

        if ($manyToMany) {
            foreach ($manyToMany as $mTable => $tables) {
                $targetKey = array_search($this->table, $tables);

                if ($targetKey !== false) {
                    $otherKey = $targetKey ? 0 : 1;
                    $checkboxlist = $settings::get('templateArr')['checkboxlist'];

                    if (!$checkboxlist || !in_array($tables[$otherKey], $checkboxlist)) continue;

                    $columns = $this->model->showColumns($tables[$otherKey]);
                    $targetRow = $this->table . '_' . $this->columns['id_row'];
                    $otherRow = $tables[$otherKey] . '_' . $columns['id_row'];

                    $this->model->delete($mTable, [
                        'where' => [$targetRow => $_POST[$this->columns['id_row']]]
                    ]);
                    // если что-то пришло в POST
                    if (isset($_POST[$tables[$otherKey]])) {
                        $insertArr = [];
                        $i = 0;

                        foreach ($_POST[$tables[$otherKey]] as $value) {
                            foreach ($value as $item) {
                                if ($item) {
                                    $insertArr[$i][$targetRow] = $_POST[$this->columns['id_row']];
                                    $insertArr[$i][$otherRow] = $item;
                                    $i++;
                                }
                            }
                        }

                        if ($insertArr) {
                            $this->model->add($mTable, [
                                'fields' => $insertArr
                            ]);
                        }
                    }

                }
            }
        }
    }

    protected function createForeignProperty($arr, $rootItems)
    {
        if (in_array($this->table, $rootItems['tables'])) {
            $this->foreignData[$arr['COLUMN_NAME']][0]['id'] = 'NULL';
            $this->foreignData[$arr['COLUMN_NAME']][0]['name'] = $rootItems['name'];
        }

        $where = [];
        $operand = '';

        $orderData = $this->createOrderData($arr['REFERENCED_TABLE_NAME']);

        if ($this->data) {
            if ($arr['REFERENCED_TABLE_NAME'] === $this->table) {
                // $this->columns['id_row'] => вернет значение. Например 'id'
                $where[$this->columns['id_row']] = $this->data[$this->columns['id_row']];
                $operand = '<>'; // <> => не равно
            }
        }
        
        $foreign = $this->model->get($arr['REFERENCED_TABLE_NAME'], [
            'fields' => [$arr['REFERENCED_COLUMN_NAME'] . ' as id', $orderData['name'], $orderData['parent_id']],
            'where' => $where,
            'operand' => $operand,
            'order' => $orderData['order']
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
        } else if (!empty($this->columns['parent_id'])) {
            $arr['COLUMN_NAME'] = 'parent_id';
            $arr['REFERENCED_COLUMN_NAME'] = $this->columns['id_row'];
            $arr['REFERENCED_TABLE_NAME'] = $this->table;

            $this->createForeignProperty($arr, $rootItems);
        }

        return;
    }

    protected function createMenuPosition($settings = false)
    {
        if (isset($this->columns['menu_position'])) {
            $where = [];

            if (!$settings) $settings = Settings::instance();

            $rootItems = $settings::get('rootItems');

            if (isset($this->columns['parent_id'])) {
                if (in_array($this->table, $rootItems['tables'])) {
                    $where = 'parent_id IS NULL OR parent_id = 0';
                } else {
                    $parent = $this->model->showForeignKeys($this->table, 'parent_id')[0];

                    if ($parent) {
                        if ($this->table === $parent['REFERENCED_TABLE_NAME']) {
                            $where = 'parent_id IS NULL OR parent_id = 0';
                        } else {
                            $columns = $this->model->showColumns($parent['REFERENCED_TABLE_NAME']);

                            if (isset($columns['parent_id'])) $order[] = 'parent_id';
                                else $order[] = $parent['REFERENCED_COLUMN_NAME'];

                            $id = $this->model->get($parent['REFERENCED_TABLE_NAME'], [
                                'fields' => [$parent['REFERENCED_COLUMN_NAME']],
                                'order' => $order,
                                'limit' => '1'
                            ])[0][$parent['REFERENCED_COLUMN_NAME']];
                            
                            if ($id) $where = ['parent_id' => $id];
                        }

                    } else {
                        $where = 'parent_id IS NULL OR parent_id = 0';
                    }
                }
            }

            $menu_pos = $this->model->get($this->table, [
                'fields' => ['COUNT(*) as count'],
                'where' => $where,
                'no_concat' => true
            ])[0]['count'] + (int) !$this->data;

            for ($i = 1; $i <= $menu_pos; $i++) {
                $this->foreignData['menu_position'][$i - 1]['id'] = $i;
                $this->foreignData['menu_position'][$i - 1]['name'] = $i;
            }
        }

        return;
    }

    protected function checkFiles($id)
    {
        if ($id) {
            $arrKeys = [];

            if (!empty($this->fileArray)) $arrKeys = array_keys($this->fileArray);

            if (!empty($_POST['js-sorting'])) $arrKeys = array_merge($arrKeys, array_keys($_POST['js-sorting']));

            if ($arrKeys) {
                $arrKeys = array_unique($arrKeys);

                $data = $this->model->get($this->table, [
                    'fields' => $arrKeys,
                    'where' => [$this->columns['id_row'] => $id]
                ]);
    
                if ($data) {
                    $data = $data[0];
    
                    foreach ($data as $key => $item) {
                        if ((!empty($this->fileArray[$key]) && is_array($this->fileArray[$key])) || !empty($_POST['js-sorting'][$key])) {
                            $fileArr = json_decode($item);
    
                            if ($fileArr) {
                                foreach ($fileArr as $file) {
                                    $this->fileArray[$key][] = $file;
                                }
                            }

                        } elseif (!empty($this->fileArray[$key])) {
                            @unlink($_SERVER['DOCUMENT_ROOT'] . PATH . UPLOAD_DIR . $item);
                        }
                    }
    
                }
            }
        }
        
    }

}
