<?php

namespace App\Common\Controller;
	
/**
 * Родительский контроллер для пользовательских контроллеров
 */
abstract class ControllerBaseUser extends \Phalcon\Mvc\Controller
{
	public function initialize()
	{
		// Меняем папку с вьюхами на общую не зависимо от того что было раньше
		$this->view->setViewsDir(APP_PATH.'/user/'.$this->auth->getUser()->name.'/resource/view/');
		// меняем адрес до макросов кастомизации форм
		$this->view->partial('custom/form');
	}
}