<?php

namespace App\Common\Phalcon;

use Phalcon\Http\Response as PhalconResponse;

/**
 * Класс для работы с Response.
 */
class Response extends PhalconResponse
{
    /**
     * Переопределяет метод send
     *
     * @inheritdoc
     */
    public function send()
    {
        $responseSend = parent::send();

        if (is_callable('fastcgi_finish_request')) {
            session_write_close();
            fastcgi_finish_request();
        }

        return $responseSend;
    }

    /**
     * Добавляет в Response контент с данными для наших виджетов.
     *
     * @param array $data Данные для виджетов
     *
     * @inheritdoc
     */
    public function setSuccessJsonContent($data)
    {
        return parent::setJsonContent(
            [
                'success' => true,
                'data'    => $data,
                'error'   => null,
            ]
        );
    }

    /**
     * Добавляет в Response контент с ошибкой для наших виджетов.
     *
     * @param string   $text Текст ошибки
     * @param int|null $code Код ошибки
     *
     * @inheritdoc
     */
    public function setFailJsonContent($text, $code = null)
    {
        return parent::setJsonContent(
            [
                'success'    => false,
                'data'       => null,
                'error'      => $text,
                'error_code' => $code,
            ]
        );
    }
};