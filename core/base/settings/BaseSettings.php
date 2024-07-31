<?php

namespace core\base\settings;

use core\base\controller\Singleton;

trait BaseSettings
{
    use Singleton {
        instance as SingletonInstance;
    }

    private $baseSettings;

    private static function instance()
    {
        if (self::$_instance instanceof self) {
            return self::$_instance;
        }

        self::SingletonInstance()->baseSettings = Settings::instance();
        // в clueProperties() передаем как параметр имя текущего класса. В данном случае это ShopSettings
        $baseProperties = self::$_instance->baseSettings->clueProperties(get_class());
        self::$_instance->setProperty($baseProperties);

        return self::$_instance;
    }

    public static function get($property)
    {
        return self::instance()->$property;
    }

    protected function setProperty($properties)
    {
        if ($properties) {
            foreach ($properties as $name => $property) {
                $this->$name = $property;
            }
        }
    }
}
