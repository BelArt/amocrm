<?php
/**
 * Создаём DI для командной строки
 */

return [
    // необходимая зависиомсть для mongo
    ['collectionManager', function () {
        return new \Phalcon\Mvc\Collection\Manager();
    }, true],

    ['mongo', function () {
        $mongo = new \Phalcon\Db\Adapter\MongoDB\Client(
            isset($this->get('config')['mongo']['host'])
                ? $this->get('config')['mongo']['host']
                : null,
            isset($this->get('config')['mongo']['options'])
                ? $this->get('config')['mongo']['options']->toArray()
                : [],
            isset($this->get('config')['mongo']['driver_options'])
                ? $this->get('config')['mongo']['driver_options']->toArray()
                : []
        );

        return $mongo->selectDatabase($this->get('config')['mongo']['db']);
    }, true],

    ['session', function () {
        return new \Phalcon\Session\Adapter\Files();
    }, true],

    ['less', function () {
        $less = new \lessc;
        return $less;
    }, true],

    ['cssmin', 'Phalcon\Assets\Filters\Cssmin', true],

    ['jsmin', 'Phalcon\Assets\Filters\Jsmin', true],

    ['queue', function () {
        return new \Phalcon\Queue\Beanstalk();
    }],

    ['closure', function () {
        return new \App\Common\Library\ClosureQueue\ClosureQueue(
            $this->get('log'),
            $this->get('queue')
        );
    }, true],

    ['log', function ($amoDomain = 'mmcoexpo') {
        $config = $this->get('config');
        $app = $this->get('app');
        $folder = 'mmcoexpo';

        // у каждого пользователя своя папка для логов
        $path = $config['log'].'/'.$folder;
        @mkdir($path, 0775, true);

        $logger = new \App\Common\Phalcon\Logger\Adapter\FileWithBacktrace($path.'/'.$app.'.log');

        // установим уровень логирования, если в конфиге есть про это
        if (isset($config['log_level']) && in_array($config['log_level'], [0, 1, 2, 3, 4, 5, 6, 7, 8, 9])) {
            $logger->setLogLevel($config['log_level']);
        }

        $logFormater = new \App\Common\Phalcon\Logger\Formatter\LogFormatter(
            '[%date%][%type%][mmcoexpo.' . $app . ']' . ($amoDomain ? '[' . $amoDomain . ']' : '') . ' %message%'
        );
        $logFormater->setDateFormat('Y-m-d H:i:s.u');

        $logger->setFormatter($logFormater);

        return $logger;
    }, true],
];
