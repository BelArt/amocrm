<?php

/**
 * точка входа для работы с пользовательским API или API виджета,
 * в зависимости чьё API вызывается отличается способ вызова:
 *
 * * /<username>/<name_of_controller>/<name_of_action>/<params>?hash=<hash> – api пользователя
 * * /<username>/<name_of_controller>/<name_of_action>/<params>?widget=<widget_name> – api виджета с авторизацией
 * пользователя
 *
 * * <username> - имя (логин) заказчика для авторизации (совпадает с его директорией)
 * * <name_of_controller> - название контроллера
 * * <name_of_action> - название экшена
 * * <params> - параметры используемые в экшене
 * * <hash> - хеш для авторизации при обращении по api
 * * <widget_name> - уникальное название виджета
 *
 * все вызовы идут через поддомен core.mmco-expo.ru через протокол https
 *
 * ответ от сервера всегда отдается в формате json и имеет следующую структуру (но есть исключения):
 *
 * * success — принимает значения true для успешного запроса и false в случае ошибки;
 * * data — результат выполнения запроса, в случае ошибки будет иметь значение null;
 * * error — краткое описание ошибки, помешавшей выполнить запрос, в случае успешного запроса будет иметь значение
 * null.
 */

umask(0002); // This will let the permissions be 0775

define('ROOT_PATH', __DIR__ . '/../..');
define('APP_PATH', ROOT_PATH . '/app');
define('VENDOR_PATH', ROOT_PATH . '/vendor');
// подключаем зависимости самого ядра
require_once VENDOR_PATH . '/autoload.php';

use App\Common\Bootstrap\Application;
use App\Common\Bootstrap\Bootstrap;
use App\Common\Bootstrap\Environment;
use \App\Common\Exception\UserNotExistsException;
use App\Common\Library\Amo\AmoException;
use Phalcon\Di\FactoryDefault;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Dispatcher\Exception as PhalconDispatchException;

try {
    require_once APP_PATH . '/common/Bootstrap/Bootstrap.php';
    require_once APP_PATH . '/common/Bootstrap/Environment.php';
    require_once APP_PATH . '/common/Bootstrap/Application.php';
    $app = new Bootstrap(
        new FactoryDefault(),
        Application::api(),
        Environment::fromFile(ROOT_PATH . '/.env')
    );

    // получим предположительный логин пользователя между первым и вторым слешами
    // но перед этим отрежем query-строку если она есть
    $requestUri = explode('?', $_SERVER["REQUEST_URI"]);
    $requestUri = explode('/', $requestUri[0]);

    if (count($requestUri) < 2) {
        throw new UserNotExistsException('Authentication credentials provided were invalid', 401);
    }

    $auth = $app->getDi()->get('auth');
    $name = $requestUri[1];

    if (isset($_GET['widget'])) {
        // Устаревшая обработка работы с общими виджетами
        (new Response())
            ->setStatusCode(404, 'Not Found')
            ->send();

        exit();
    } else {
        // есть возможность авторизации без хеша, если в аккаунте пользователя это указано
        if (isset($_GET['hash'])) {
            if (!$auth->checkByHash($name, $_GET['hash'])) {
                throw new UserNotExistsException(
                    'This user was not found. Maybe you incorrectly stated login? ' . $name,
                    418
                );
            }
        } else {
            // модель авторизации когда hash указан через слеш последним параметром
            if (count($requestUri) >= 5) {
                if (!$auth->checkByHash($name, $requestUri[4])) {
                    throw new UserNotExistsException(
                        'This user was not found. Maybe you incorrectly stated login? ' . $name,
                        418
                    );
                }
            } else {
                throw new UserNotExistsException(
                    'This user was not found or not access. ' . $_SERVER["REQUEST_URI"],
                    418
                );
            }
        }

        if (!$app->bootUser()) {
            (new Response())
                ->setStatusCode(404, 'Not Found')
                ->send();

            exit();
        }
    }

    $router = $app->getDi()->get('router');
    $router->add(
        '/{user}/:controller/:action/:params',
        [
            'user'       => 1,
            'controller' => 2,
            'action'     => 3,
            'params'     => 4,
        ]
    );
    $router->handle();

    $dispatcher = $app->getDi()->get('dispatcher');
    $dispatcher->setDefaultNamespace('\\App\\User\\Api\\Controller\\');
    $dispatcher->setControllerName($router->getControllerName());
    $dispatcher->setActionName($router->getActionName());
    $dispatcher->setParams($router->getParams());
    $dispatcher->dispatch();

    /** @var Response $response */
    $response = $dispatcher->getReturnedValue();
    if ($response instanceof ResponseInterface && !$response->isSent()) {
        $response
            ->setContentType('application/json')
            ->send();
    }
} catch (AmoException $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->has('env') && $app->getDi()->get('env') == 'prod') {
        $app->getDi()->get('log')->error($app->getExceptionText($e));
    }

    (new Response())
        ->setContentType('application/json')
        ->setStatusCode(200, 'Server Error')
        ->setJsonContent(
            [
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ]
        )
        ->send();
} catch (UserNotExistsException $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->has('env') && $app->getDi()->get('env') == 'prod') {
        $app->getDi()->get('log')->error($app->getExceptionText($e));
    }

    (new Response())
        ->setContentType('application/json')
        ->setStatusCode(200, 'OK')
        ->setJsonContent(
            [
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ]
        )
        ->send();
} catch (PhalconDispatchException $de) {
    (new Response())
        ->setContentType('application/json')
        ->setStatusCode(404, 'Not found')
        ->setJsonContent(
            [
                'success' => false,
                'data'    => null,
                'error'   => $de->getMessage(),
            ]
        )
        ->send();
} catch (Error $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->has('env') && $app->getDi()->get('env') == 'prod') {
        $app->getDi()->get('log')->error($app->getExceptionText($e));

        (new Response())
            ->setContentType('application/json')
            ->setStatusCode(500, 'Server Error')
            ->setJsonContent(
                [
                    'success' => false,
                    'data'    => null,
                    'error'   => 'На сервере произошла ошибка (см. логи)',
                ]
            )
            ->send();
    } else {
        (new Response())
            ->setStatusCode(500, 'Server Error')
            ->setContent($e->getMessage())
            ->send();
    }
} catch (Exception $e) {
    // если отладка включена, то передадим исключение дальше
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->get('config')['debug']) {
        throw $e;
    }

    if ($e instanceof \App\Common\Exception\JsonException) {
        $message = $e->getJson();
    } else {
        $message = $e->getMessage();
        // Елси это обычный Exception, то кинем его в логи как ошибку
        if ($e instanceof Exception
            && array_key_exists('app', $GLOBALS)
            && $app->getDi()->has('env')
            && $app->getDi()->get('env') == 'prod'
        ) {
            $app->getDi()->get('log')->error($app->getExceptionText($e));
        }
    }

    $response = new Response();
    // стандартные номер ошибок от 0 до 5 (http://docs.phalconphp.ru/ru/latest/api/Phalcon_Mvc_Dispatcher.html)
    // потому для них всех 500 делаем
    //$number = $e->getCode() > 5?$e->getCode():500;
    // всегда ответ будет 200, чтоб своими методами обработать текст ошибки
    $response
        ->setContentType('application/json')
        ->setStatusCode(200, 'Server Error')
        ->setJsonContent(
            [
                'success' => false,
                'data'    => null,
                'error'   => $message,
            ]
        )
        ->send();
}
