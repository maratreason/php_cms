<?php

namespace core\base\controller;

trait Singleton
{
    private static $_instance;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function instance()
    {
        if (self::$_instance instanceof self) {
            return self::$_instance;
        }

        self::$_instance = new self;

        if (method_exists(self::$_instance, 'connect')) {
            self::$_instance->connect();
        }

        return self::$_instance;
    }
}
