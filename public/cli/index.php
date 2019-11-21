<?php

/**
 * точка входа для консольных команд пользователей и виджетов, есть отличие
 * от других точек входа в том что тут не нужна авторизация пользователя
 * т.к. процесс запускается от имени виджета, а не какого-либо пользователя.
 *
 * вызов какой-либо команды происходит следующим образом:
 *
 * * php public/cli/index.php user:<name_of_user>:<taks>:<action>[:daemon] [<params>] – от пользователя
 * * php public/cli/index.php widget:<name_of_widget>:<taks>:<action>[:daemon] [<params>] – от виджета
 *
 * * name_of_user - это уникальный логин пользвоателя (совпадает с его директорией)
 * * name_of_widget - это уникальное название виджета (совпадает с его директорией)
 * * taks - это задача (контроллер)
 * * action - это экшен, что непосредственно исполняется
 * * daemon - обеспечивает уникальность процесса (фактически конкретной команды) или демонизирует процесс, если установленно
 * * params - это параметр(ы) в экшен, можно перечислять через пробел, можно именованные (name=param), можно массивом (name=param1,param2)
 *
 * если вызвать экшена, то программа попытается найти главный экшен указанной задачи <taks>Task::mainAction()
 */

umask(0002); // This will let the permissions be 0775

// Определяем путь к каталогу приложений
define('ROOT_PATH', __DIR__.'/../..');
define('APP_PATH', ROOT_PATH.'/app');
define('VENDOR_PATH', ROOT_PATH.'/vendor');
// подключаем зависимости самого ядра
require_once VENDOR_PATH . '/autoload.php';

try {
    // Определяем консольные аргументы
    $arguments = $params = [];
    $name = null;
    $daemon = false;
    foreach($argv as $k => $arg) {
        // в первом аргументе вызов задачи и экшена вида <task>:<action>
        if ($k == 1) {
            $tmp = explode(':', $arg);
            if (count($tmp) < 4)
                throw new \Exception('Missing running task & action: user:<name_of_user>:<task>:<action>[:daemon] [<args>]');

            // запомним пока имя пользователя (или виджета), чтобы потом проверить
            $name = $tmp[1];

            $arguments['task'] = \Phalcon\Text::camelize($tmp[2]);
            // В зависимости от типа запроса нужный нэймспэйс подставим
            $arguments['task'] = '\\App\\User\\Cli\\Task\\'.$arguments['task'];
            $arguments['action'] = \Phalcon\Text::camelize($tmp[3]);

            // если необходимо залокать процесс, то поменяем флаг
            $daemon = isset($tmp[4]);
        } else if ($k >= 2) {
            // следующие аргументы могут быть одиночные <arg_value> или именованные <arg_name>=<arg_value> (без пробелов)
            $tmp = explode('=', $arg);
            if (count($tmp) == 2) {
                // возможно через запятую перечислить нумерованный массив (обычный [<value1>, <value2>, <value2>])
                $arr = explode(',', $tmp[1]);
                if (count($arr) < 2) {
                    $params[$tmp[0]] = $tmp[1];
                } else {
                    $params[$tmp[0]] = $arr;
                }
            } else
                $params[] = $arg;
        }
    }

    if(count($params) > 0) {
        $arguments['params'] = $params;
    }

    require_once APP_PATH.'/common/Bootstrap/Bootstrap.php';
    require_once APP_PATH.'/common/Bootstrap/Environment.php';
    require_once APP_PATH.'/common/Bootstrap/Application.php';
    $app = new \App\Common\Bootstrap\Bootstrap(
        new \Phalcon\Di\FactoryDefault\Cli(),
        \App\Common\Bootstrap\Application::cli(),
        \App\Common\Bootstrap\Environment::fromFile(ROOT_PATH.'/.env')
    );

    // Для поддержание уникальности демона создаём .pid-файл для конкретной команды
    // и демонизируем его (отвязываем от консоли) расположим это после инициализации
    // приложения, потому что нужен конфиг, где указана папка временных файлов.
    // Внимание! Это необходимо делать до первого обращения в MongoDB иначе после
    // форка коннект протухает и будет работать только после повторного коннекта.
    if ($daemon) {
        $task = explode('\\',$arguments['task']);
        $app->demonize('user'.'-'.$name.'-'.strtolower($task[count($task)-1]).'_'.strtolower($arguments['action']));
    }

    $auth = $app->getDi()->get('auth');

    if (!$auth->checkByName($name)) {
        throw new \App\Common\Exception\UserNotExistsException('Пользователя ' . $name . ' в базе данных нет', 418);
    }

    if (!$app->bootUser()) {
        echo 'Загрузка пользователя не удалась';
        exit(250);
    }

    (new \Phalcon\Cli\Console($app->getDi()))->handle($arguments);
} catch (\App\Common\Library\Amo\AmoException $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->has('env') && $app->getDi()->get('env') == 'prod') {
        $app->getDi()->get('log')->error($app->getExceptionText($e));
    }
} catch (\Error $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->has('env') && $app->getDi()->get('env') == 'prod') {
        $app->getDi()->get('log')->error($app->getExceptionText($e));
    }

    echo $e->getMessage();
} catch (\Exception $e) {
    if (array_key_exists('app', $GLOBALS) && $app->getDi()->has('log')) {
        $app->getDi()->get('log')->error($app->getExceptionText($e));
    }

    echo 'Error: '.$e->getMessage();
    exit(255);
}
