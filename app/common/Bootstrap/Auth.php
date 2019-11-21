<?php
namespace App\Common\Bootstrap;

use Phalcon\Mvc\User\Component,
    App\Common\Model\User,
    Phalcon\UsersAuth\Models\FailedLogins,
    Phalcon\UsersAuth\Library\Auth\Exception\Exception;

/**
 * Phalcon\UsersAuth\Auth\Auth
 *
 * Manages Authentication/Identity Management
 *
 * @property \Phalcon\Http\Cookie $cookies
 */
class Auth extends Component
{
    /**
     * The logged in user
     */
    protected $_user;

    /**
     * Is widget request? false - not widget, <string> – name of widget
     */
    protected $_widget = false;

    /**
     * Checks the user credentials with name and pass for gui
     *
     * @param string $name
     * @param string $pass
     * @param boolean $rem
     * @param string|boolean $widget – запрос пришёл от виджета или нет
     *
     * @return boolean
     */
    public function checkByPass($name, $pass, $rem = false, $widget = false)
    {
        $this->_widget = $widget;

        //Check if the user exist
        $user = User::findFirst([['name' => $name]]);
        if (!$user)
            return false;

        //Check the password
        if (!$this->security->checkHash($pass, $user->pass)) {
            return false;
        }

        // Check if the remember me was selected
        if ($rem) {
            $this->createRememberEnviroment($user);
        }

        $this->createSession($user);
        $this->_user = $user;
        return true;
    }

    /**
     * Checks the user credentials with name and hash for api
     *
     * @param string $name
     * @param string $pass
     * @param boolean $session - формировать сессию или нет
     * @param string|boolean $widget – запрос пришёл от виджета или нет
     *
     * @return boolean
     */
    public function checkByHash($name, $hash, $session = false, $widget = false)
    {
        $this->_widget = $widget;

        $user = User::findFirst([[
            'hash' => $hash,
            'name' => $name
        ]]);

        if (!$user)
            return false;

        if ($session)
            $this->createSession($user);

        $this->_user = $user;
        return true;
    }

    /**
     * Checks the user credentials only with name (it's unique) for cli
     *
     * @param string $name
     * @param boolean $session - формировать сессию или нет
     * @param string|boolean $widget – запрос пришёл от виджета или нет
     *
     * @return boolean
     */
    public function checkByName($name, $session = false, $widget = false)
    {
        $this->_widget = $widget;

        $user = User::findFirst([[
            'name' => $name
        ]]);

        if (!$user)
            return false;

        if ($session)
            $this->createSession($user);

        $this->_user = $user;
        return true;
    }

    /**
     * Checks the user credentials only with id
     *
     * @param string $id
     * @param boolean $session - формировать сессию или нет
     * @param string|boolean $widget – запрос пришёл от виджета или нет
     *
     * @return boolean
     */
    public function checkById($id, $session = false, $widget = false)
    {
        $this->_widget = $widget;

        $user = User::findById($id);
        if (!$user)
            return false;

        if ($session)
            $this->createSession($user);

        $this->_user = $user;
        return true;
    }

    /**
     * Creates the remember me environment settings the related cookies and generating tokens
     *
     * @param Vokuro\Models\Users $user
     */
    public function createRememberEnviroment(User $user)
    {
        $userAgent = $this->request->getUserAgent();
        $token = md5($user->email . $user->pass . $userAgent);
        $user->rem[$token] = time();
        if ($user->save() != false) {
            $expire = time() + 86400 * 8;
            $this->cookies->set('rmu', $user->_id, $expire);
            $this->cookies->set('rmt', $token, $expire);
        }
    }

    /**
     * Создание сессия для запоминания пользователя
     *
     * @param App\Common\Model\Users $user
     */
    public function createSession(User $user)
    {
        $this->session->set('auth-identity', [
            'id' => $user->_id,
            'name' => $user->name,
            'widget' => $this->_widget,
        ]);
    }

    /**
     * Check if the session has a remember me cookie
     *
     * @return boolean
     */
    public function hasRememberMe()
    {
        return $this->cookies->has('rmu');
    }

    /**
     * Logs on using the information in the coookies
     *
     * @return boolean
     */
    public function loginWithRememberMe()
    {
        $userId = $this->cookies->get('rmu')->getValue();
        $cookieToken = $this->cookies->get('rmt')->getValue();
        $user = User::findById($userId);

        if ($user) {
            $userAgent = $this->request->getUserAgent();
            $token = md5($user->email . $user->pass . $userAgent);
            if ($cookieToken == $token) {
                if (isset($user->rem[$token]))  {
                    // Check if the cookie has not expired
                    if ((time() - (86400 * 8)) < $user->rem[$token]) {
                        // Register identity
                        $this->createSession($user);
                        return true;
                    }
                }
            }
        }
        $this->cookies->get('rmu')->delete();
        $this->cookies->get('rmt')->delete();
        return false;
    }

