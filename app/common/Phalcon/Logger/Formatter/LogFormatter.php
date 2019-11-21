<?php

namespace App\Common\Phalcon\Logger\Formatter;

class LogFormatter extends \Phalcon\Logger\Formatter\Line
{
    public function format($message, $type, $timestamp, $context = null)
    {
        $dateTime = new \DateTime;
        $dateTime->setTimestamp($timestamp);
        $t = microtime(true);
        $dateFormat = str_replace('u', sprintf("%06d", ($t -  floor($t)) * 1000000), $this->getDateFormat());

        $formated = str_replace('%date%', $dateTime->format($dateFormat), $this->getFormat());
        $formated = str_replace('%message%', $message, $formated);

        return str_replace('%type%', $this->getTypeString($type), $formated) . "\n";
    }
}
