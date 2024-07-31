<?php

defined('APP_ACCESS') or die('Access Denied');

const TEMPLATE = 'templates/default/';
const ADMIN_TEMPLATE = 'core/admin/view/';
const UPLOAD_DIR = 'userfiles/';

const COOKIE_VERSION = '1.0.0';
const COOKIE_TIME = 60;
const BLOCK_TIME = 3;
const CRYPT_KEY = '';

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
