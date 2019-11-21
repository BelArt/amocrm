<?php
/**
 * размещаем в di необходимые сервисы
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

    ['cache', function () {
        $config = $this->get('config');

        $redis = new \Redis();
        $redis->connect($config->redis->host, $config->redis->port);

        $frontend = new \Phalcon\Cache\Frontend\Data(array(
            'lifetime' => $config->cache->lifetime // сутки
        ));

        $cache = new \Phalcon\Cache\Backend\Redis($frontend, array(
            'redis' => $redis,
            'prefix' => $config->cache->prefix
        ));

        return $cache;
    }, true],

    // произведём валидацию запроса (метод и принимаемые данные) на основе аннотаций
    ['dispatcher', function () {
        $eventsManager = new \Phalcon\Events\Manager();

        $eventsManager->attach('dispatch', function ($event, $dispatcher) {
            if ($event->getType() == 'beforeDispatchLoop') {
                // $dispatcher->getActionName() will be NULL and a warning issued on camelize()
                $dispatcher->setActionName(\Phalcon\Text::camelize($dispatcher->getActionName()));
            }
        });

        //Привязать плагин к событию 'dispatch'
        $eventsManager->attach('dispatch', new \App\Common\Plugin\MethodAnnotationPlugin());
        $eventsManager->attach('dispatch', new \App\Common\Plugin\ParamsAnnotationPlugin());

        $dispatcher = new \Phalcon\Mvc\Dispatcher();
        $dispatcher->setEventsManager($eventsManager);
        return $dispatcher;
    }, true],

    ['queue', function () {
        return new Phalcon\Queue\Beanstalk();
    }],

    ['closure', function () {
        return new \App\Common\Library\ClosureQueue\ClosureQueue(
            $this->get('log'),
            $this->get('queue'),
            $this->get('user')
        );
    }, true],

    ['log', function () {
        $config = $this->get('config');
        $user = $this->get('auth')->getUser();
        $app = $this->get('app');

        $folder = $user?$user->name:'_undef';
        // у каждого пользователя своя папка для логов
        $path = $config['log'].'/'.$folder;
        @mkdir($path, 0775, true);

        $logger = new \App\Common\Phalcon\Logger\Adapter\FileWithBacktrace($path.'/'.$app.'.log');

        // установим уровень логирования, если в конфиге есть про это
        if (isset($config['log_level']) && in_array($config['log_level'], [0, 1, 2, 3, 4, 5, 6, 7, 8, 9])) {
            $logger->setLogLevel($config['log_level']);
        }

        $logFormater = new \App\Common\Phalcon\Logger\Formatter\LogFormatter(
            '[%date%][%type%][mmcoexpo.' . $app . '][' . $folder . '] %message%'
        );
        $logFormater->setDateFormat('Y-m-d H:i:s.u');

        $logger->setFormatter($logFormater);

        return $logger;
    }, true],


    ['url', function () {
        $url = new \Phalcon\Mvc\Url();
        $url->setBaseUri('https://api.' . $this->get('config')['domain']);

        return $url;
    }, true],

    ['response', function () {
        return new App\Common\Phalcon\Response();
    }, true],
];
