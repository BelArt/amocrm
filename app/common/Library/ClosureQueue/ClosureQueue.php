<?php

namespace App\Common\Library\ClosureQueue;

use SuperClosure\Serializer;
use Gos\Component\PnctlEventLoopEmitter\PnctlEmitter;
use React\EventLoop\Factory as Loop;
use App\Common\Model\User;
use Phalcon\Queue\Beanstalk;
use Phalcon\Logger\AdapterInterface;

/**
 * Класс для работы с очередью замыканий. Служит для избегания создавания демона
 * на каждый чих. Использовать только для небольших задач, которые нужно исполнить
 * в очереди, но делать демона лишнее. Например, отложенная обработка вебхука.
 *
 * Ниже приведён пример использования в контроллере. Обект этого класса находится
 * в `$this->closure`. В обработчик всё надо передавать напрямую т.к. сериализации
 * $this и self не подлежат. Внутри обработчика мы имеем функцию добавления
 * в очередь `$put` (обработчик `$handler` снова передавать не нужно) и функцию
 * удаления текущей джобы из очереди `$delete`.
 *
 * $log = $this->di->get('log');
 * $delaySeconds = self::DELAY_SECONDS;
 * $handler = function ($data, $put, $delete) use ($log, $delaySeconds) {
 *      $call = $data['call']; // получаем звонок
 *      $resp = $data['resp']; // номер текущей попытки
 *
 *      $log->notice('Обрабатываем ' . $resp . ': ' . print_r($call, true));
 *
 *      if (--$resp > 0) {
 *          $put([
 *              'resp' => $resp,
 *              'call' => $call,
 *          ], [
 *              'delay' => $delaySeconds,
 *          ]);
 *      }
 *
 *      $delete();
 * };
 *
 * $this->closure->put($handler, [
 *      'resp' => 3,
 *      'call' => [
 *          'qwer' => 4,
 *          'qqqq' => 'ffffff',
 *      ],
 * ], [
 *      'delay' => self::DELAY_SECONDS,
 * ]);
 */
class ClosureQueue
{
    private $queue;
    private $serializer;
    private $log;
    private $user;

    public function __construct(AdapterInterface $log, Beanstalk $queue, User $user = null)
    {
        $this->queue = $queue;

        $this->log      = $log;
        $this->user     = $user;

        $this->serializer = new Serializer();
    }

    /**
     * Добавляем в очередь на исполнение замыкание. Замыкание должно на вход
     * принимать массив данных, саму джобу для управления ею (удаление) и
     * замыкание для повторого использования в коде (рекурсии по сути):
     *
     * function ($data, $put, $delete) {
     *      $delete();
     * }
     *
     * По сути, передать в $closure можно уже сериализованное замыкание, а не
     * только callback-объект. Это требуется при переиспользовании обработчика.
     *
     * По умолчанию, если эта очередь используется в функционале пользователя,
     * то его контекст будет автоматически цепляться (его namespaces'ы). Но если
     * использование очереди происходит внеконтекста пользователя, то через
     * options можно передать имя пользователя для подтягивания этого контекста.
     * Последнее нужно для повтора джобы т.к. контекста пользователя в console
     * нет, но из тела задачи будет получено его имя, а значит при повторе можно
     * пробросить его дальше (см. ниже метод loop()).
     *
     * @param callback|string $closure
     * @param array $data
     * @param array $options
     * @return void
     */
    public function put($closure, array $data = [], array $options = [])
    {
        $userName = null;
        // Приоритетней передавать user в options
        if (isset($options['user']) && $options['user']) {
            $userName = $options['user'];
            unset($options['user']);
        } elseif ($this->user) {
            $userName = $this->user->name;
        }

        $this->queue->choose('closure_queue');
        $this->queue->put(
            [
                'user' => $userName,
                'data' => $data,
                'closure' => is_string($closure) ? $closure : $this->serializer->serialize($closure),
            ],
            $options
        );
    }

    /**
     * Удаляем всё из очереди либо указанное кол-во джоб.
     *
     * @param int $number
     * @return int
     */
    public function flush($number = null)
    {
        $this->queue->choose('closure_queue');

        $inc = 0;
        while (($job = $this->queue->reserve()) !== false) {
            $job->delete();
            $inc++;

            if ($number && $inc >= $number) {
                break;
            }
        }

        return $inc;
    }

