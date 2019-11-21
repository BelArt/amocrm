<?php

namespace App\Common\Exception;

/**
 * класс исключения для того чтобы возвращать json в место string при исключении,
 * чтобы на клиент отправлять нормальный json
 */
class JsonException extends \Exception
{
	private $json;
	
	/**
	 * изменили толкьо первый параметр
	 * @param array $messages - именованный (или нет) массив с ошибками
	 */
    public function __construct(array $messages, $code = 0, Exception $previous = null) {
		$this->json = $messages;
        parent::__construct(json_encode($messages), $code, $previous);
    }

    public function getJson() {
        return $this->json;
    }
}