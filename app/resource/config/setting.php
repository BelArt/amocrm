<?php
/**
 * Конфиг, что размещаем в dependency injector
 */

return [
    'daemon'    => ROOT_PATH . '/daemon',
    'temp'      => ROOT_PATH . '/temp',
    'data'      => ROOT_PATH . '/data',
    'log'       => ROOT_PATH . '/log',
    // https://github.com/phalcon/cphalcon/blob/master/phalcon/logger.zep
    'log_level' => 9,
    'debug'     => false,
    'asset'     => [
        'less' => [
            [
                //'input' => VENDOR_PATH.'/twbs/bootstrap/less/bootstrap.less',
                'input'  => APP_PATH . '/resource/less/bootstrap.less',
                'output' => ROOT_PATH . '/public/gui/css/bootstrap.css',
            ],
            [
                'input'  => APP_PATH . '/resource/less/panel-quality.less',
                'output' => ROOT_PATH . '/public/gui/css/panel-quality.css',
            ],
        ],
        'css'  => [],
        'js'   => [
            [
                'input'  => [
                    VENDOR_PATH . '/jquery/jquery/jquery-1.11.2.js',

                    VENDOR_PATH . '/twbs/bootstrap/js/transition.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/alert.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/button.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/carousel.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/collapse.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/dropdown.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/modal.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/tooltip.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/popover.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/scrollspy.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/tab.js',
                    VENDOR_PATH . '/twbs/bootstrap/js/affix.js',

                    APP_PATH . '/resource/js/dropdown-submenu.js',
                ],
                'output' => ROOT_PATH . '/public/gui/js/bootstrap.js',
            ],
        ],
        'font' => [
            'input'  => VENDOR_PATH . '/twbs/bootstrap/fonts/',
            'output' => ROOT_PATH . '/public/gui/fonts/',
        ],
    ],
];
