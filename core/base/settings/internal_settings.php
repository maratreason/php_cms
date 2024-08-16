<?php

defined('APP_ACCESS') or die('Access Denied');

// разрешаем работать с браузером microsoft = true/false.
const MS_MODE = false;

const TEMPLATE = 'templates/default/';
const ADMIN_TEMPLATE = 'core/admin/view/';
const UPLOAD_DIR = 'userfiles/';

const COOKIE_VERSION = '1.0.0';
const CRYPT_KEY = 's5v8y/A?D(G+KbPeXn2r5u8x/A%D*G-KfTjWnZr4u7x!A%C*MbQeThWmZq4t7w!z(G+KbPeShVmYq3t6A%D*G-KaPdSgVkYp7x!z%C*F-JaNdRgUq4t7w9z$C&F)J@Nc';
const COOKIE_TIME = 60;
const BLOCK_TIME = 3;

const QTY = 8;
const QTY_LINKS = 3;

const ADMIN_CSS_JS = [
    'styles' => ['css/main.css'],
    'scripts' => ['js/framework-functions.js', 'js/scripts.js']
];

const USER_CSS_JS = [
    'styles' => [],
    'scripts' => []
];

use core\base\exceptions\RouteException;

function autoloadMainClasses($className)
{
    $className = str_replace('\\', '/', $className);

    if (!@include_once $className . '.php') {
        throw new RouteException('Не верное имя файла для подключения - ' . $className);
    }
}

spl_autoload_register('autoloadMainClasses');
