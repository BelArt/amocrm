<?php

namespace App\Common\Controller;

/**
 * Родительский контроллер для общих контроллеров
 */
abstract class ControllerBaseCommon extends \Phalcon\Mvc\Controller
{
    public function initialize()
    {
        // Меняем папку с вьюхами на общую не зависимо от того что было раньше
        $this->view->setViewsDir(APP_PATH.'/resource/view/');
        // меняем адрес до макросов кастомизации форм
        $this->view->partial('custom/form');
    }
}
