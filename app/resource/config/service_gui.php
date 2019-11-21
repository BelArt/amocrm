<?php
/**
 * Создаём DI для общей авторизации в gui
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
                : null
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

    ['session', function () {
        $session = new \Phalcon\Session\Adapter\Files();
        $session->start();
        return $session;
    }, true],

    ['view', function () {
        $config = $this->get('config');

        $view = new \Phalcon\Mvc\View();

        // выставляем папки с вьюхами общие или пользоватея в контроллерах

        $view->registerEngines(array(
            ".volt" => function ($view, $di) use ($config) {
                $volt = new \App\Common\Library\VoltEngine\VoltEngine($view, $di);

                $volt->setOptions(array(
                    //'compiledPath' => $config['temp'].'/cache/volt/',
                    'compiledPath' => function ($templatePath) use ($config) {
                        $dirName = explode('/../../', dirname($templatePath));
                        $dirCache = $config['temp'].'/cache/volt/'.(isset($dirName[1])?$dirName[1]:'');
                        if (!is_dir($dirCache)) {
                            mkdir($dirCache, 0777, true);
                        }
                        return $dirCache . '/'. basename($templatePath) . '.php';
                    },
                    'compileAlways' => true,
                ));

                return $volt;
            }
        ));
        return $view;
    }, true],

    // Регистрируем компонент сообщений с CSS классами
    ['flash', function () {
        $flash = new \Phalcon\Flash\Direct(array(
            'error' => 'alert alert-danger',
            'success' => 'alert alert-success',
            'notice' => 'alert alert-info',
            'warning' => 'alert alert-warning',
        ));
        return $flash;
    }],

    ['dispatcher', function () {
        $eventsManager = new \Phalcon\Events\Manager();

        $eventsManager->attach('dispatch', function ($event, $dispatcher) {
            if ($event->getType() == 'beforeDispatchLoop') {
                // $dispatcher->getActionName() will be NULL and a warning issued on camelize()
                if ($actionName = $dispatcher->getActionName()) {
                    $dispatcher->setActionName(\Phalcon\Text::camelize($actionName));
                }
            }
        });

        $eventsManager->attach('dispatch:beforeException', new \App\Common\Plugin\NotFoundPlugin());

        $dispatcher = new \Phalcon\Mvc\Dispatcher();
        //Прикрепляем менеджер событий к диспетчеру
        $dispatcher->setEventsManager($eventsManager);

        return $dispatcher;
    }, true],

    ['router', function () {
        $auth = $this->get('auth');

        $router = new \Phalcon\Mvc\Router();

        if ($auth->hasUser()) {
            // если запрос от виджета, то уходим в другой namespace
            if ($auth->getWidget()) {
                $router->add('/', [
                    'controller' => 'index',
                    'action' => 'index',
                    'namespace' => 'App\Widget\Gui\Controller',
                ]);

                $router->add('/:controller/:action/:params', [
                    'controller' => 1,
                    'action' => 2,
                    'params' => 3,
                    'namespace' => 'App\Widget\Gui\Controller',
                ]);
            } else {
                $router->add('/', [
                    'controller' => 'index',
                    'action' => 'index',
                    'namespace' => 'App\User\Gui\Controller',
                ]);

                $router->add('/:controller/:action/:params', [
                    'controller' => 1,
                    'action' => 2,
                    'params' => 3,
                    'namespace' => 'App\User\Gui\Controller',
                ]);
            }

            $router->add('/auth/logout', [
                'controller' => 'auth',
                'action' => 'logout',
                'namespace' => 'App\Common\Controller',
            ]);

            $router->add('/auth/changepassword', [
                'controller' => 'auth',
                'action' => 'changepassword',
                'namespace' => 'App\Common\Controller',
            ]);
        } else {
            $router->add('/', [
                'controller' => 'index',
                'action' => 'index',
                'namespace' => 'App\Common\Controller',
            ]);

            $router->add('/:controller/:action', [
                'controller' => 1,
                'action' => 2,
                'namespace' => 'App\Common\Controller',
            ]);

            $router->add('/auth/resetpassword/:params', [
                'controller' => 'auth',
                'action' => 'resetpassword',
                'params' => 1,
                'namespace' => 'App\Common\Controller',
            ]);

            $router->add('/auth/forgot', [
                'controller' => 'auth',
                'action' => 'forgot',
                'namespace' => 'App\Common\Controller',
            ]);
        }

        return $router;
    }],

    ['url', function () {
        $config = $this->get('config');
        $url = new Phalcon\Mvc\Url();
        $url->setBaseUri('http://pannel.'.$config['domain']);
        return $url;
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

    ['queue', function () {
        return new Phalcon\Queue\Beanstalk();
    }],
];
