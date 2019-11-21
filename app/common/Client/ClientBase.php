<?php

namespace App\Common\Client;

/**
 * абстрактный класс для создания клиентов для веб-сокета
 * необходимо для запоминания сессии сокета и работы с очередью
 * все клиенты пользователей должны наследоваться от этого класса
 */
abstract class ClientBase extends \Thruway\Peer\Client implements \Phalcon\Di\InjectionAwareInterface
{
    protected $di;
    protected $workerMq;
    protected $session;
    protected $transport;

    public function __construct(\Phalcon\DiInterface $di, \App\Common\Library\Queue\Worker $workerMq)
    {
		$this->di = $di;
        $this->workerMq = $workerMq;

        parent::__construct($this->di->get('user')->name);
    }

    /**
     * @param \Thruway\ClientSession $session
     * @param \Thruway\Transport\TransportInterface $transport
     */
    public function onSessionStart($session, $transport)
    {
        $this->session = $session;
        $this->transport = $transport;

        $this->onProcessQueue();
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
	 * обязательная функция для работы с очередью,
	 * чтобы дёргать нужные ниточки по сообщения из очереди
	 */
    abstract function onProcessQueue();
}
