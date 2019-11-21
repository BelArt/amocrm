<?php

namespace App\Common\Phalcon\Logger\Adapter;

class FileWithBacktrace extends \Phalcon\Logger\Adapter\File
{
    /**
     * Включение выключение бектрейса для логирования
     */
    private $backtrace = true;

    /**
     * Phalcon\Logger\Adapter\File constructor
     *
     * @param string name
     * @param array options
     */
    public function __construct($name, $options = null)
    {
        if (is_array($options) && isset($options['backtrace'])) {
            $this->backtrace = (bool)$options['backtrace'];
            unset($options['backtrace']);
        }

        parent::__construct($name, $options);
    }

    public function logInternal($message, $type, $time, array $context)
    {
        if ($this->backtrace) {
            $trace = debug_backtrace();
            // Свой функционал (контроллеры) обычно на 4 шага назад
            $depth = 3;
            if (isset($trace[$depth])) {
                // Если у 3го нет класса, то это замыкание в замыкании
                if (!isset($trace[$depth]['class'])) {
                    $depth++;
                }

                $class = explode('\\', $trace[$depth]['class']);
                // Тут может быть closure вида App\User\Cli\Task\{closure}, по идеи
                // можно спуститься ниже и понять откуда она вызвана, но пока так.
                $function = explode('\\', $trace[$depth]['function']);

                $message = $class[count($class) - 1] . ' :: '. $function[count($function) - 1] . ' :: ' . $message;
            }
        }

        parent::logInternal($message, $type, $time, $context);
    }
}
