<?php

/**
 * точка входа для интерфейса пользователя или на страницу настроек виджета
 * если переход на страницу настроек виджета, то в GET-параметре widget
 * должно передоваться имя виджета, к странице настроек которому проводится авторизация
 */

umask(0002); // This will let the permissions be 0775

define('ROOT_PATH', __DIR__.'/../..');
define('APP_PATH', ROOT_PATH.'/app');
define('VENDOR_PATH', ROOT_PATH.'/vendor');
// подключаем зависимости самого ядра
require_once VENDOR_PATH . '/autoload.php';

try {
    require_once APP_PATH.'/common/Bootstrap/Bootstrap.php';
    require_once APP_PATH.'/common/Bootstrap/Environment.php';
    require_once APP_PATH.'/common/Bootstrap/Application.php';
    $app = new \App\Common\Bootstrap\Bootstrap(
        new \Phalcon\Di\FactoryDefault(),
        \App\Common\Bootstrap\Application::gui(),
        \App\Common\Bootstrap\Environment::fromFile(ROOT_PATH.'/.env')
    );

    if ($app->getDi()->get('auth')->hasUser()) {
        $app->bootUser();
    }

    $application = new \Phalcon\Mvc\Application($app->getDi());
    $application->useImplicitView(true);

    $application->handle()->send();
} catch (\Error $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->has('env') && $app->getDi()->get('env') == 'prod') {
        $app->getDi()->get('log')->error($app->getExceptionText($e));

        (new \Phalcon\Http\Response())
            ->setStatusCode(500, 'Server Error')
            ->setContent('На сервере произошла ошибка (см. логи)')
            ->send();
    } else {
        (new \Phalcon\Http\Response())
            ->setStatusCode(500, 'Server Error')
            ->setContent($e->getMessage())
            ->send();
    }
} catch(\Exception $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->get('config')->debug) {
        throw $e;
    }

    $app->getDi()->get('log')->error($app->getExceptionText($e));

    echo 'Ошибка на сервере';
}
