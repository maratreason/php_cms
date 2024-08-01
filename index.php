<?php

use core\base\controller\BaseRoute;
use core\base\exceptions\RouteException;
use core\base\exceptions\DbException;

define('APP_ACCESS', true);

header('Content-Type:text/html;charset=utf-8');
session_start();

// Перед сдачей проекта заказчику отключить вывод warnings.
// error_reporting(0);

require_once 'config.php';
require_once 'core/base/settings/internal_settings.php';
require_once 'libraries/functions.php';

if ($_POST) {
    exit("Ajax");
}

try {
    BaseRoute::routeDirection();
} catch (RouteException $e) {
    exit($e->getMessage());
} catch (DbException $e) {
    exit($e->getMessage());
}
