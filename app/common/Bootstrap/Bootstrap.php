<?php

namespace App\Common\Bootstrap;

use \Phalcon\Config;
use \App\Common\Plugin\MethodAnnotationPlugin;
use \Phalcon\Di\InjectionAwareInterface;
use \App\Common\Exception\UserNotExistsException;
use \App\Common\Exception\WarningException;
use \App\Common\Exception\NoticeException;
use \App\Common\Exception\OtherErrorException;

final class Bootstrap implements InjectionAwareInterface
{
    /**
     * Dependency Injector reference
     *
     * @var DiInterface
     */
    private $di;

    /**
     * Автозагрузчик Phalcon
     *
     * @var Loader
     */
    private $loaderPhalcon;

    /**
     * Автозагрузчик Composer'а пользователя
     *
     * @var Loader
     */
    private $loaderComposer;

    /**
     * Bootstrap constructor - sets some defaults and stores the di interface
     *
     * @param DiInterface $di
     * @param Application $app
     * @param Environment $env
     */
    public function __construct(\Phalcon\DiInterface $di, Application $app, Environment $env)
    {
        $this->di = $di;
        $this->loaderPhalcon = new \Phalcon\Loader();
        $this->loaderPhalcon->register();
        $this->di->set('env', $env);
        $this->di->set('app', $app);

        $this->loaderPhalcon
            ->registerNamespaces([
                'App\\Common' => APP_PATH.'/common/',
            ], true);

        // подгружаем необходимые начальные конфиги
        $this
            // первичная загрузка неймспейсов и классов + настройка фреймворка
            ->addConfig(APP_PATH.'/resource/config/setting.php', true)
            // настройки среды (конекты для внешних сервисов: redis, etc)
            ->addConfig(APP_PATH.'/resource/config/parameter_'.$env.'.php', true);

        // отдельно добавим Auth, чтобы он 100% был
        $this->di->set('auth', new \App\Common\Bootstrap\Auth(), true);
        $this->di->set('user', function () {
            return $this->get('auth')->getUser();
        }, true);

        $this
            // производим первичную загрузку сервисов, необходимых для поднятия приложения (минимум mongo, collectionManager)
            ->addService(APP_PATH.'/resource/config/service_'.$app.'.php', true);

        if (isset($this->di->get('config')->debug) && $this->di->get('config')->debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'on');
            (new \Phalcon\Debug())->listen();
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 'off');
        }
    }

    /**
     * Загрузка конфигов и сервисов пользователя
     */
    public function bootUser()
    {
        $user = $this->di->get('auth')->getUser();

        if ($user) {
            if ($user->disabled) {
                return false;
            }

            $appName = $this->di->get('app');
            $userDir = APP_PATH.'/user/'.$user->name;

            // проверим наличие папки с логикой пользователя
            if (!file_exists($userDir.'/')) {
                throw new UserNotExistsException('Missing user folder', 500);
            }

            // подключаем зависимости пользователя, если они есть
            if (file_exists($userDir . '/vendor/autoload.php')) {
                $this->loaderComposer = require_once $userDir . '/vendor/autoload.php';
            }

            $this
                ->addConfig($userDir.'/resource/config/setting.php')
                // пытаемся подключить динамический конфиг, что шарится между релизами подменяет собой стандартные настройки
                ->addConfig(ROOT_PATH . '/env/' . $user->name. '.php')
                ->addService($userDir.'/resource/config/service_'.$appName.'.php');

            // Чтение конфига из данных пользователя в MonogDB
            if ($user->config && is_array($user->config)) {
                    $config = new Config($user->config);
                if ($this->di->has('config')) {
                    $this->di->get('config')->merge($config);
                } else {
                    $this->di->set('config', $config);
                }
            }

            // и добавим отдельный namespace для логики пользователя в зависимости от того какое приложение инициируется
            // добавляем общий функционал и уникальный функционал для конкретного интерфейса доступа (api, cli, gui)
            $this->loaderPhalcon
                ->registerNamespaces([
                    'App\\User\\'.ucfirst(strtolower($appName)) => $userDir.'/'.$appName.'/',
                    'App\\User\\Common' => $userDir.'/common/',
                ], true);

            return true;
        } else {
            throw new UserNotExistsException('This user was not found. Maybe you incorrectly stated login?', 418);
        }
    }

    /**
     * Загрузка конфигов и сервисов конкретного виджета
     */
    public function bootWidget($name)
    {
        //if ($user = $this->di->get('auth')->getWidget()) {
            $appName = $this->di->get('app');
            $widgetDir = APP_PATH.'/widget/'.$name;

            // проверим наличие папки с логикой пользователя
            if (!file_exists($widgetDir.'/'))
                throw new \Exception('This widget (folder) was not found.', 418);

            $this
                ->addConfig($widgetDir.'/resource/config/setting.php')
                ->addService($widgetDir.'/resource/config/service_'.$appName.'.php');

            // и добавим отдельный namespace для логики пользователя в зависимости от того какое приложение инициируется
            // добавляем общий функционал и уникальный функционал для конкретного интерфейса доступа (api, cli, gui)
            $this->loaderPhalcon
                ->registerNamespaces([
                    'App\\Widget\\'.ucfirst(strtolower($appName)) => $widgetDir.'/'.$appName.'/',
                    'App\\Widget\\Common' => $widgetDir.'/common/',
                ], true);
            return true;
        //}
        //return false;
    }

    /**
     * Добавить очередной конфиг, с возможным подгрузом новых неймспейсов и классов
     *
     * @param string $path
     * @param boolean $require - если конфиг обязательный, то при отсутсвии файла выкинуть исключение
     */
    protected function addConfig($path, $require = false)
    {
        $config = null;
        if(is_readable($path)) {
            $config = new Config(require $path);
        } else if ($require == true) {
            throw new \Exception('Config "'.basename($path).'" was not found!', 418);
        } else {
            return $this;
        }

        if ($this->di->has('config'))
            $this->di->get('config')->merge($config);
        else
            $this->di->set('config', $config);

        return $this;
    }

    /**
     * Добавить очередной набор сервисов
     * @param string $path
     * @param boolean $require - если конфиг обязательный, то при отсутсвии файла выкинуть исключение
     */
    protected function addService($path, $require = false)
    {
        $config = null;
        if(is_readable($path)) {
            //var_dump($this->loaderPhalcon->register());
            $config = $this->di->get('config');
            $auth = $this->di->get('auth');
            $services = include $path;
            foreach ($services as $service) {
                // в объявлениях сервисов доступны переменные $config и $auth
                $this->di->set($service[0], $service[1], (isset($service[2])?$service[2]:false));
            }
            //die();
            //$this->di = $di;
        } else if ($require == true) {
            throw new \Exception('Services file was not found!', 418);
        } else {
            return false;
        }

        return $this;
    }

    /**
     * демонизируем данный процесс, аккуратнее, не применять веб-запросов, только cli
     *
     * для поддержание уникальности демона создаём .pid-файл для конкретной команды и демонизируем его (отвязываем от консоли)
     * расположим это после инициализации приложения, потому что нужен конфиг, где указана папка временных файлов
     *
     * @param string $postfix - уникальный постфикс конкретного типа запроса (обычно <пользователь>_<контроллер>_<экшен>) для названия .pid-файла
     */
    public function demonize($postfix)
    {
        // создаем дочерний процесс
        $childPid = pcntl_fork();

        if ($childPid < 0) {
            die('could not fork');
        } else if ($childPid) {
            // выходим из родительского, привязанного к консоли, процесса
            exit;
        } else {
            // делаем основным процессом дочерний
            $childPid = posix_setsid();

            if ($childPid < 0) exit;
        }

        // название pid-файла состоить из окружения (нужно чтобы тестить без остановки dev-демонов), имени пользователя, контроллера и экшена
        $pidFile = $this->di->get('config')['daemon'].'/'.$this->di->get('env').'-'.$postfix.'.pid';

        if (is_file($pidFile)) {
            $pid = file_get_contents($pidFile);
            //проверяем на наличие процесса
            if (posix_kill($pid,0)) {
                die("Already runninng\n");
            } else {
                //pid-файл есть, но процесса нет
                if(!unlink($pidFile)) {
                    //не могу уничтожить pid-файл. ошибка
                    exit(-1);
                }
            }
        }

        file_put_contents($pidFile, getmypid());
    }

    /**
     * Формирует текст ошибки на основе данных исключения.
     *
     * @param \Throwable $exception
     *
     * @return string
     */
    public function getExceptionText(\Throwable $exception)
    {
        $message = $exception->getMessage();
        $code    = $exception->getCode();
        $file    = $exception->getFile();
        $line    = $exception->getLine();
        $class   = get_class($exception);

        return "Исключение $class ($code): $message в $file в строке $line";
    }

    public function setDi(\Phalcon\DiInterface $di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    /**
     * Отменяет регистрацию автозагрузчика в качестве реализации метода
     * __autoload(), чтобы исключить из namespace'ов ранее зарегестрированные.
     * Это требуется при локальном добавлении и удалении контекста пользователя.
     *
     * @return void
     */
    public function unregisterLoader()
    {
        $this->loaderPhalcon->unregister();

        if ($this->loaderComposer) {
            $this->loaderComposer->unregister();
        }
    }
}