    /**
     * Returns the current identity
     *
     * @return boolean
     */
    public function getIdentity()
    {
        return $this->session->get('auth-identity');
    }

    /**
     * Returns the current identity
     *
     * @return boolean
     */
    public function hasUser()
    {
        return $this->session->has('auth-identity') || !empty($this->_user);
    }

    /**
     * Returns the current identity
     *
     * @return string
     */
    public function getUser()
    {
        if ($this->_user !== null) return $this->_user;

        if ($this->session->has('auth-identity')) {
            $identity = $this->session->get('auth-identity');
            $this->_user = User::findById($identity['id']);
        } else {
            $this->_user = false;
        }

        return $this->_user;
    }

    /**
     * Добавляем нового пользователя, на основе входного массива данных
     *
     * @param array $params
     * @return true|array - массив с ошибками, если что-то пошло не так
     */
    public function addUser($params)
    {
        $user = new User();
        if (isset($params['name']))
            $user->name = $params['name'];
        if (isset($params['email']))
            $user->email = $params['email'];
        if (isset($params['pass']))
            $user->pass = $this->security->hash($params['pass']);
        if (isset($params['role']))
            $user->role = $params['role'];
        if (isset($params['port']))
            $user->port = $params['port'];

        if (isset($params['hash']))
            $user->hash = $params['hash'];
        else
            $user->generateHash();

        if ($user->save() == false) {
            $errors = [];
            foreach ($user->getMessages() as $message)
                $errors[] = $message;

            return $errors;
        }

        return true;
    }

    /**
     * Редактируем существующего пользователя, на основе входного массива данных
     *
     * @param User $user
     * @param array $params
     * @return true|array - массив с ошибками, если что-то пошло не так
     */
    public function editUser(User $user, $params)
    {
        if (isset($params['name']))
            $user->name = $params['name'];
        if (isset($params['email']))
            $user->email = $params['email'];
        if (isset($params['pass']))
            $user->pass = $this->di->get('security')->hash($params['pass']);
        if (isset($params['role']))
            $user->role = $params['role'];
        if (isset($params['hash']))
            $user->hash = $params['hash'];
        if (isset($params['port']))
            $user->port = $params['port'];
        if (isset($params['disabled']))
            $user->disabled = ($params['disabled'] == 'true' ? true : false);

        if ($user->save() == false) {
            $errors = [];
            foreach ($user->getMessages() as $message)
                $errors[] = $message;

            return $errors;
        }

        return true;
    }

    /**
     * Removes the user identity information from session
     */
    public function remove()
    {
        if ( $this->cookies->has('rmu') ) {
            $this->cookies->get('rmu')->delete();
        }
        if ( $this->cookies->has('rmt') ) {
            $this->cookies->get('rmt')->delete();
        }
        $this->session->remove('auth-identity');
    }

    /**
     * запомнить URL текущей страницы, необходимо для неудачной авторизации,
     * если запрашивалась не главная страница
     */
    public function setTargetUrl()
    {
        if (empty($_SERVER["SERVER_NAME"]) || empty($_SERVER["SERVER_PORT"]) || empty($_SERVER["REQUEST_URI"])) return;

        $url = @( $_SERVER["HTTPS"] != 'on' ) ? 'http://'.$_SERVER["SERVER_NAME"] :  'https://'.$_SERVER["SERVER_NAME"];
        $url .= ( $_SERVER["SERVER_PORT"] != 80 ) ? ":".$_SERVER["SERVER_PORT"] : "";
        $url .= $_SERVER["REQUEST_URI"];
        $this->session->set('login-target-url', $url);
    }

    /**
     * получить URL страницы на которую хотел перейти пользователь до начала авторизации
     */
    public function getTargetUrl()
    {
        return $this->session->get('login-target-url', null, true);
    }

    /**
     * обрабатываем запрос от виджета (общего виджета) или нет (значение может просто в сессии лежать)
     *
     * @return string|false
     */
    public function getWidget()
    {
        if ($this->_widget) return $this->_widget;

        if ($this->session->has('auth-identity')) {
            $identity = $this->session->get('auth-identity');
            if (isset($identity['widget']))
                $this->_widget = $identity['widget'];
        }

        return $this->_widget;
    }

    /**
     * устанавливаем новое имя виджета, но (!)
     * это не уходит в сессию т.к. может и не быть пользователя >_<
     * требуется при работе виджета без авторизации какого-либо пользователя
     *
     * @param string $widget
     */
    public function setWidget($widget)
    {
        $this->_widget = $widget;
    }
}