    /**
     * Обработка очереди с джобам
     *
     * @param array $data
     * @param callback $closure
     * @param array $options
     * @return void
     */
    public function loop()
    {
        $this->log->notice('Общая очередь замыканий стартовала');

        $this->queue->watch('closure_queue');

        $jobOut = null;
        $loop   = Loop::create();

        $pnctlEmitter = new PnctlEmitter($loop);

        $timer = $loop->addPeriodicTimer(1, function () use (&$jobOut, $loop) {
            if (($job = $this->queue->reserve()) !== false) {
                $jobOut = $job;
                $body   = $job->getBody();

                $this->log->notice('Данные джобы из очереди замыкания: ' . print_r($body, true));

                if (!isset($body['data']) || !isset($body['closure'])) {
                    $job->delete();

                    return false;
                }

                $userName   = $body['user'];
                $app        = null;

                // Подгружаем cli-окружение пользователя
                if ($userName) {
                    $app = new \App\Common\Bootstrap\Bootstrap(
                        new \Phalcon\Di\FactoryDefault\Cli(),
                        \App\Common\Bootstrap\Application::cli(),
                        \App\Common\Bootstrap\Environment::fromFile(ROOT_PATH.'/.env')
                    );

                    $auth = $app->getDi()->get('auth');

                    if ($auth->checkByName($userName)) {
                        $app->bootUser();
                    }

                }

                $closureSerialize = $body['closure'];

                try {
                    $closureUnserialize = $this->serializer->unserialize($closureSerialize);

                    $closureUnserialize(
                        $body['data'],
                        function (array $data = [], array $options = []) use ($closureSerialize, $userName) {
                            // Прокидываем имя пользователя при повторе через options
                            // т.к. контекста пользователя в console нет.
                            $options = array_merge($options, ['user' => $userName]);

                            $this->put($closureSerialize, $data, $options);
                        },
                        function () use ($job) {
                            $job->delete();
                        }
                    );
                } catch (\Exception $e) {
                    $message = $app ? $app->getExceptionText($e) : $e->getMessage();

                    $this->log->error(
                        'Джоба из очереди замыканий пользователя '
                        . $userName . ' вызвала исключение: ' . $message
                    );
                    // Если джоба вызвала исключение, то просто удалим без повторов
                    // т.к. это её проблема.
                    $job->delete();
                } catch (\Error $e) {
                    $message = $app ? $app->getExceptionText($e) : $e->getMessage();

                    $this->log->error(
                        'Джоба из очереди замыканий пользователя '
                        . $userName . ' совершило ошибку: ' . $message
                    );
                    // Если джоба вызвала исключение, то просто удалим без повторов
                    // т.к. это её проблема.
                    $job->delete();
                }

                // $job->delete();

                $jobOut = null;

                // Внимание! Очередь останавливается после удачного завершения обработки
                // т.к. классы перезагрузить нельзя, а изначально была выбрана политика с
                // одинаковыми namespace для заказчиков, а значит инициализованные классы
                // ранее не могут быть перетёрты поздними с аналогичными именами. НИКАК!
                // Потому было решено запускать несколько обработчиков каждую минуту с
                // остановкой каждого после первой удачной обработки джобы, которые
                // резервируются – нет проблем с обработкой одной джобы двумя процессами.
                $loop->stop();
            }
        });

        // Обрабатываем SIGTERM, который будет кидать скрипт деплоя для остановки
        // этого демона. В этом случае текущая джоба завершит свою обработку и
        // демон будет остановлен штатно без потери данных.
        $pnctlEmitter->on(SIGTERM, function () use ($loop, $timer) {
            $this->log->notice('Остановка работы очереди по SIGTERM');

            $loop->stop();
            $timer->cancel();
        });

        // Особая магия для удаления джобы, в которой произошёл Fatal Error т.к.
        // обычный обработчик PHP-ошибок не может это перехватить, то делаем это
        // через обработчик завершения работы скрипта, который шарит с основным
        // обработчиком джобы эту самую джобу (через переменную $jobOut) и в случае
        // чего просто её удаляет, чтобы при перезапуске она не застопорила всё.
        register_shutdown_function(function () use (&$jobOut) {
            $lastError = error_get_last();

            if ($lastError['type'] === E_ERROR && $jobOut) {
                $body = $jobOut->getBody();

                $this->log->error(
                    'В джобе из очереди замыканий пользователя '
                    . $body['user'] . ' произошёл Fatal Error – она удаляется, причина: '
                    . $lastError['message']
                );

                $jobOut->delete();
            }
        });

        $loop->run();

        $this->log->notice('Общая очередь замыканий остановлена');
    }
}
