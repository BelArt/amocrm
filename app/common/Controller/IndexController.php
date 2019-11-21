<?php

namespace App\Common\Controller;
	
/**
 * Страницы для всех
 *
 * @property \Phalcon\UsersAuth\Library\Auth\Auth $auth
 */
class IndexController extends ControllerBaseCommon
{	
    /**
     * Main page
     *
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function indexAction()
    {
	    $this->response->redirect('/auth/login');
    }
    
    /**
     * Страница для 404, или редирект на auth/login, если не авторизован
     *
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function error404Action()
    {
	    if (!$this->auth->hasUser()) {
			$this->auth->setTargetUrl(); // запомним целевой url, чтобы после авторизации вернуться
			$this->response->redirect('/auth/login'); 
			$this->view->disable();
		}
    }
    
    /**
     * Страница для 500
     *
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function error500Action()
    {
    }
}