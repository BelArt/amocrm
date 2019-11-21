<?php

namespace App\Common\Model;

use Phalcon\Mvc\Model\Validator\Email,
    App\Common\Validation\UniqueValidator,
    App\Common\Validation\NotEmptyValidator;

/**
 * Модель для сущности пользователя (будь то заказчик или админ)
 */
class User extends \Phalcon\Mvc\MongoCollection
{
    /**
     * уникальное имя латиницей (логин) поскольку это же и название папки с логикой заказчика
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $email;

    /**
     * дата создания аккаунта
     *
     * @var timestamp
     */
    public $createdAt;

    /**
     * @var string
     */
    public $pass;

    /**
     * хеш генерируется автоматом на основе $name, $pass и всякой рандомщины
     * сменить можно вручную, а при смене name или email ничего не происходит,
     * а то потом надоест повсюду менять этот хеш
     * @var string
     */
    public $hash;

    /**
     * Обрабатывать или не обрабатывать запросы от пользователя. Если true, то даже
     * в логах не будет никаких хвостов о том, что был запрос (кроме логов nginx).
     *
     * @var bool
     */
    public $disabled;

    /**
     * массив ролей или одна роль строкой для ACL
     * @var array|string
     */
    public $role;

    /**
     * именованный массив токенов для функции remember me вида [<token> => <created_at>]
     * @var array
     */
    public $rem;

    /**
     * именованный массив токенов для восcтановления пароля [<token> => <boolean>]
     * @var array
     */
    public $res;

    /**
     * уникальный порт для каждого пользователя на тот, случай, если им понадобится какой-нибудь сокет
     * @var integer
     */
    public $port;

    /**
     * Конфиг, который мержится с основным массивом самым последним, потому может переопределить всё
     * @var array
     */
    public $config;

    /**
     * дополнительное поле для хранения немногочисленных данных для пользователя, чтобы не создавать целые сущности
     * например, это пригодится для хранения даты парсинга или какого-нибудь (каких-нибудь) id менеджеров и др.
     * @var mixed
     */
    public $data;

    /**
     * Предустанавливаем данные
     *
     * @return void
     */
    public function beforeCreate()
    {
        $this->createdAt = time();
    }

    /**
     * Преобрам все объекты в массивы после присовения данных
     *
     * @return void
     */
    public function afterFetch()
    {
        if (is_object($this->data)) {
            $this->data = $this->objectToArray($this->data);
        }

        if (is_object($this->config)) {
            $this->config = $this->objectToArray($this->config);
        }

        if (is_object($this->res)) {
            $this->res = $this->objectToArray($this->res);
        }

        if (is_object($this->rem)) {
            $this->rem = $this->objectToArray($this->rem);
        }

        if (is_object($this->role)) {
            $this->role = $this->objectToArray($this->role);
        }
    }

    /**
     * Validations and business logic
     */
    public function validation()
    {
        // $this->validate(new NotEmptyValidator([
        //     'field' => 'name',
        //     'message' => 'The name is required'
        // ]));
        //
        // $this->validate(new NotEmptyValidator([
        //     'field' => 'hash',
        //     'message' => 'The hash is required'
        // ]));
        //
        // $this->validate(new UniqueValidator([
        //     'field'  => 'name',
        //     'message' => 'The name is already registered'
        // ]));
        //
        // $this->validate(new UniqueValidator([
        //     'field'  => 'port',
        //     'message' => 'The port is already used',
        //     'can_be_empty' => true
        // ]));

/* т.к. теперь мыло и пароль не обязательны потому что виджет и без них должен работать
        $this->validate(new NotEmptyValidator([
            'field' => 'email',
            'message' => 'The email is required'
        ]));

        $this->validate(new NotEmptyValidator([
            'field' => 'pass',
            'message' => 'The password is required'
        ]));

        $this->validate(new Email([
            'field' => 'email',
            'message' => 'The email is not email'
        ]));
*/

        return true !== $this->validationHasFailed();
    }

    /**
     * генерируем хеш (лучше когда всё остальное заполнено)
     */
    public function generateHash()
    {
        $this->hash = strtolower($this->getDI()->get('security')->getToken(24));
    }

    /**
     * получаем адрес мыла, куда слать копии писем, чтобы они уходили и в amocrm
     */
    public function getEmailCopy()
    {
        return $this->name . '@mail.amocrm.ru';
    }

    /**
     * получаем дату последнего парсинга указанной сущности
     * @param string $key
     * @return \DateTime
     */
    public function getPars($key)
    {
        if ($date = isset($this->pars[$key]) ? $this->pars[$key] : false) {
            return (new \DateTime())->setTimestamp($date);
        }

        return new \DateTime('1970-01-01 00:00:00');
    }

    /**
     * устанавливаем дату последнего парсинга указанной сущности
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setPars($key, $value)
    {
        if (is_string($value) === false) {
            trigger_error('setPars expected Argument 2 to be String', E_USER_WARNING);
        }

        $this->pars[$key] = $value;

        return $this;
    }

    /**
     * Конвертация вложенных массивов во вложенные объекты stdClass
     *
     * @param array $array
     * @return stdClass
     */
    private function arrayToObject($array)
    {
        if (is_array($array)) {
            return (object) array_map([$this, __FUNCTION__], $array);
        } else {
            return $array;
        }
    }

    /**
     * Конвертация вложенных объектов типа stdClass во вложенные массивы
     *
     * @param stdClass $array
     * @return stdClass
     */
    private function objectToArray(\stdClass $object)
    {
        return json_decode(json_encode($object), true);
    }
}
