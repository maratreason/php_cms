<?php

namespace core\base\settings;

class ShopSettings
{
    use BaseSettings;

    private $templateArr = [
        'text' => ['price', 'short'],
        'textarea' => ['goods_content']
    ];

    private $routes = [
        'plugins' => [
            'dir' => false,
            'routes' => []
        ],
    ];
}
