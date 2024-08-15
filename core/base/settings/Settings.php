<?php

namespace core\base\settings;

use core\base\controller\Singleton;

class Settings {
    use Singleton;

    private $routes = [
        'admin' => [
            'alias' => 'admin',
            'path' => 'core/admin/controller/',
            'hrUrl' => false,
            'routes' => []
        ],
        'settings' => [
            'path' => 'core/base/settings/'
        ],
        'plugins' => [
            'path' => 'core/plugins/',
            'hrUrl' => false,
            'dir' => false,
        ],
        'user' => [
            'path' => 'core/user/controller/',
            'hrUrl' => true,
            'routes' => [
                'catalog' => 'site/input/output'
            ]
        ],
        'default' => [
            'controller' => 'IndexController',
            'inputMethod' => 'inputData',
            'outputMethod' => 'outputData'
        ]
    ];

    private $expansion = 'core/admin/expansion/';

    private $messages = 'core/base/messages/';

    private $defaultTable = 'goods';
    
    private $formTemplates = PATH . 'core/admin/view/include/form_templates/';

    private $projectTables = [
        'goods' => ['name' => 'Товары', 'img' => 'pages.png'],
        'filters' => ['name' => 'Фильтры'],
    ];

    private $templateArr = [
        'text' => ['name'],
        'textarea' => ['content', 'keywords'],
        'radio' => ['visible'],
        'checkboxlist' => ['filters'],
        'select' => ['menu_position', 'parent_id'],
        'img' => ['img'],
        'gallery_img' => ['gallery_img'],
    ];

    private $fileTemplates = ['img', 'gallery_img'];

    private $translate = [
        'name' => ['Название', 'Не более 100 символов'],
        'content' => ['Контент', 'Не более 100 символов'],
        'keywords' => ['Ключевые слова', 'Не более 70 символов'],
        'img' => ['Картинка', ''],
        'gallery_img' => ['Галерея картинок', ''],
    ];

    private $radio = [
        'visible' => ['Нет', 'Да', 'default' => 'Да']
    ];

    private $rootItems = [
        'name' => 'Корневая',
        'tables' => ['articles', 'goods', 'filters']
    ];

    private $manyToMany = [
        // таблица, показывать только родительский раздел parent или childs
        // 'goods_filters' => ['goods', 'filters', 'type' => 'root'], // 'type' => 'child' || 'root' || 'all'
        'goods_filters' => ['goods', 'filters'], 
    ];

    private $blockNeedle = [
        'vg-rows' => [],
        'vg-img' => ['img'],
        'vg-content' => ['content']
    ];

    private $validation = [
        'name' => ['empty' => true, 'trim' => true],
        'price' => ['int' => true],
        'login' => ['empty' => true, 'trim' => true],
        'password' => ['crypt' => true, 'empty' => true],
        'keywords' => ['count' => 70, 'trim' => true],
        'description' => ['count' => 160, 'trim' => true],
    ];

    public static function get($property)
    {
        return self::instance()->$property;
    }

    public function clueProperties($class)
    {
        $baseProperties = [];
        // пробегаемся в цикле по свойсту и берем имя свойста $name и значение свойства $item.
        foreach ($this as $name => $item) {
            // Сохраняем в переменную $property имя свойства класса. $class::get($name) => это будет так: ShopSettings::get('templateArr').
            $property = $class::get($name);
            
            if (is_array($property) && is_array($item)) {
                // 'templateArr' = $this->$name - это templateArr, $item - это значение: 'text', 'textarea'
                $baseProperties[$name] = $this->arrayMergeRecursive($this->$name, $property);
                continue;
            }

            if (!$property) {
                $baseProperties[$name] = $this->$name;
            }
        }

        return $baseProperties;
    }

    public function arrayMergeRecursive()
    {
        // аргументы в функцию передавать не нужно. Получим их через func_get_args().
        $arrays = func_get_args();
        // записываем в $base первый элемент массива и удаляем его из $arrays.
        $base = array_shift($arrays);
        // Теперь перебираем и забираем оставшиеся массивы
        foreach ($arrays as $array) {
            // Теперь перебираем сам массив. Т.е. например ShopSettings->templateArr.
            foreach ($array as $key => $value) {
                // Если свойства $value и $base[$key], т.е. если они одинаковые, например routes, templateArr есть и там и там.
                if (is_array($value) && is_array($base[$key])) {
                    // То снова перебираем уже сами эти свойства как ключ и значение
                    $base[$key] = $this->arrayMergeRecursive($base[$key], $value);
                } else {
                    // Если это нумерованные массивы
                    if (is_int($key)) {
                        // если не существует такое значение в массиве, например 'name', 'content' а во втором нет этого
                        if (!in_array($value, $base)) array_push($base, $value);
                        // то переходим на следующую итерацию цикла
                        continue;
                    }
                    // Например 'text' и во втором массиве есть 'text', то ключ перезаписывается, а значения добавляются.
                    // Иначе перезаписываем 'text' => ['name', 'phone', 'address'] ====> 'text' => ['name', 'phone', 'address', 'price', 'short']
                    $base[$key] = $value;
                }
            }

        }

        return $base;
    }
}
