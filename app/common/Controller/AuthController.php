<?php

namespace App\Common\Controller;

use \App\Common\Form\Auth\LoginForm,
    \App\Common\Model\User,
    \App\Common\Form\Auth\ForgotPasswordForm,
    \App\Common\Form\Auth\ChangePasswordForm;

/**
 * Авторизация для пользователей
 *
 * @property \Phalcon\UsersAuth\Library\Auth\Auth $auth
 */
class AuthController extends ControllerBaseCommon
{
    /**
     * Step 1 - login
     *
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function loginAction()
    {
        $form = new LoginForm();

        if (!$this->request->isPost()) {
            if ($this->auth->hasRememberMe()) {
                $this->auth->loginWithRememberMe();
            }
        } else {
            if ($form->isValid($this->request->getPost())) {
                if ($this->auth->checkByPass(
                    $this->request->getPost('name'),
                    $this->request->getPost('pass'),
                    $this->request->getPost('rem'),
                    $this->request->get('widget', null, false) // скорее всего оно будет в GET-параметре
                )) {
                    // if ($url = $this->auth->getTargetUrl()) {
                    //     return $this->response->redirect($url);
                    // } else {
                        return $this->response->redirect('/');
                    // }
                }
            }
            $form->clear(['pass']);
            $this->flash->error('Неверные реквизиты!');
            //foreach ($form->getMessages() as $message )
            //	$this->flash->error($message);
        }

        $this->view->form = $form;
        $this->view->pick('auth/login');
    }

    /**
     * Step 2 - logout
     *
     * @return \Phalcon\Http\ResponseInterface
     */
    public function logoutAction()
    {
        $this->auth->remove();
        return $this->response->redirect('/');
    }

    /**
     * Step 3 - forgot password
     */
    public function forgotAction()
    {
        $form = new ForgotPasswordForm();
        if ($this->request->isPost()) {
            if ($form->isValid($this->request->getPost())) {
                $user = User::findFirst([['name' => $this->request->getPost('name')]]);
                if (!$user) {
                    $this->flash->notice('Такого пользователя не существует');
                } else {
                    $code = null;
                    // если уже есть токен не использованный для восстановления, то возьмём его
                    if ($user->res || !($code = array_search(false, $user->res))) {
                        $code = $this->security->getToken();
                        $user->res[$code] = false;
                        if (!$user->save()) {
                            foreach ($user->getMessages() as $message)
                                $this->flash->error($message);
                            $code = null;
                        }
                    }

                    if ($code) {
                        $link = $this->url->get('/auth/resetpassword/' . $code . '/' . $user->_id);
                        $this->flash->success("Приветствуем, {$user->name}!\n\nДля смены пароля, пожалуйста, пройдите по ссылке {$link}\n\nОбращаем Ваше внимание на то, что перейдя по ссылке вы авторизируетесь и уже тогда сможете изменить пароль из своего профиля.\n\nС наилучшими пожеланиями,\nкоманда сайта.");
                        $form->clear();
                    }
                }
            } else
                foreach ($form->getMessages() as $message)
                    $this->flash->error($message);
        }

        $this->view->form = $form;
        $this->view->pick('auth/forgot');
    }

    /**
     * Step 4 - reset password
     *
     * @return mixed
     */
    public function resetPasswordAction($code, $id)
    {
        $user = User::findById($id);
        if (!$user || !isset($user->res[$code]) || $user->res[$code] <> false) {
            return $this->response->redirect('/');
        }

        $user->res[$code] = true;

        // Change the confirmation to 'reset'
        if (!$user->save()) {
            foreach ($user->getMessages() as $message) {
                $this->flash->error($message);
            }
            return $this->response->redirect('/');
        }

        // Identity the user in the application
        $this->auth->checkById($id, true);
        $this->flash->success('Пожалуйста измените свой пароль');

        return $this->response->redirect('/auth/changepassword');
    }

    /**
     * Step 5 - change password
     *
     * Users must use this action to change its password
     */
    public function changePasswordAction()
    {
        $form = new ChangePasswordForm();
        if ($this->request->isPost()) {
            if ($form->isValid($this->request->getPost())) {
                $user = $this->auth->getUser();
                $user->pass = $this->security->hash($this->request->getPost('pass'));
                if ($user->save()) {
                    $this->flash->success('Ваш пароль успешно изменён');
                } else {
                    $this->flash->error('Что-то пошло не так. Пароль не изменён. Попробуйте позже.');
                }
                return $this->response->redirect('/');
            }
        }

        $this->view->form = $form;
        $this->view->pick('auth/changepassword');
    }
}
