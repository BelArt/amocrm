<?php
/**
 * Конфиг, с реквизитами доступа к стореджам, которые зависят от среды (продакшен, девелопмент и др.)
 */

return [
    'mongo'       => [
        'host'           => 'mongodb://127.0.0.1:27017',
        'db'             => 'mmcoexpo',
        'options'        => [
            'serverSelectionTryOnce' => false,
        ],
        'driver_options' => [],
    ],
    'redis'       => [
        'host' => 'localhost',
        'port' => '6379',
    ],
    'cache'       => [
        'lifetime' => 86400, // кеш на сутки по умолчанию
        'prefix'   => 'cache.',
    ],
    'debug'       => true,
    'mail'        => [
        'driver'     => 'smtp',
        'host'       => 'smtp.yandex.ru',
        'port'       => 465,
        'encryption' => 'ssl',
        'username'   => 'noreply@dev-mmco-expo.ru',
        'password'   => 'password',
        'from'       => [
            'email' => 'noreply@dev-mmco-expo.ru',
            'name'  => 'mmcoexpo',
        ],
    ],
    'queue'       => [
        'mailer' => 'emails',
    ],
    'domain'      => 'dev-mmco-expo.ru',
    'socket_host' => '0.0.0.0', // для работы сокетов
    'version'     => '0.0.1',
    'log_level'   => 9,
];
