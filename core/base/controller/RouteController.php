<?php

namespace core\base\controller;

use core\base\exceptions\RouteException;
use core\base\settings\Settings;

class RouteController extends BaseController
{
    use Singleton;

    protected $routes;

    private function __construct()
    {
        $address_str = $_SERVER['REQUEST_URI'];

        if ($_SERVER['QUERY_STRING']) {
            $address_str = substr($address_str, 0, strpos($address_str, $_SERVER['QUERY_STRING']) - 1);
        }

        $path = substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], 'index.php'));

        if ($path === PATH) {

            if (strrpos($address_str, '/') === strlen($address_str) - 1 && strrpos($address_str, '/') !== strlen(PATH) - 1) {
                $this->redirect(rtrim($address_str, '/'), 301);
            }

            $this->routes = Settings::get('routes');

            if (!$this->routes) throw new RouteException('Отсутствуют маршруты в базовых настройках', 1);
            // Обрезаем $address_str с первого символа. Чтобы im.my не попал в $url;
            $url = explode('/', substr($address_str, strlen(PATH)));
            // админка
            // Если $url[0] равно алиасу, ни буквой больше, ни буквой меньше
            if ($url[0] && $url[0] === $this->routes['admin']['alias']) {
                array_shift($url);

                // Плагин
                if (isset($url[0]) && is_dir($_SERVER['DOCUMENT_ROOT'] . PATH . $this->routes['plugins']['path'] . $url[0])) {
                    // Выкидываем название плагина из массива $url.
                    $plugin = array_shift($url);
                    // формируем путь к файлу настроек плагина
                    $pluginSettings = $this->routes['settings']['path'] . ucfirst($plugin . 'Settings');
                    // если существует такой файл. Передаем полный путь к файлу
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . PATH . $pluginSettings . '.php')) {
                        // заменяем слэши на обратные слэши
                        $pluginSettings = str_replace('/', '\\', $pluginSettings);
                        $this->routes = $pluginSettings::get('routes');
                    }
                    // если в директории, то добавляем слэши
                    $dir = $this->routes['plugins']['dir'] ? '/' . $this->routes['plugins']['dir'] . '/' : '/';
                    $dir = str_replace('//', '/', $dir);
                    $this->controller = $this->routes['plugins']['path'] . $plugin . $dir;
                    $hrUrl = $this->routes['plugins']['hrUrl'];
                    $route = 'plugins';
                } else {
                    $this->controller = $this->routes['admin']['path'];
                    $hrUrl = $this->routes['admin']['hrUrl'];
                    $route = 'admin';
                }

                // пользователь
            } else {
                $hrUrl = $this->routes['user']['hrUrl'];
                $this->controller = $this->routes['user']['path'];
                $route = 'user';
            }

            $this->createRoute($route, $url);

            /**
             * Продебажить этот код.
             * В поисковой строке передать параметры:
             * im.my/news/color/red/id/4/text/good
             * im.my/news/title-news/last
             * посмотреть каждую переменную, что в нее записывается.
             */
            if (!empty($url[1])) {

                $count = count($url);
                $key = '';

                if (!$hrUrl) {
                    $i = 1;
                } else {
                    $this->parameters['alias'] = $url[1];
                    $i = 2;
                }

                for (; $i < $count; $i++) {

                    if (!$key) {

                        $key = $url[$i];
                        // параметры это адресная строка
                        $this->parameters[$key] = '';
                    } else {

                        $this->parameters[$key] = $url[$i];

                        $key = '';
                    }
                }
            }
        } else {

            throw new RouteException('Некорректная директория сайта', 1);
        }
    }

    private function createRoute($var, $arr)
    {
        $route = [];

        if (!empty($arr[0])) {

            if (!empty($this->routes[$var]['routes'][$arr[0]])) {

                $route = explode('/', $this->routes[$var]['routes'][$arr[0]]);

                $this->controller .= ucfirst($route[0] . 'Controller');
            } else {

                $this->controller .= ucfirst($arr[0] . 'Controller');
            }
        } else {

            $this->controller .= $this->routes['default']['controller'];
        }

        $this->inputMethod = $route[1] ?? $this->routes['default']['inputMethod'];

        $this->outputMethod = $route[2] ?? $this->routes['default']['outputMethod'];

        return;
    }
}
