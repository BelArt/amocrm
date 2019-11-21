<?php

namespace App\Common\Task;

use App\Common\Model\User;

/**
 * Задача-CRUD для работы с пользователями
 */
class UserTask extends \Phalcon\Cli\Task
{
    /**
     * Выводим весь список заказчиков
     *
     * @example php console user:list
     *
     * @return string
     */
    public function listAction()
    {
        $users = User::find();

        foreach ($users as $user) {
            echo $user->name.' '.$user->email.' '.$user->hash.' '.
                 (is_array($user->role)?implode(', ', $user->role):$user->role)."\n";
        }
    }

    /**
     * Выводим данные одного заказчика
     *
     * @example php console user:get <name>
     *
     * @param array $params
     *
     * @return string
     */
    public function getAction($params)
    {
        if (count($params) != 1) {
            throw new \Exception('Missing count of an arguments: user:get <name>'."\n");
        }

        $user = User::findFirst([['name' => $params[0]]]);

        if (!$user) {
            throw new \Exception('User with this name is not existing'."\n");
        }

        echo $user->name.' '.$user->email.' '.$user->hash.' '.
            (is_array($user->role)?implode(', ', $user->role):$user->role)."\n";
    }

    /**
     * Добавить нового пользователя
     *
     * @example php console user:add name=<name> email=<email> pass=<pass> [role=<role> hash=<hash> port=<port>]
     *
     * @param array $params
     *
     * @return string
     */
    public function addAction($params)
    {
        // проверим количество параметров
        if (count($params) < 3) {
            throw new \Exception(
                'Missing count of an arguments: user:add name=<name> email=<email> '.
                'pass=<pass> [role=<role> hash=<hash> port=<port>]'."\n"
            );
        }

        $result = $this->di->get('auth')->addUser($params);

        if ($result === true) {
            $user = User::findFirst([['name' => $params['name']]]);

            echo 'Great, a new user was saved successfully!'."\n";
            echo $user->name.' '.$user->email.' '.$user->hash."\n";
        } else {
            echo 'Umh, We can\'t store user right now:'."\n";
            foreach ($result as $message) {
                echo $message, "\n";
            }
        }
    }

    /**
     * Получить данные пользователя либо полные либо тольк по виджету.
     *
     * @example php console user:json <name>
     *
     * @param array $params
     *
     * @return string
     */
    public function jsonAction($params)
    {
        if (count($params) < 1) {
            throw new \Exception('Missing count of an arguments: user:json <name>'."\n");
        }

        $user = User::findFirst([['name' => $params[0]]]);

        if (!$user) {
            throw new \Exception('User with this name is not existing'."\n");
        }

        $data = [
            'name'      => $user->name,
            'email'     => $user->email,
            'createdAt' => $user->createdAt,
            'disabled'  => $user->disabled,
            'port'      => $user->port,
            'config'    => $user->config,
            'data'      => $user->data,
        ];

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Изменить пользователя по имени
     *
     * @example php console user:mod <name> [name=<new_name>|email=<new_email>|pass=<new_pass>|role=<new_role>|hash=<new_hash>|port=<port>]
     *
     * @param array $params
     *
     * @return string
     */
    public function modAction($params)
    {
        if (count($params) < 2) {
            throw new \Exception(
                'Missing count of an arguments: user:mod <name> [name=<new_name>|email=<new_email>|'.
                'pass=<new_pass>|role=<new_role>|hash=<new_hash>|port=<port>]'."\n"
            );
        }

        $user = User::findFirst([['name' => $params[0]]]);

        if (!$user) {
            throw new \Exception('User with this name is not existing'."\n");
        }

            $result = $this->di->get('auth')->editUser($user, $params);

        if ($result === true) {
            echo 'Great, the user was saved successfully!'."\n";
        } else {
            echo 'Umh, We can\'t store user right now:'."\n";
            foreach ($result as $message) {
                echo $message, "\n";
            }
        }
    }

    /**
     * Перегенерировать hash пользователя по имени
     *
     * @example php console user:hash <name>
     *
     * @param array $params
     *
     * @return string
     */
    public function hashAction($params)
    {
        if (count($params) != 1) {
            throw new \Exception('Missing count of an arguments: user:hash <name>'."\n");
        }

        $user = User::findFirst([['name' => $params[0]]]);

        if (!$user) {
            throw new \Exception('User with this name is not existing'."\n");
        }

        $user->generateHash();

        if ($user->save() == false) {
            echo 'Umh, We can\'t store user right now:'."\n";
            foreach ($user->getMessages() as $message) {
                echo $message, "\n";
            }
        } else {
            echo 'Great, the user was saved successfully!'."\n";
        }
    }

    /**
     * Удалить пользователя по имени
     *
     * @example php console user:rem <name>
     *
     * @param array $params
     *
     * @return string
     */
    public function remAction($params)
    {
        if (count($params) != 1) {
            throw new \Exception('Missing count of an arguments: user:rem <name>'."\n");
        }

        $user = User::findFirst([['name' => $params[0]]]);

        if (!$user) {
            throw new \Exception('User with this name is not existing'."\n");
        }

        if ($user->delete() == false) {
            echo 'Sorry, we can\'t delete the user right now:'."\n";
            foreach ($user->getMessages() as $message) {
                echo $message, "\n";
            }
        } else {
            echo 'The user was deleted successfully!'."\n";
        }
    }
}
