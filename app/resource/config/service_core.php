<?php
/**
 * Создаём DI для командной строки
 */

return [
    ['collectionManager', function(){
        return new \Phalcon\Mvc\Collection\Manager();
    }, true],

    ['mongo', function() {
        $mongo = new \Phalcon\Db\Adapter\MongoDB\Client(
            isset($this->get('config')['mongo']['host'])
                ? $this->get('config')['mongo']['host']
                : null
        );

        return $mongo->selectDatabase($this->get('config')['mongo']['db']);
    }, true],

    ['dispatcher', function() {
        $eventsManager = new \Phalcon\Events\Manager();

        $eventsManager->attach('dispatch', function($event, $dispatcher) {
            if ($event->getType() == 'beforeDispatchLoop') {
                // $dispatcher->getActionName() will be NULL and a warning issued on camelize()
                $dispatcher->setActionName(\Phalcon\Text::camelize($dispatcher->getActionName()));
            }
        });

        $dispatcher = new \Phalcon\Mvc\Dispatcher();
        //Прикрепляем менеджер событий к диспетчеру
        $dispatcher->setEventsManager($eventsManager);
        return $dispatcher;
    }, true],

    ['amo', function() {
        $config = $this->get('config');

        return new \App\Common\Library\Amo\Amo(
            new \App\Common\Library\Amo\AmoRestApi(
                $config['amo_api']['domain'],
                $config['amo_api']['email'],
                $config['amo_api']['hash']
            )
        );
    }, true],

    ['session', function() {
        $session = new \Phalcon\Session\Adapter\Files();
        $session->start();
        return $session;
    }, true],

    ['view', function() {
        $config = $this->get('config');

        $view = new \Phalcon\Mvc\View();

        // выставляем папки с вьюхами общие или пользоватея в контроллерах

        $view->registerEngines(array(
            ".volt" => function($view, $di) use ($config) {
                $volt = new \App\Common\Library\VoltEngine\VoltEngine($view, $di);

                $volt->setOptions(array(
                    //'compiledPath' => $config['temp'].'/cache/volt/',
                    'compiledPath' => function($templatePath) use ($config) {
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
];
