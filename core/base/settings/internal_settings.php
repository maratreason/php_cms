<?php

defined('APP_ACCESS') or die('Access Denied');

// разрешаем работать с браузером microsoft = true/false.
const MS_MODE = false;

const TEMPLATE = 'templates/default/';
const ADMIN_TEMPLATE = 'core/admin/view/';
const UPLOAD_DIR = 'userfiles/';
const DEFAULT_IMAGE_DIRECTORY = 'default_images';

const COOKIE_VERSION = '1.0.0';
const CRYPT_KEY = 's5v8y/A?D(G+KbPeXn2r5u8x/A%D*G-KfTjWnZr4u7x!A%C*MbQeThWmZq4t7w!z(G+KbPeShVmYq3t6A%D*G-KaPdSgVkYp7x!z%C*F-JaNdRgUq4t7w9z$C&F)J@Nc';
const COOKIE_TIME = 120;
const BLOCK_TIME = 3;

const END_SLASH = '/';
const QTY = 8;
const QTY_LINKS = 3;

const ADMIN_CSS_JS = [
    'styles' => ['css/main.css'],
    'scripts' => [
        'js/framework-functions.js',
        'js/scripts.js',
        'js/tinymce/tinymce.min.js',
        'js/tinymce/tinymce_init.js',
    ]
];

const USER_CSS_JS = [
    'styles' => [
        'https://fonts.googleapis.com/css?family=Ubuntu:300,400,500,700&display=swap&subset=cyrillic',
        'https://fonts.googleapis.com/css?family=Didact+Gothic&display=swap&subset=cyrillic',
        'https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css',
        'https://unpkg.com/swiper/swiper-bundle.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css',
        'assets/css/animate.css',
        'assets/css/style.css',
    ],
    'scripts' => [
        'https://code.jquery.com/jquery-3.4.1.min.js',
        'https://unpkg.com/swiper/swiper-bundle.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.2.5/gsap.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.0.2/gsap.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/gsap/2.1.3/TweenMax.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.7/ScrollMagic.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.7/plugins/animation.gsap.min.js',
        'https://cdnjs.cloudflare.com/ajax/libs/ScrollMagic/2.0.7/plugins/debug.addIndicators.min.js',
        'assets/js/jquery.maskedinput.min.js',
        'assets/js/TweenMax.min.js',
        'assets/js/ScrollMagic.min.js',
        'assets/js/animation.gsap.min.js',
        'assets/js/bodyscrolllock/bodyScrollLock.min.js',
        'assets/js/app.js',
        'assets/js/script.js',
        'assets/js/freeHost.js',
    ]
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
