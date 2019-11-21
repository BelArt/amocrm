<?php

namespace App\Common\Library\Amo;

/**
 * декоратор класса для работы с API amo,
 * который добавляет дополнительный функционал автоматизирующий рутинные задачи
 */
class Amo
{
    /**
     * Константы успешной и неуспешной Сделки в амоСРМ.
     */
    const STATUS_SUCCESS = 142;
    const STATUS_FAIL    = 143;

    /**
     * Константы для типов сущностей
     */
    const ENTITY_TYPE_CONTACT  = 1;
    const ENTITY_TYPE_LEAD     = 2;
    const ENTITY_TYPE_COMPANY  = 3;
    const ENTITY_TYPE_CUSTOMER = 12;

    /**
     * Константы для типов примечаний
     */
    const NOTE_TYPE_COMMON = 4;

    /**
     * Константы для типов задач
     */
    const TASK_TYPE_CALL    = 1;
    const TASK_TYPE_MEETING = 2;

    /**
     * Константы для лейблов сущностей
     */
    const ENTITY_LABEL_CONTACTS         = 'contacts';
    const ENTITY_LABEL_COMPANIES        = 'companies';
    const ENTITY_LABEL_LEADS            = 'leads';
    const ENTITY_LABEL_CUSTOMERS        = 'customers';
    const ENTITY_LABEL_CATALOGS_ELEMENT = 'catalog_elements';

    /**
     * Константы для ENUM телефона или email
     * Взяты из AMOCRM.constant('account')
     */
    const ENUM_WORK              = 'WORK';
    const ENUM_OTHER             = 'OTHER';
    const ENUM_PHONE_FAX         = 'FAX';
    const ENUM_PHONE_HOME        = 'HOME';
    const ENUM_PHONE_MOBILE      = 'MOB';
    const ENUM_PHONE_WORK_DIRECT = 'WORKDD';
    const ENUM_EMAIL_PRIVATE     = 'PRIV';

    /**
     * объект для работы с api amoCRM
     */
    private $amoRestApi;

    /**
     * данные аккаунта
     */
    private $account = null;

    /**
     * Amo constructor.
     *
     * @param AmoRestApi $amoRestApi
     */
    public function __construct(AmoRestApi $amoRestApi)
    {
        $this->amoRestApi = $amoRestApi;
    }

    /**
     * Check authorization in amoCRM
     *
     * @return bool
     * @access public
     */
    public function checkAuthorization()
    {
        try {
            $this->amoRestApi->auth();

            return true;
        } catch (AmoException $e) {
            return false;
        }
    }

    /**
     * Сбросить данных об аккаунте, чтобы позже получить новые,
     * пригодится в долго живущих демонах, чтобы обновлять данные
     *
     * @return void
     */
    public function dropAccount()
    {
        $this->account = null;
    }

    /**
     * Получение данных об аккаунте
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getAccount()
    {
        if (is_null($this->account)) {
            $this->account = $this->amoRestApi->getAccounts()['account'];
        }

        return $this->account;
    }

    /**
     * Получение данных о доп. полях аккаунта
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCustomFields()
    {
        $account = $this->getAccount();

        return isset($account['custom_fields']) ? $account['custom_fields'] : false;
    }

    /**
     * Получение данных о конкретном доп. поле аккаунта по его DI
     *
     * @param int    $id
     * @param string $entity   - сущность, в которой искать: contacts, companies,
     *                         leads, customers или id кастомной сущности.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCustomFieldById($id, $entity = null)
    {
        $customFields = $this->getCustomFields();

        if ($entity && isset($customFields[$entity])) {
            foreach ($customFields[$entity] as $field) {
                if ($field['id'] == $id) {
                    return $field;
                }
            }
        } else {
            foreach ($customFields as $fields) {
                foreach ($fields as $field) {
                    if ($field['id'] == $id) {
                        return $field;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Получение данных о конкретном доп. поле аккаунта по названию
     *
     * @param string $name
     * @param string $entity   - сущность, в которой искать: contacts, companies,
     *                         leads, customers или id кастомной сущности.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCustomFieldByName($name, $entity = null)
    {
        $customFields = $this->getCustomFields();

        if ($entity && isset($customFields[$entity])) {
            foreach ($customFields[$entity] as $field) {
                if ($field['name'] == $name) {
                    return $field;
                }
            }
        } else {
            foreach ($customFields as $fields) {
                foreach ($fields as $field) {
                    if ($field['name'] == $name) {
                        return $field;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Получение данных о конкретном доп. поле аккаунта по его коду. Имеет смысл
     * использовать в основм в компании с названием усщности т.к. некоторые поля
     * имеются как в компании, так и в контакте (phone, email, im). А у сделки
     * подобных полей вообще нет.
     *
     * @param string $code     – код поля (position, phone, email, im, web, address)
     * @param string $entity   - сущность, в которой искать: contacts, companies,
     *                         leads, customers или id кастомной сущности.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCustomFieldByCode($code, $entity = null)
    {
        $customFields = $this->getCustomFields();

        $code = strtoupper($code);

        if ($entity && isset($customFields[$entity])) {
            foreach ($customFields[$entity] as $field) {
                if (strtoupper($field['code']) == $code) {
                    return $field;
                }
            }
        } else {
            foreach ($customFields as $fields) {
                foreach ($fields as $field) {
                    if (strtoupper($field['code']) == $code) {
                        return $field;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Возвращает массив всех воронок с их статусами
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getPipelines()
    {
        $account = $this->getAccount();

        return $account['pipelines'];
    }

    /**
     * Возвращает данные одной указанной воронки
     *
     * @param string|int $pipelineId - ID воронки продаж
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getPipeline($pipelineId)
    {
        foreach ($this->getPipelines() as $pipeline) {
            if ($pipeline['id'] == $pipelineId) {
                return $pipeline;
            }
        }

        return false;
    }

    /**
     * формируем список id всех статусов, что не «успешно реализовано» (142) и «закрыто и реализовано» (143)
     *
     * @param int|array $pipelineId - если указана id или name воронки (или нескольких воронок
     *                              в массиве), то только её (их) статусы, иначе всех воронок
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getStatusesActive($pipelineId = null)
    {
        if (!is_null($pipelineId) && !is_array($pipelineId)) {
            $pipelineId = [$pipelineId];
        }

        $activeStatuses = [];

        foreach ($this->getPipelines() as $pipeline) {
            if (is_null($pipelineId)
                || (is_array($pipelineId)
                    && (in_array($pipeline['id'], $pipelineId)
                        || in_array(
                            $pipeline['name'],
                            $pipelineId
                        )))
            ) {
                foreach ($pipeline['statuses'] as $status) {
                    if (!in_array($status['id'], [142, 143])) {
                        $activeStatuses[] = $status['id'];
                    }
                }
            }
        }

        return $activeStatuses;
    }

    /**
     * Формируем список id всех доступных статусов, в том числе и 142 и 143.
     * Если указанна воронка, то возвращаются статусы только этой воронки.
     *
     * @param int|array $pipelineId - если указана id или name воронки (или нескольких воронок
     *                              в массиве), то только её (их) статусы, иначе всех воронок
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getStatuses($pipelineId = null)
    {
        $statuses = [];

        foreach ($this->getPipelines() as $pipeline) {
            if (is_null($pipelineId)
                || (is_array($pipelineId)
                    && (in_array($pipeline['id'], $pipelineId)
                        || in_array(
                            $pipeline['name'],
                            $pipelineId
                        )))
            ) {
                foreach ($pipeline['statuses'] as $status) {
                    $statuses[] = $status['id'];
                }
            }
        }

        return $statuses;
    }

    /**
     * Ищим самого свободного от сделок пользователя
     *
     * @param array     $status      - массив id статусов, которые нужно учитывать при отборе
     * @param int|array $group       - если необходимо отбирать пользователей из конкретной группы или нескольких групп
     *                               (тогда id будут в массиве)
     * @param boolean   $arrayReturn - форма возврата: true - возврат отсортированного по занятости массива
     *                               пользователей, false - один id самого свободного
     *
     * @throws AmoException
     *
     * @return int|array|false - id самого свободного или false, если пользователей не нашлось
     */
    public function getUserFreest($group = null, array $status = null, $arrayReturn = false)
    {
        $users = $this->getUsers($group);

        if (empty($users)) {
            return false;
        }

        array_walk(
            $users,
            function (&$item) {
                $item = $item['id'];
            }
        );

        // попытаемся отобрать самого свободного (у кого меньше всего Сделок в работе)
        $count = array_fill_keys($users, 0);
        // получаем Сделки людей выше с определёнными статусами
        $leads = $this->getLeads(null, null, null, $users, $status);

        if ($leads) {
            foreach ($leads as $lead) {
                $count[$lead['responsible_user_id']]++;
            }

            asort($count);
        }

        return $arrayReturn ? $count : current(array_keys($count));
    }

    /**
     * Получаем пользователей всех или определённой группы (групп)
     *
     * todo: Можно сделать фильтрацию по правам, передавать массив с требуемыми правами.
     *
     * @param int|array $group - id группы или массив id
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getUsers($group = null)
    {
        $users = $this->getAccount()['users'];

        if (!$users) {
            return false;
        }

        // если указанна группа или массив групп то отфильтруем
        if (!is_null($group)) {
            $group = is_array($group) ? $group : [$group];
            $tmp   = $users;
            $users = [];

            foreach ($tmp as $usr) {
                if (in_array($usr['group_id'], $group)) {
                    $users[] = $usr;
                }
            }
        }

        return $users ? : false;
    }

    /**
     * Получаем данные указанного пользователя
     *
     * @param int $userId - id группы или массив id
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getUser($userId)
    {
        $users = $this->getUsers();

        if (!$users) {
            return false;
        }

        foreach ($users as $user) {
            if ($user['id'] == $userId) {
                return $user;
            }
        }

        return false;
    }

    /**
     * Получаем данные пользователя по почте
     *
     * @param string $email - почта
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getUserByEmail($email)
    {
        $users = $this->getUsers();

        if (!$users) {
            return false;
        }

        foreach ($users as $user) {
            if (strtolower($user['login']) == strtolower($email)) {
                return $user;
            }
        }

        return false;
    }

    /**
     * Получаем список групп, решается такая проблема, что amoCRM не возвращает
     * id и названия главной первичной группы, у которой всегда одини и те же
     * название и id.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getGroups()
    {
        $groups = $this->getAccount()['groups'];

        if (!$groups) {
            return false;
        }

        $groups = array_merge(
            $groups,
            [
                [
                    'id'   => 0,
                    'name' => 'Отдел продаж',
                ],
            ]
        );

        return $groups;
    }

    /**
     * получить все контакты по сделке
     *
     * @param int|array $id
     * @param string    $query
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getContactsByLead($id, $query = null)
    {
        $links      = $this->getLinks(null, null, !is_array($id) ? [$id] : $id, 2);
        $contactIds = [];

        if (!$links) {
            return false;
        }

        foreach ($links as $link) {
            $contactIds[] = $link['contact_id'];
        }

        if (empty($contactIds)) {
            return false;
        }

        $chunks   = array_chunk($contactIds, 350);
        $contacts = [];

        foreach ($chunks as $chunk) {
            $contactsChunks = $this->amoRestApi->getContactsList(null, null, $chunk, $query);

            if ($contactsChunks && isset($contactsChunks['contacts']) && count($contactsChunks['contacts']) > 0) {
                $contacts = array_merge($contacts, $contactsChunks['contacts']);
            }
        }

        if ($contacts) {
            return $contacts;
        }

        return false;
    }

    /**
     * Получить только одного покупателя по id.
     *
     * @param int|array $id
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCustomer($id)
    {
        $parameters = [];

        if (!is_null($id)) {
            $parameters['id'] = $id;
        }
        $entities = $this->amoRestApi->getCustomersList($parameters);

        if ($entities && isset($entities['customers']) && count($entities['customers'])) {
            return array_shift($entities['customers']);
        }

        return false;
    }

    /**
     * Добавляем только одного покупателя.
     *
     * @param string           $name        Имя покупателя
     * @param string|\DateTime $date        Дата следующей покупки в формате timestamp
     * @param string           $user        Id отвественного пользователя
     * @param string           $price       Ожидаемая сумма покупки
     * @param string           $periodicity Периодичность покупки
     * @param string|array     $tags        Теги покупателя через запятую
     * @param array            $customFields
     *
     * @return int
     * @throws AmoException
     */
    public function addCustomer(
        $name,
        $date,
        $user = null,
        $price = null,
        $periodicity = null,
        $tags = null,
        $customFields = []
    ) {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $request = [
            'name'          => $name,
            'next_date'     => ($date instanceof \DateTime) ? $date->format('U') : $date,
            'main_user_id'  => $user,
            'next_price'    => $price,
            'periodicity'   => $periodicity,
            'tags'          => is_array($tags) ? implode(', ', $tags) : $tags,
            'custom_fields' => $newCustomFields,
        ];

        $customers['request']['customers']['add'] = [$request];

        $response = $this->amoRestApi->setCustomers($customers);

        if ($response && isset($response['customers']['add']['customers'][0]['id'])) {
            return (int)$response['customers']['add']['customers'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Обновление покупателя
     *
     * @param string|int $id           - Id Покупателя
     * @param string     $name         - Название покупателя
     * @param \DateTime  $date         - Дата следующей покупки
     * @param string|int $user         - Ответсвенный пользователь
     * @param string|int $price        - Следующая цена
     * @param array      $customFields - Дополнительные поля
     * @param array      $tags         - Теги
     * @param null       $periodicity  - Переодичность
     *
     * @throws AmoException
     *
     * @return array|bool
     */
    public function updateCustomer(
        $id,
        $name,
        \DateTime $date,
        $user,
        $price,
        $customFields = [],
        $tags = [],
        $periodicity = null
    ) {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $nowTimestamp = time();

        if (is_array($id)) {
            $request = [
                'id'            => $id['id'],
                'last_modified' => ($id['last_modified'] > $nowTimestamp) ? $id['last_modified'] + 1 : $nowTimestamp,
            ];
        } else {
            $request = [
                'id'            => $id,
                'last_modified' => $nowTimestamp,
            ];
        }

        if (!is_null($name)) {
            $request['name'] = $name;
        }

        if (!is_null($date) && $date instanceof \DateTime) {
            $request['next_date'] = $date->format('U');
        }

        if (!is_null($price)) {
            $request['next_price'] = $price;
        }

        if ($newCustomFields) {
            $request['custom_fields'] = $newCustomFields;
        }

        if (is_array($tags)) {
            $request['tags'] = $tags ? implode(', ', $tags) : false;
        }

        if ($user) {
            $request['main_user_id'] = $user;
        }

        if ($periodicity) {
            $request['periodicity'] = $periodicity;
        }

        $customers['request']['customers']['update'] = [$request];

        return $this->amoRestApi->setCustomers($customers);
    }

    /**
     * Удаляем одного или несколько покупателей.
     *
     * @param array|int $id - Id Покупателей для удаления.
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function deleteCustomer($id)
    {
        $catalog['request']['customers']['delete'] = is_array($id) ? $id : [$id];

        $response = $this->amoRestApi->setCustomers($catalog);

        // Тут в ключе находится id сущности
        if ($response && isset($response['customers']['delete']['customers'])
            && count(
                $response['customers']['delete']['customers']
            )) {
            return is_array($id)
                ? $response['customers']['delete']['customers']
                : array_shift(
                      $response['customers']['delete']['customers']
                  )['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Добавление связи для покупателей.
     *
     * @param string $from - сущность, к которой осуществленна привязка (leads, contacts, companies, customers)
     * @param int    $fromId
     * @param int    $toId
     * @param int    $quantity
     *
     * @deprecated
     *
     * @return int|array
     * @throws AmoException
     */
    public function addCustomerElementLink($from, $fromId, $toId, $quantity = null)
    {
        $request = [
            'from'    => $from,
            'from_id' => $fromId,
            'to'      => 'customers',
            'to_id'   => $toId,
        ];

        if ($quantity) {
            $request['quantity'] = $quantity;
        }

        $links['request']['links']['link'] = [$request];

        $response = $this->amoRestApi->setLinks($links);

        // Заодно обрабатываем error, если есть
        if ($response && isset($response['links']['link']['links'])) {
            if (isset($response['links']['link']['errors'])
                && count($response['links']['link']['errors'])
            ) {
                throw new AmoException(
                    'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Error: ' .
                    array_shift($response['links']['link']['errors'])['message'], 500
                );
            } else {
                return array_shift($response['links']['link']['links'])['from_id'];
            }
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Получить все покупки покупателя
     *
     * @param $customerId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCustomerTransactions($customerId)
    {
        $request = [];

        if ($customerId) {
            $request = ['filter' => ['customer_id' => $customerId]];
        }

        $result = $this->amoRestApi->getCustomersTransactionsList($request);

        if (isset($result['transactions']) && is_array($result['transactions'])) {
            return $result['transactions'];
        } else {
            throw new AmoException('Something goes wrong ' . print_r($result, true));
        }
    }

    /**
     * Получить все покупки покупателя
     *
     * @param        $customerId
     *
     * @param        $price
     * @param string $comment
     * @param null   $date
     *
     * @return array
     * @throws AmoException
     */
    public function addCustomerTransaction($customerId, $price, $comment = '', $date = null)
    {
        $request = [
            'customer_id' => $customerId,
            'price'       => $price,
            'comment'     => $comment,
            'date'        => ($date instanceOf \DateTime) ? $date->format('U') : (new \DateTime('now'))->format('U'),
        ];

        $result = $this->amoRestApi->setCustomersTransactionsList(
            ['request' => ['transactions' => ['add' => [$request]]]]
        );

        if (isset($result['transactions']['add']['errors'], $result['transactions']['add']['transactions'])
            && is_array(
                $result['transactions']['add']['errors']
            )) {
            if (count($result['transactions']['add']['errors'])) {
                throw new AmoException(
                    'Не смогли добавить покупку: ' . implode(
                        "\n\n",
                        $result['transactions']['add']['errors']
                    )
                );
            }

            return $result['transactions']['add']['transactions'];
        } else {
            throw new AmoException('Something goes wrong ' . print_r($result, true));
        }
    }

    /**
     * Получить только один каталога по id
     *
     * @param int $id
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCatalog($id)
    {
        $entities = $this->amoRestApi->getCatalogsList($id);

        if ($entities && isset($entities['catalogs']) && count($entities['catalogs'])) {
            return array_shift($entities['catalogs']);
        }

        return false;
    }

    /**
     * Получение всего списка каталогов без разбивки на 500 т.к. столько много каталогов
     * в одном аккаунте не за чем.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCatalogs()
    {
        $entities = $this->amoRestApi->getCatalogsList();

        if ($entities && isset($entities['catalogs'])) {
            return $entities['catalogs'];
        }

        return false;
    }

    /**
     * Добавляем только один каталог
     *
     * @param string $name
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function addCatalog($name)
    {
        $request = [
            'name' => $name,
        ];

        $catalog['request']['catalogs']['add'] = [$request];

        $response = $this->amoRestApi->setCatalogs($catalog);

        if ($response && isset($response['catalogs']['add']['catalogs'][0]['id'])) {
            return $response['catalogs']['add']['catalogs'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Получить все элементы только одного каталога по id
     *
     * @param int    $catalogId
     * @param int    $id
     * @param string $term
     * @param string $orderBy   - сортировка по name, date_create, date_modify
     * @param string $orderType - направление сортировки: ASC, DESC
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCatalogElements($catalogId, $id = null, $term = null, $orderBy = null, $orderType = null)
    {
        $entities = [];
        $pagen    = 1;
        $end      = false;

        do {
            $response = $this->amoRestApi->getCatalogElementsList($catalogId, $id, $term, $orderBy, $orderType, $pagen);

            if ($response && isset($response['catalog_elements']) && count($response['catalog_elements'])) {
                $entities = array_merge($entities, $response['catalog_elements']);
                $pagen++;
            } else {
                $end = true;
            }
        } while (!$end);

        return $entities;
    }

    /**
     * Добавление одного элемента каталога
     *
     * @param int    $catalogId    - ID каталога
     * @param string $name         - Имя элемента
     * @param array  $customFields - Дополнительные поля
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function addCatalogElement($catalogId, $name, array $customFields = [])
    {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $request = [
            'catalog_id' => $catalogId,
            'name'       => $name,
        ];

        if ($newCustomFields) {
            $request['custom_fields'] = $newCustomFields;
        }

        $catalogElements['request']['catalog_elements']['add'] = [$request];

        $response = $this->amoRestApi->setCatalogElements($catalogElements);

        // Заодно обрабатываем error, если есть
        if ($response && isset($response['catalog_elements']['add']['catalog_elements'])) {
            if (isset($response['catalog_elements']['add']['errors'])
                && count($response['catalog_elements']['add']['errors'])
            ) {
                throw new AmoException(
                    'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Error: ' .
                    array_shift($response['catalog_elements']['add']['errors'])['message'], 500
                );
            } else {
                return array_shift($response['catalog_elements']['add']['catalog_elements'])['id'];
            }
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Обновление одного элемента каталога
     *
     * @param int    $catalogId
     * @param int    $id
     * @param string $name – при обновлении название обязательно должно быть >_<
     * @param array  $customFields
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function updateCatalogElement($catalogId, $id, $name, array $customFields = [])
    {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $request = [
            'catalog_id' => $catalogId,
            'id'         => $id,
        ];

        if ($name) {
            $request['name'] = $name;
        }

        if ($newCustomFields) {
            $request['custom_fields'] = $newCustomFields;
        }

        $catalogElements['request']['catalog_elements']['update'] = [$request];

        $response = $this->amoRestApi->setCatalogElements($catalogElements);

        // Тут в ключе находится id сущности и заодно обрабатываем error, если есть
        if ($response && isset($response['catalog_elements']['update']['catalog_elements'])) {
            if (isset($response['catalog_elements']['update']['errors'])
                && count($response['catalog_elements']['update']['errors'])
            ) {
                throw new AmoException(
                    'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Error: ' .
                    array_shift($response['catalog_elements']['update']['errors'])['message'], 500
                );
            } else {
                return array_shift($response['catalog_elements']['update']['catalog_elements'])['id'];
            }
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Удаляем один или несколько элементов каталога
     *
     * @param int|array $id
     *
     * @throws AmoException
     *
     * @return int|array
     */
    public function deleteCatalogElement($id)
    {
        $catalog['request']['catalog_elements']['delete'] = is_array($id) ? $id : [$id];

        $response = $this->amoRestApi->setCatalogElements($catalog);

        // Тут в ключе находится id сущности и заодно обрабатываем error, если есть
        if ($response && isset($response['catalog_elements']['delete']['catalog_elements'])) {
            if (isset($response['catalog_elements']['delete']['errors'])
                && count($response['catalog_elements']['delete']['errors'])
            ) {
                throw new AmoException(
                    'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Error: ' .
                    array_shift($response['catalog_elements']['delete']['errors'])['message'], 500
                );
            } else {
                return array_shift($response['catalog_elements']['delete']['catalog_elements'])['id'];
            }
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Получение cвязей между сущностями
     *
     * @param string $from – сущность, к которой осуществленна привязка (leads, contacts, companies, customers)
     * @param int    $fromId
     * @param int    $toCatalogId
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCatalogElementsLinks($from, $fromId, $toCatalogId)
    {
        $entities = $this->amoRestApi->getLinks($from, $fromId, 'catalog_elements', $toCatalogId);

        if ($entities && isset($entities['links']) && count($entities['links'])) {
            return $entities['links'];
        }

        return false;
    }

    /**
     * Добавление связи "сущность - элемент кастомной сущности"
     *
     * @param string $from - сущность, к которой осуществленна привязка (leads, contacts, companies, customers)
     * @param int    $fromId
     * @param int    $toCatalogId
     * @param int    $toId
     * @param int    $quantity
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function addCatalogElementLink($from, $fromId, $toCatalogId, $toId, $quantity = null)
    {
        $request = [
            'from'          => $from,
            'from_id'       => $fromId,
            'to'            => 'catalog_elements',
            'to_catalog_id' => $toCatalogId,
            'to_id'         => $toId,
        ];

        if ($quantity) {
            $request['quantity'] = $quantity;
        }

        $links['request']['links']['link'] = [$request];

        $response = $this->amoRestApi->setLinks($links);

        // Заодно обрабатываем error, если есть
        if ($response && isset($response['links']['link']['links'])) {
            if (isset($response['links']['link']['errors'])
                && count($response['links']['link']['errors'])
            ) {
                throw new AmoException(
                    'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Error: ' .
                    array_shift($response['links']['link']['errors'])['message'], 500
                );
            } else {
                return array_shift($response['links']['link']['links'])['from_id'];
            }
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Удаление связи "сущность - элемент кастомной сущности"
     *
     * @param string $from - сущность, к которой осуществленна привязка (leads, contacts, companies, customers)
     * @param int    $fromId
     * @param int    $toCatalogId
     * @param int    $toId
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function deleteCatalogElementLink($from, $fromId, $toCatalogId, $toId)
    {
        $request = [
            'from'          => $from,
            'from_id'       => $fromId,
            'to'            => 'catalog_elements',
            'to_catalog_id' => $toCatalogId,
            'to_id'         => $toId,
        ];

        $links['request']['links']['unlink'] = [$request];

        $response = $this->amoRestApi->setLinks($links);

        // Заодно обрабатываем error, если есть
        if ($response && isset($response['links']['unlink']['links'])) {
            if (isset($response['links']['unlink']['errors'])
                && count($response['links']['unlink']['errors'])
            ) {
                throw new AmoException(
                    'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Error: ' .
                    array_shift($response['links']['unlink']['errors'])['message'], 500
                );
            } else {
                return array_shift($response['links']['unlink']['links'])['id'];
            }
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Обновляем только один каталог
     *
     * @param int    $id
     * @param string $name
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function updateCatalog($id, $name)
    {
        $request = [
            'id'   => $id,
            'name' => $name,
        ];

        $catalog['request']['catalogs']['update'] = [$request];

        $response = $this->amoRestApi->setCatalogs($catalog);

        // Тут в ключе находится id сущности
        if ($response && isset($response['catalogs']['update']['catalogs'])
            && count(
                $response['catalogs']['update']['catalogs']
            )) {
            return array_shift($response['catalogs']['update']['catalogs'])['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Удаляем один или несколько каталогов
     *
     * @param int|array $id
     *
     * @throws AmoException
     *
     * @return int|array
     */
    public function deleteCatalog($id)
    {
        $catalog['request']['catalogs']['delete'] = is_array($id) ? $id : [$id];

        $response = $this->amoRestApi->setCatalogs($catalog);

        // Тут в ключе находится id сущности
        if ($response && isset($response['catalogs']['delete']['catalogs'])
            && count(
                $response['catalogs']['delete']['catalogs']
            )) {
            return is_array($id)
                ? $response['catalogs']['delete']['catalogs']
                : array_shift(
                      $response['catalogs']['delete']['catalogs']
                  )['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * получить все компании по сделке
     *
     * @param int|array $id
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getCompaniesByLead($id)
    {
        $lead = $this->getLead($id);

        if (!$lead || !isset($lead['linked_company_id']) || !$lead['linked_company_id']) {
            return false;
        }

        return $this->getCompany($lead['linked_company_id']);
    }

    /**
     * получить все сделки по контакту
     *
     * @param int|array $id
     * @param int|array $status - id статусов сделок, которые стоит подцепить
     * @param string    $query
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getLeadsByContacts($id, $status = [], $query = null)
    {
        $links   = $this->getLinks(null, null, !is_array($id) ? [$id] : $id, 1);
        $leadIds = [];

        if (!$links) {
            return false;
        }

        foreach ($links as $link) {
            $leadIds[] = $link['lead_id'];
        }

        if (empty($leadIds)) {
            return false;
        }

        $chunks = array_chunk($leadIds, 350);
        $leads  = [];

        foreach ($chunks as $chunk) {
            $leadsChunks = $this->amoRestApi->getLeadsList(null, null, $chunk, null, null, null);

            if ($leadsChunks && isset($leadsChunks['leads']) && count($leadsChunks['leads']) > 0) {
                // Так как если есть параметр id в запросе, все остальные игнорируются
                // добавляем свои проверки

                // Проверка соответсвия статусам
                if (!empty($status)) {
                    $filterLeads = [];
                    $status      = !is_array($status) ? [$status] : $status;

                    foreach ($leadsChunks['leads'] as $lead) {
                        if (in_array($lead['status_id'], $status)) {
                            $filterLeads[] = $lead;
                        }
                    }

                    $leadsChunks['leads'] = $filterLeads;
                }

                // Проверка есть ли подстрока в доп полях
                if ($query) {
                    $filterLeads = [];

                    foreach ($leadsChunks['leads'] as $lead) {
                        foreach ($lead['custom_fields'] as $customField) {
                            foreach ($customField['values'] as $values) {
                                if (stripos($values['value'], $query) !== false) {
                                    $filterLeads[] = $lead;
                                }
                            }
                        }
                    }

                    $leadsChunks['leads'] = $filterLeads;
                }

                if (count($leadsChunks['leads']) > 0) {
                    $leads = array_merge($leads, $leadsChunks['leads']);
                }
            }
        }

        if ($leads) {
            return $leads;
        }

        return false;
    }

    /**
     * получить только одну сделку по id
     *
     * @param int|array $id
     * @param array     $user
     * @param array     $status
     *
     * @throws AmoException
     *
     * @return array|false - если запросили в $id одного, то вернём только его данные, если массив $id, то вернём массив
     */
    public function getLead($id, $user = [], $status = [])
    {
        $leads = $this->amoRestApi->getLeadsList(1, null, !is_array($id) ? [$id] : $id, null, $user, $status);
        if ($leads && isset($leads['leads'][0])) {
            return $leads['leads'][0];
        }

        return false;
    }

    /**
     * Получаем сделки не учитывая лимита amocrm в 500 строк,
     * прозрачно происходят ещё запросы в amocrm, если нужно больше 500
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param string    $query
     * @param string    $responsible
     * @param string    $status
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getLeads(
        $limitRows = null,
        $limitOffset = null,
        $query = null,
        $responsible = null,
        $status = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $maxLimit = 350; // максимально возможная выборка строк (ограничено amocrm), она же шаг
        $end      = false;
        $entities = [];
        // определяем первоначальное смещение
        $offset = $limitOffset ? : 0;
        $limit  = null;

        do {
            // определяем сколько заправшивать в этой итерации
            if (is_null($limitRows)) {
                $limit = $maxLimit; // если лимит не указан, то берём максимально возможное число записей
            } elseif ($limitRows <= $maxLimit) {
                $limit = $limitRows;
            } else {
                $limitRows -= $maxLimit;
                $limit     = $maxLimit;
            }

            $response = $this->amoRestApi->getLeadsList(
                $limit,
                $offset,
                null,
                $query,
                $responsible,
                $status,
                $dateModified,
                $dateCreateFrom,
                $dateCreateTo
            );

            if ($response && isset($response['leads']) && count($response['leads']) > 0) {
                $entities = array_merge($entities, $response['leads']);
                if (count($response['leads']) < $maxLimit) {
                    $end = true;
                } else {
                    $offset += $limit;
                }
            } else {
                $end = true;
            }
        } while (!$end);

        return $entities;
    }

    /**
     * Обновить только одну сделку.
     *
     * @param int|array $id           Id сделки или вся Сделка сразу. При передаче всей сделки из
     *                                нее берется last_modified и инкрементируется на 1 (использовать
     *                                при ошибке "Last modified date is older than in database").
     * @param string    $name
     * @param int|array $status       - если требуется статус конкретной воронки, то указываем
     *                                массив с ключ-значение: [<pipline_id> => <status_id>]
     * @param int       $price
     * @param int       $user
     * @param array     $customFields - массив массивов вида [id => [value, enum, subtype]]
     * @param array     $tags
     *
     * @throws AmoException
     *
     * @return int|array
     */
    public function updateLead(
        $id,
        $name = null,
        $status = null,
        $price = null,
        $user = null,
        array $customFields = [],
        $tags = null
    ) {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $nowTimestamp = (new \DateTime('now'))->format('U');

        if (is_array($id)) {
            $request = [
                'id'            => $id['id'],
                'last_modified' => ($id['last_modified'] > $nowTimestamp) ? $id['last_modified'] + 1 : $nowTimestamp,
            ];
        } else {
            $request = [
                'id'            => $id,
                'last_modified' => $nowTimestamp,
            ];
        }

        if (!is_null($name)) {
            $request['name'] = $name;
        }

        if (!is_null($status)) {
            if (is_array($status)) {
                $request['pipeline_id'] = key($status);
                $request['status_id']   = reset($status);
            } else {
                $request['status_id'] = $status;
            }
        }

        if (!is_null($price)) {
            $request['price'] = $price;
        }

        if ($newCustomFields) {
            $request['custom_fields'] = $newCustomFields;
        }

        if (is_array($tags)) {
            $request['tags'] = $tags ? implode(', ', $tags) : false;
        }

        if ($user) {
            $request['responsible_user_id'] = $user;
        }

        $lead['request']['leads']['update'] = [$request];

        $response = $this->amoRestApi->setLeads($lead);

        return $response;
    }

    /**
     * Добавляем только одну Сделку
     *
     * @param string    $name
     * @param int|array $status       - если требуется статус конкретной воронки, то указываем
     *                                массив с ключ-значение: [<pipline_id> => <status_id>]
     * @param int       $price
     * @param int       $user
     * @param array     $customFields - массив массивов вида [id => [value, enum, subtype]]
     * @param array     $tags
     *
     * @throws AmoException
     *
     * @return int
     */
    public function addLead(
        $name,
        $status = null,
        $price = null,
        $user = null,
        array $customFields = [],
        array $tags = []
    ) {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $request = [
            'name'                => $name,
            'price'               => $price,
            'responsible_user_id' => $user,
            'custom_fields'       => $newCustomFields,
            'tags'                => $tags ? implode(', ', $tags) : null,
        ];

        if (is_array($status)) {
            $request['pipeline_id'] = key($status);
            $request['status_id']   = reset($status);
        } else {
            $request['status_id'] = $status;
        }

        $lead['request']['leads']['add'] = [$request];

        $response = $this->amoRestApi->setLeads($lead);

        // получим id только что добавленной сделки
        if ($response && isset($response['leads']['add'][0]) && isset($response['leads']['add'][0]['id'])) {
            return (int)$response['leads']['add'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * добавляем только один контакт
     *
     * @param string     $name
     * @param array      $leads
     * @param int|string $company      - Можно передавать как id компании (int), так и имя(string)
     * @param int        $user
     * @param array      $customFields - массив массивов вида [id => [value, enum, subtype]]
     * @param array      $tags
     *
     * @throws AmoException
     *
     * @return int
     */
    public function addContact(
        $name,
        $leads = [],
        $company = null,
        $user = null,
        array $customFields = [],
        array $tags = []
    ) {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $request = [
            'name' => $name,
        ];

        if ($leads) {
            $request['linked_leads_id'] = is_array($leads) ? $leads : [$leads];
        }
        if ($company) {
            if (is_int($company)) {
                $request['linked_company_id'] = $company;
            } else {
                $request['company_name'] = $company;
            }
        }
        if ($newCustomFields) {
            $request['custom_fields'] = $newCustomFields;
        }
        if ($tags) {
            $request['tags'] = $tags ? implode(', ', $tags) : null;
        }
        if ($user) {
            $request['responsible_user_id'] = $user;
        }

        $contact['request']['contacts']['add'] = [$request];

        $response = $this->amoRestApi->setContacts($contact);

        // получим id только что добавленной сделки
        if ($response && isset($response['contacts']['add'][0]) && isset($response['contacts']['add'][0]['id'])) {
            return (int)$response['contacts']['add'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Обновляем только один контакт.
     *
     * @param int|array $id           Id Контакта или весь Контакт сразу. При передаче Контакта из
     *                                него берется last_modified и инкрементируется на 1 (использовать
     *                                при ошибке "Last modified date is older than in database").
     * @param string    $name
     * @param array     $leads
     * @param int       $company
     * @param int       $user
     * @param array     $customFields - массив массивов вида [id => [value, enum, subtype]]
     * @param array     $tags
     *
     * @throws AmoException
     *
     * @return array
     */
    public function updateContact(
        $id,
        $name = null,
        $leads = [],
        $company = null,
        $user = null,
        array $customFields = [],
        $tags = null
    ) {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $nowTimestamp = (new \DateTime('now', new \DateTimeZone('Europe/Moscow')))->format('U');

        if (is_array($id)) {
            $request = [
                'id'            => $id['id'],
                'last_modified' => ($id['last_modified'] > $nowTimestamp) ? $id['last_modified'] + 1 : $nowTimestamp,
            ];
        } else {
            $request = [
                'id'            => $id,
                'last_modified' => $nowTimestamp,
            ];
        }

        if ($name) {
            $request['name'] = $name;
        }
        if ($leads) {
            $request['linked_leads_id'] = is_array($leads) ? $leads : [$leads];
        }
        if ($company) {
            if (is_int($company)) {
                $request['linked_company_id'] = $company;
            } else {
                $request['company_name'] = $company;
            }
        }
        if ($newCustomFields) {
            $request['custom_fields'] = $newCustomFields;
        }
        if (is_array($tags)) {
            $request['tags'] = $tags ? implode(', ', $tags) : false;
        }
        if ($user) {
            $request['responsible_user_id'] = $user;
        }

        $contact['request']['contacts']['update'] = [$request];

        $response = $this->amoRestApi->setContacts($contact);

        return $response;
    }

    /**
     * Добавляем Сделки к Контактам (связываем их).
     *
     * @deprecated Используйте linkContactWithLeads() или linkLeadWithContacts() вместо
     *
     * @param int|array $contactId Id Контакта или весь Контакт сразу.
     * @param int|array $leadIds   Массив Id Сделок.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function updateContactAddLeads($contactId, $leadIds)
    {
        if (!$leadIds) {
            return false;
        }

        $leadIds = is_array($leadIds) ? $leadIds : [$leadIds];

        $links = $this->getLinks(null, null, [(is_array($contactId) ? $contactId['id'] : $contactId)], 1);

        if ($links) {
            foreach ($links as $link) {
                if (array_search($link['lead_id'], $leadIds) === false) {
                    $leadIds[] = $link['lead_id'];
                }
            }
        }

        return $this->updateContact($contactId, null, $leadIds);
    }

    /**
     * Удаляем указанные Сделки из Контакта (отвязываем их).
     *
     * @param int|array $contactId Id Контакта или весь Контакт сразу.
     * @param array     $leadIds   Массив Id Сделок.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function updateContactRemoveLeads($contactId, $leadIds)
    {
        if (!$leadIds) {
            return false;
        }

        $leadIds = is_array($leadIds) ? $leadIds : [$leadIds];

        $links = $this->getLinks(null, null, [(is_array($contactId) ? $contactId['id'] : $contactId)], 1);

        if ($links) {
            $ids = [];
            foreach ($links as $link) {
                if (array_search($link['lead_id'], $ids) === false) {
                    $ids[] = $link['lead_id'];
                }
            }

            if ($ids) {
                return $this->updateContact($contactId, null, array_diff($ids, $leadIds));
            }
        }

        return false;
    }

    /**
     * Добавляем сделки к компаниям (связываем их)
     *
     * @param int|array $companyId Id Компании или вся Компания сразу.
     * @param array     $leadIds   Массив Id Сделок.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function updateCompanyAddLeads($companyId, $leadIds)
    {
        if (!$leadIds) {
            return false;
        }

        $leadIds = is_array($leadIds) ? $leadIds : [$leadIds];

        $company = $this->getCompany((is_array($companyId) ? $companyId['id'] : $companyId));

        if (isset($company['linked_leads_id'])) {
            foreach ($company['linked_leads_id'] as $laedId) {
                if (array_search($laedId, $leadIds) === false) {
                    $leadIds[] = $laedId;
                }
            }
        }

        return $this->updateCompany($companyId, null, $leadIds);
    }

    /**
     * Удаляем указанные сделки из компании (отвязываем их).
     *
     * @param int|array $companyId Id Компании или вся Компания сразу.
     * @param array     $leadIds   Массив Id Сделок.
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function updateCompanyRemoveLeads($companyId, $leadIds)
    {
        if (!$leadIds) {
            return false;
        }

        $leadIds = is_array($leadIds) ? $leadIds : [$leadIds];

        $company = $this->getCompany((is_array($companyId) ? $companyId['id'] : $companyId));

        if (isset($company['linked_leads_id'])) {
            $ids = [];
            foreach ($company['linked_leads_id'] as $leadId) {
                if (array_search($leadId, $ids) === false) {
                    $ids[] = $leadId;
                }
            }

            if ($ids) {
                return $this->updateCompany($companyId, null, array_diff($ids, $leadIds));
            }
        }

        return false;
    }

    /**
     * получить только один контакт по id
     *
     * @param int|array $id
     * @param array     $user
     * @param string    $type - тип контакта: contact(по-умолчанию), company или all
     *
     * @throws AmoException
     *
     * @return array|false - если запросили в $id одного, то вернём только его данные, если массив $id, то вернём массив
     */
    public function getContact($id, $user = [], $type = 'contact')
    {
        $contacts = $this->amoRestApi->getContactsList(1, null, !is_array($id) ? [$id] : $id, null, $user, $type);
        if ($contacts && isset($contacts['contacts'][0])) {
            return $contacts['contacts'][0];
        }

        return false;
    }

    /**
     * Получаем контакты не учитывая лимита amocrm в 500 строк,
     * прозрачно происходят ещё запросы в amocrm, если нужно больше 500
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param string    $query
     * @param string    $responsible
     * @param string    $type - contact (по-умолчанию), company или all
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getContacts(
        $limitRows = null,
        $limitOffset = null,
        $query = null,
        $responsible = null,
        $type = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $maxLimit = 350; // максимально возможная выборка строк (ограничено amocrm), она же шаг
        $end      = false;
        $entities = [];
        // определяем первоначальное смещение
        $offset = $limitOffset ? : 0;
        $limit  = null;

        do {
            // определяем сколько заправшивать в этой итерации
            if (is_null($limitRows)) {
                $limit = $maxLimit; // если лимит не указан, то берём максимально возможное число записей
            } elseif ($limitRows <= $maxLimit) {
                $limit = $limitRows;
            } else {
                $limitRows -= $maxLimit;
                $limit     = $maxLimit;
            }

            $response = $this->amoRestApi->getContactsList(
                $limit,
                $offset,
                null,
                $query,
                $responsible,
                $type,
                $dateModified,
                $dateCreateFrom,
                $dateCreateTo
            );

            if ($response && isset($response['contacts']) && count($response['contacts']) > 0) {
                $entities = array_merge($entities, $response['contacts']);
                if (count($response['contacts']) < $maxLimit) {
                    $end = true;
                } else {
                    $offset += $limit;
                }
            } else {
                $end = true;
            }
        } while (!$end);

        return $entities;
    }

    /**
     * Получение всего списка виджетов или конкретных виджетов по code или id.
     *
     * @param int|array $widgetCodes
     * @param int|array $widgetIds
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getWidgets($widgetCodes = null, $widgetIds = null)
    {
        $entities = $this->amoRestApi->getWidgetsList($widgetCodes, $widgetIds);

        if ($entities && isset($entities['widgets'])) {
            return $entities['widgets'];
        }

        return false;
    }

    /**
     * Получение одного виджета по его коду.
     *
     * @param int $widgetCode
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getWidgetByCode($widgetCode)
    {
        $entities = $this->getWidgets($widgetCode);

        if ($entities) {
            return array_shift($entities);
        }

        return false;
    }

    /**
     * Получение одного виджета по его id.
     *
     * @param int $widgetId
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getWidgetById($widgetId)
    {
        $entities = $this->getWidgets(null, $widgetId);

        if ($entities) {
            return array_shift($entities);
        }

        return false;
    }

    /**
     * Установка одного виджета
     *
     * @param string $widgetCode
     * @param int    $widgetId
     * @param array  $settings
     *
     * @throws AmoException
     *
     * @return string
     */
    public function installWidget($widgetCode = null, $widgetId = null, $settings = null)
    {
        if (!$widgetCode && !$widgetId) {
            trigger_error(
                'Method ' . __METHOD__ . ' of ' . __CLASS__ . ' Argument 1 or Argument 2 is required',
                E_USER_WARNING
            );
        }

        $request = [];

        if ($widgetCode) {
            $request['widget_code'] = $widgetCode;
        }

        if ($widgetId) {
            $request['widget_id'] = $widgetId;
        }

        if ($settings) {
            $request['settings'] = $settings;
        }

        $widget['request']['widgets']['install'] = [$request];

        $response = $this->amoRestApi->setWidgets($widget);

        if ($response && isset($response['widgets']['install'])) {
            return (string)$response['widgets']['install'][0]['widget_code'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Дизактивируем однин виджет
     *
     * @param string $widgetCode
     * @param int    $widgetId
     * @param array  $settings
     *
     * @throws AmoException
     *
     * @return string
     */
    public function uninstallWidget($widgetCode, $widgetId, $settings = null)
    {
        if (!$widgetCode && !$widgetId) {
            trigger_error(
                'Method ' . __METHOD__ . ' of ' . __CLASS__ . ' Argument 1 or Argument 2 is required',
                E_USER_WARNING
            );
        }

        $request = [];

        if ($widgetCode) {
            $request['widget_code'] = $widgetCode;
        }

        if ($widgetId) {
            $request['widget_id'] = $widgetId;
        }

        if ($settings) {
            $request['settings'] = $settings;
        }

        $widget['request']['widgets']['uninstall'] = [$request];

        $response = $this->amoRestApi->setWidgets($widget);

        if ($response && isset($response['widgets']['uninstall'])) {
            return (string)$response['widgets']['uninstall'][0]['widget_code'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Получаем контакты по их id без ограничения в 500 строк,
     * прозрачно происходят ещё запросы в amocrm, если нужно больше 500.
     *
     * TODO по большому счёту это надо смержить с getContacts(), но они вроде о разном
     *
     * @param array     $ids
     * @param string    $query
     * @param string    $responsible
     * @param string    $type - contact (по-умолчанию), company или all
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getContactsByIds(
        array $ids,
        $query = null,
        $responsible = null,
        $type = 'contact',
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        if (empty($ids)) {
            return false;
        }

        $entities = [];

        foreach (array_chunk($ids, 350) as $chunk) {
            $response = $this->amoRestApi->getContactsList(
                null,
                null,
                $chunk,
                $query,
                $responsible,
                $type,
                $dateModified,
                $dateCreateFrom,
                $dateCreateTo
            );

            if ($response && isset($response['contacts']) && count($response['contacts']) > 0) {
                $entities = array_merge($entities, $response['contacts']);
            }
        }

        if (empty($entities)) {
            return false;
        }

        return $entities;
    }

    /**
     * пытается найти по телефону существующий контакт с помощью поиска amoCRM
     * при поиске сам amoCRM откидывает лишние символы и потому телефоны
     * +7 (495) 700-99-72 и 74957009972 для него одинаковые
     *
     * @param string $phone
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function findContactByPhone($phone)
    {
        $phoneClear = $this->phoneClear($phone);

        if (mb_strlen($phoneClear, 'utf-8') < 5) {
            return false;
        }

        $contacts = $this->getContacts(null, null, $phoneClear, null, 'contact');

        if ($contacts && count($contacts)) {
            foreach ($contacts as $contact) {
                // пробегаемся по всем доп. полям, что являются телефонами
                if (isset($contact['custom_fields'])) {
                    foreach ($contact['custom_fields'] as $field) {
                        if (isset($field['code']) && $field['code'] == 'PHONE') {
                            foreach ($field['values'] as $value) {
                                if ($phoneClear == $this->phoneClear($value['value'])) {
                                    return $contact;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * пытается найти по электронной почте существующий контакт
     *
     * @param string $email
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function findContactByEmail($email)
    {
        $contacts = $this->getContacts(null, null, $email, null, 'contact');

        if ($contacts && count($contacts)) {
            foreach ($contacts as $contact) {
                // пробегаемся по всем доп. полям, что являются электронными почтами
                if (isset($contact['custom_fields'])) {
                    foreach ($contact['custom_fields'] as $field) {
                        if (isset($field['code']) && $field['code'] == 'EMAIL') {
                            foreach ($field['values'] as $value) {
                                if (strtolower($email) == strtolower($value['value'])) {
                                    return $contact;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Пытается найти по телефону существующую компанию с помощью поиска amoCRM
     * при поиске сам amoCRM откидывает лишние символы и потому телефоны
     * +7 (495) 700-99-72 и 74957009972 для него одинаковые.
     *
     * @param string $phone
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function findCompanyByPhone($phone)
    {
        $phoneClear = $this->phoneClear($phone);

        if (mb_strlen($phoneClear, 'utf-8') < 5) {
            return false;
        }

        $companies = $this->getCompanies(null, null, $phoneClear);

        if ($companies && count($companies)) {
            foreach ($companies as $company) {
                // пробегаемся по всем доп. полям, что являются телефонами
                if (isset($company['custom_fields'])) {
                    foreach ($company['custom_fields'] as $field) {
                        if (isset($field['code']) && $field['code'] == 'PHONE') {
                            foreach ($field['values'] as $value) {
                                if ($phoneClear == $this->phoneClear($value['value'])) {
                                    return $company;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Пытается найти по электронной почте существующую компанию.
     *
     * @param string $email
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function findCompanyByEmail($email)
    {
        $companies = $this->getCompanies(null, null, $email);

        if ($companies && count($companies)) {
            foreach ($companies as $company) {
                // пробегаемся по всем доп. полям, что являются электронными почтами
                if (isset($company['custom_fields'])) {
                    foreach ($company['custom_fields'] as $field) {
                        if (isset($field['code']) && $field['code'] == 'EMAIL') {
                            foreach ($field['values'] as $value) {
                                if (strtolower($email) == strtolower($value['value'])) {
                                    return $company;
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Получаем связи сделок и контактов не учитывая лимита amocrm в 500 строк,
     * прозрачно происходят ещё запросы в amocrm, если нужно больше 500.
     * Возвращается сразу содердимое массива по ключу links.
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param null      $ids
     * @param null      $elementType
     * @param \DateTime $dateModified
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getLinks(
        $limitRows = null,
        $limitOffset = null,
        $ids = null,
        $elementType = null,
        \DateTime $dateModified = null
    ) {
        $maxLimit = 250; // максимально возможная выборка строк (ограничено amocrm), она же шаг
        $end      = false;
        $entities = [];
        // определяем первоначальное смещение
        $offset      = $limitOffset ? : 0;
        $limit       = null;
        $idsChunks   = array_chunk($ids, $maxLimit);
        $countChunks = count($idsChunks);

        do {
            // определяем сколько заправшивать в этой итерации
            if (is_null($limitRows)) {
                $limit = $maxLimit; // если лимит не указан, то берём максимально возможное число записей
            } elseif ($limitRows <= $maxLimit) {
                $limit = $limitRows;
            } else {
                $limitRows -= $maxLimit;
                $limit     = $maxLimit;
            }

            $response = $this->amoRestApi->getContactsLinks(
                null,
                null,
                $idsChunks[$countChunks - 1],
                $elementType,
                $dateModified
            );
            $countChunks--;

            if ($response && isset($response['links']) && count($response['links']) > 0) {
                $entities = array_merge($entities, $response['links']);
                if ($countChunks > 0) {
                    $offset += $limit;
                } else {
                    $end = true;
                }
            } else {
                $end = true;
            }
        } while (!$end);

        return $entities;
    }

    /**
     * добавляем только одну компанию
     *
     * @param string    $name
     * @param array|int $leads
     * @param int       $user
     * @param array     $customFields - массив массивов вида [id => [value, enum, subtype]]
     * @param array     $tags
     *
     * @throws AmoException
     *
     * @return int
     */
    public function addCompany($name, $leads = [], $user = null, array $customFields = [], array $tags = [])
    {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }
        $company['request']['contacts']['add'] = [
            [
                'name'                => $name,
                'linked_leads_id'     => is_array($leads) ? $leads : [$leads],
                'responsible_user_id' => $user,
                'custom_fields'       => $newCustomFields,
                'tags'                => $tags ? implode(', ', $tags) : null,
            ],
        ];

        $response = $this->amoRestApi->setCompany($company);

        // получим id только что добавленной компании
        if ($response && isset($response['contacts']['add'][0]) && isset($response['contacts']['add'][0]['id'])) {
            return (int)$response['contacts']['add'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * получить только одну компанию по id
     *
     * @param int|array $id
     * @param array     $user
     *
     * @throws AmoException
     *
     * @return array|false - если запросили в $id одного, то вернём только его данные, если массив $id, то вернём массив
     */
    public function getCompany($id, $user = [])
    {
        $leads = $this->amoRestApi->getCompanyList(1, null, !is_array($id) ? [$id] : $id, null, $user);
        if ($leads && isset($leads['contacts'][0])) {
            return $leads['contacts'][0];
        }

        return false;
    }

    /**
     * Получаем компании не учитывая лимита amocrm в 500 строк,
     * прозрачно происходят ещё запросы в amocrm, если нужно больше 500
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param string    $query
     * @param string    $responsible
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCompanies(
        $limitRows = null,
        $limitOffset = null,
        $query = null,
        $responsible = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $maxLimit = 350; // максимально возможная выборка строк (ограничено amocrm), она же шаг
        $end      = false;
        $entities = [];
        // определяем первоначальное смещение
        $offset = $limitOffset ? : 0;
        $limit  = null;

        do {
            // определяем сколько заправшивать в этой итерации
            if (is_null($limitRows)) {
                $limit = $maxLimit; // если лимит не указан, то берём максимально возможное число записей
            } elseif ($limitRows <= $maxLimit) {
                $limit = $limitRows;
            } else {
                $limitRows -= $maxLimit;
                $limit     = $maxLimit;
            }

            $response = $this->amoRestApi->getCompanyList(
                $limit,
                $offset,
                null,
                $query,
                $responsible,
                $dateModified,
                $dateCreateFrom,
                $dateCreateTo
            );

            if ($response && isset($response['contacts']) && count($response['contacts']) > 0) {
                $entities = array_merge($entities, $response['contacts']);
                if (count($response['contacts']) < $maxLimit) {
                    $end = true;
                } else {
                    $offset += $limit;
                }
            } else {
                $end = true;
            }
        } while (!$end);

        return $entities;
    }

    /**
     * обновляем только один контакт
     *
     * @param int|array $id           Id Компании или вся Компания сразу. При передаче всей компании из
     *                                нее берется last_modified и инкрементируется на 1 (использовать
     *                                при ошибке "Last modified date is older than in database").
     * @param string    $name
     * @param array|int $leads        - список связанных сделок
     * @param int       $user
     * @param array     $customFields - массив массивов вида [id => [value, enum, subtype]]
     * @param string    $tags
     * @param \DateTime $dateCreate
     *
     * @throws AmoException
     *
     * @return array
     */
    public function updateCompany(
        $id,
        $name = null,
        $leads = [],
        $user = null,
        array $customFields = [],
        $tags = null,
        \DateTime $dateCreate = null
    ) {
        $newCustomFields = [];
        if (!empty($customFields)) {
            $newCustomFields = $this->mapCustomFields($customFields);
        }

        $nowTimestamp = (new \DateTime('now', new \DateTimeZone('Europe/Moscow')))->format('U');

        if (is_array($id)) {
            $request = [
                'id'            => $id['id'],
                'last_modified' => ($id['last_modified'] > $nowTimestamp) ? $id['last_modified'] + 1 : $nowTimestamp,
            ];
        } else {
            $request = [
                'id'            => $id,
                'last_modified' => $nowTimestamp,
            ];
        }

        if ($name) {
            $request['name'] = $name;
        }
        if ($leads) {
            $request['linked_leads_id'] = is_array($leads) ? $leads : [$leads];
        }
        if ($user) {
            $request['responsible_user_id'] = $user;
        }
        if ($newCustomFields) {
            $request['custom_fields'] = $newCustomFields;
        }
        if (is_array($tags)) {
            $request['tags'] = $tags ? implode(', ', $tags) : false;
        }
        if ($dateCreate) {
            $request['date_create'] = $dateCreate->format('U');
        }

        $contact['request']['contacts']['update'] = [$request];

        $response = $this->amoRestApi->setCompany($contact);

        return $response;
    }

    /**
     * Добавляем только одну задачу
     *
     * @param string    $text
     * @param int       $elementId    - id сделки или контакта, к которому привязывается задача
     * @param int       $elementType  - тип элемента, к которому привязывается задача (1 - контакт, 2 - сделка)
     * @param int       $taskType     - тип задачи, получаемый из аккаунта
     * @param \DateTime $completeTill - дата, до которой необходимо завершить задачу. Если указано время 23:59, то в
     *                                интерфейсах системы вместо времени будет отображаться "Весь день".
     * @param int       $user
     * @param int       $createdUserId
     *
     * @throws AmoException
     *
     * @return int
     */
    public function addTask(
        $text,
        $elementId,
        $elementType,
        $taskType,
        \DateTime $completeTill,
        $user = null,
        $createdUserId = null
    ) {
        // Если не передан параметр, то берем пользователя,
        // через которого работаем, либо 0
        if (is_null($createdUserId)) {
            $createdUser   = $this->getUserByEmail($this->amoRestApi->getLogin());
            $createdUserId = isset($createdUser['id']) ? $createdUser['id'] : 0;
        }

        $task['request']['tasks']['add'] = [
            [
                'text'                => $text,
                'element_id'          => $elementId,
                'element_type'        => $elementType,
                'task_type'           => $taskType,
                'complete_till'       => $completeTill->format('U'),
                'responsible_user_id' => $user,
                'created_user_id'     => $createdUserId,
            ],
        ];

        $response = $this->amoRestApi->setTasks($task);

        // получим id только что добавленной сделки
        if ($response && isset($response['tasks']['add'][0]) && isset($response['tasks']['add'][0]['id'])) {
            return (int)$response['tasks']['add'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * обновить только одну задачу
     *
     * @param int|array $id           Id Задачи или вся Задача сразу. При передаче всей задачи из
     *                                нее берется last_modified и инкрементируется на 1 (использовать
     *                                при ошибке "Last modified date is older than in database").
     * @param string    $text
     * @param int       $elementId    - id сделки или контакта, к которому привязывается задача
     * @param int       $elementType  - тип элемента, к которому привязывается задача (1 - контакт, 2 - сделка)
     * @param int       $taskType     - тип задачи, получаемый из аккаунта
     * @param \DateTime $completeTill - дата, до которой необходимо завершить задачу. Если указано время 23:59, то в
     *                                интерфейсах системы вместо времени будет отображаться "Весь день".
     * @param int       $user
     *
     * @throws AmoException
     *
     * @return int
     */
    public function updateTask(
        $id,
        $text = null,
        $elementId = null,
        $elementType = null,
        $taskType = null,
        \DateTime $completeTill = null,
        $user = null
    ) {
        $nowTimestamp = (new \DateTime('now'))->format('U');

        if (is_array($id)) {
            $request = [
                'id'            => $id['id'],
                'last_modified' => ($id['last_modified'] > $nowTimestamp) ? $id['last_modified'] + 1 : $nowTimestamp,
            ];
        } else {
            $request = [
                'id'            => $id,
                'last_modified' => $nowTimestamp,
            ];
        }

        if ($text) {
            $request['text'] = $text;
        }
        if ($elementId) {
            $request['element_id'] = $elementId;
        }
        if ($elementType) {
            $request['element_type'] = $elementType;
        }
        if ($taskType) {
            $request['task_type'] = $taskType;
        }
        if ($completeTill) {
            $request['complete_till'] = $completeTill->format('U');
        }
        if ($user) {
            $request['responsible_user_id'] = $user;
        }

        $task['request']['tasks']['update'] = [$request];

        $response = $this->amoRestApi->setTasks($task);

        // получим id только что добавленной сделки
        if ($response && isset($response['tasks']['update'][0]) && isset($response['tasks']['update'][0]['id'])) {
            return (int)$response['tasks']['update'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * получить только одну задачу по id
     *
     * @param int|array $id
     * @param array     $user
     * @param string    $type - тип задачи: contact(по-умолчанию) или lead
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getTask($id, $user = [], $type = 'contact')
    {
        $contacts = $this->amoRestApi->getTasksList(1, null, !is_array($id) ? [$id] : $id, null, $user, $type);
        if ($contacts && isset($contacts['contacts'][0])) {
            return $contacts['contacts'][0];
        }

        return false;
    }

    /**
     * Получаем задачи не учитывая лимита amocrm в 500 строк,
     * прозрачно происходят ещё запросы в amocrm, если нужно больше 500
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param mixed     $ids
     * @param string    $responsible
     * @param string    $type
     * @param int       $elementId
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getTasks(
        $limitRows = null,
        $limitOffset = null,
        $ids = null,
        $responsible = null,
        $type = null,
        $elementId = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $maxLimit = 350; // максимально возможная выборка строк (ограничено amocrm), она же шаг
        $end      = false;
        $entities = [];
        // определяем первоначальное смещение
        $offset = $limitOffset ? : 0;
        $limit  = null;

        do {
            // определяем сколько заправшивать в этой итерации
            if (is_null($limitRows)) {
                $limit = $maxLimit; // если лимит не указан, то берём максимально возможное число записей
            } elseif ($limitRows <= $maxLimit) {
                $limit = $limitRows;
            } else {
                $limitRows -= $maxLimit;
                $limit     = $maxLimit;
            }

            $response = $this->amoRestApi->getTasksList(
                $limit,
                $offset,
                $ids,
                $responsible,
                $type,
                $elementId,
                $dateModified,
                $dateCreateFrom,
                $dateCreateTo
            );

            if ($response && isset($response['tasks']) && count($response['tasks']) > 0) {
                $entities = array_merge($entities, $response['tasks']);
                if (count($response['tasks']) < $maxLimit) {
                    $end = true;
                } else {
                    $offset += $limit;
                }
            } else {
                $end = true;
            }
        } while (!$end);

        return $entities;
    }

    /**
     * Добавляем только одно событие (примечание)
     *
     * @param string|array $text
     * @param int          $elementId   - id сделки или контакта, к которому привязывается задача
     * @param int          $elementType - тип элемента, к которому привязывается задача (1 - контакт, 2 - сделка)
     * @param int          $noteType    - тип событий, получаемый из аккаунта (или тут
     *                                  https://developers.amocrm.ru/rest_api/notes_type.php#notetypes)
     * @param int          $user
     * @param int          $createdUserId
     *
     * @throws AmoException
     *
     * @return int
     */
    public function addNote($text, $elementId, $elementType, $noteType = null, $user = null, $createdUserId = null)
    {
        // Если не передан параметр, то берем пользователя,
        // через которого работаем, либо 0
        if (is_null($createdUserId)) {
            $createdUser   = $this->getUserByEmail($this->amoRestApi->getLogin());
            $createdUserId = isset($createdUser['id']) ? $createdUser['id'] : 0;
        }

        $note['request']['notes']['add'] = [
            [
                'text'                => $text,
                'element_id'          => $elementId,
                'element_type'        => $elementType,
                'note_type'           => $noteType,
                'responsible_user_id' => $user,
                'created_user_id'     => $createdUserId,
            ],
        ];

        $response = $this->amoRestApi->setNotes($note);

        // получим id только что добавленной сделки
        if ($response && isset($response['notes']['add'][0]) && isset($response['notes']['add'][0]['id'])) {
            return (int)$response['notes']['add'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * получить только одно событие по id
     *
     * @param int|array $id
     * @param array     $elementId
     * @param string    $type - тип примечания: contact или lead или null (выборка по всем)
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getNote($id, $elementId = null, $type = null)
    {
        $notes = $this->amoRestApi->getNotesList(1, null, !is_array($id) ? [$id] : $id, $elementId, $type);

        if ($notes && isset($notes['notes'][0])) {
            return $notes['notes'][0];
        }

        return false;
    }

    /**
     * Получаем примечания не учитывая лимита amocrm в 500 строк,
     * прозрачно происходят ещё запросы в amocrm, если нужно больше 500
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param null      $elementId
     * @param string    $type - тип примечания: contact или lead или null (выборка по всем)
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getNoties(
        $limitRows = null,
        $limitOffset = null,
        $elementId = null,
        $type = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $maxLimit = 350; // максимально возможная выборка строк (ограничено amocrm), она же шаг
        $end      = false;
        $entities = [];
        // определяем первоначальное смещение
        $offset = $limitOffset ? : 0;
        $limit  = null;

        do {
            // определяем сколько заправшивать в этой итерации
            if (is_null($limitRows)) {
                $limit = $maxLimit; // если лимит не указан, то берём максимально возможное число записей
            } elseif ($limitRows <= $maxLimit) {
                $limit = $limitRows;
            } else {
                $limitRows -= $maxLimit;
                $limit     = $maxLimit;
            }

            $response = $this->amoRestApi->getNotesList(
                $limit,
                $offset,
                null,
                $elementId,
                $type,
                $dateModified,
                $dateCreateFrom,
                $dateCreateTo
            );

            if ($response && isset($response['notes']) && count($response['notes']) > 0) {
                $entities = array_merge($entities, $response['notes']);
                if (count($response['notes']) < $maxLimit) {
                    $end = true;
                } else {
                    $offset += $limit;
                }
            } else {
                $end = true;
            }
        } while (!$end);

        return $entities;
    }

    /**
     * Добавляем только один вебхук
     *
     * @param string    $url
     * @param array|int $events
     *
     * @throws AmoException
     *
     * @return bool
     */
    public function subscribeWebhook($url, $events)
    {
        $webhook['request']['webhooks']['subscribe'] = [
            [
                'url'    => $url,
                'events' => is_array($events) ? $events : [$events],
            ],
        ];

        $response = $this->amoRestApi->subscribeWebhooks($webhook);

        // возвращаем только успех подписки (true или false)
        if ($response && isset($response['webhooks']['subscribe'][0]['result'])) {
            return (bool)$response['webhooks']['subscribe'][0]['result'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Получить вебхук(и) по url. Немного закостылено т.к. получаем в любом случае все.
     *
     * @param int|array $url
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getWebhookByUrl($url)
    {
        $webhooks = $this->amoRestApi->getWebhooksList();

        if ($webhooks && isset($webhooks['webhooks'][0])) {
            foreach ($webhooks['webhooks'] as $webhook) {
                if ($webhook['url'] == $url) {
                    return $webhook;
                }
            }
        }

        return false;
    }

    /**
     * Получить вебхук(и) по id. Немного закостылено т.к. получаем в любом случае все.
     *
     * @param int|array $id
     *
     * @throws AmoException
     *
     * @return array|false
     */
    public function getWebhookById($id)
    {
        $id       = is_array($id) ? $id : [$id];
        $webhooks = $this->amoRestApi->getWebhooksList();
        $result   = [];

        if ($webhooks && isset($webhooks['webhooks'][0])) {
            foreach ($webhooks['webhooks'] as $webhook) {
                if (in_array($webhook['id'], $id)) {
                    $result[] = $webhook;
                }
            }
        }

        if (count($result)) {
            return $result;
        }

        return false;
    }

    /**
     * Отписываеся от вебхука
     *
     * @param string    $url
     * @param array|int $events
     *
     * @throws AmoException
     *
     * @return bool
     */
    public function unsubscribeWebhook($url, $events)
    {
        $webhook['request']['webhooks']['unsubscribe'] = [
            [
                'url'    => $url,
                'events' => is_array($events) ? $events : [$events],
            ],
        ];

        $response = $this->amoRestApi->unsubscribeWebhooks($webhook);

        // возвращаем только успех подписки (true или false)
        if ($response && isset($response['webhooks']['unsubscribe'][0]['result'])) {
            return (bool)$response['webhooks']['unsubscribe'][0]['result'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Добавление определённого кастомного поля
     *
     * @param string $name
     * @param int    $type        – https://developers.amocrm.ru/rest_api/accounts_current.php#type_id
     * @param int    $elementType – contact, company или lead или id Каталога (кастомной сущности)
     * @param string $origin      – уникальный id, по которому будет доступно удаление
     * @param int    $disabled    – закрыто редактируемость поля из веб-интерфейсе или нет (1 - закрыто, 0 - открыто)
     *
     * @throws AmoException
     *
     * @return int
     */
    public function addField($name, $type, $elementType, $origin, $disabled = 1)
    {
        $entityTypes = ['contact' => 1, 'lead' => 2, 'company' => 3];

        if (is_string($elementType)) {
            if (!isset($entityTypes[$elementType])) {
                throw new AmoException('Type "' . $elementType . '" of an entity not exists', 500);
            }

            $elementType = $entityTypes[$elementType];
        } elseif (!is_numeric($elementType)) {
            throw new AmoException('Type "' . $elementType . '" of an entity not exists and not numeric', 500);
        }

        if (!in_array($disabled, [0, 1], true)) {
            $disabled = 1;
        }

        $field['request']['fields']['add'] = [
            [
                'name'         => $name,
                'disabled'     => $disabled,
                'type'         => $type,
                'element_type' => $elementType,
                'origin'       => $origin,
            ],
        ];

        $response = $this->amoRestApi->setFields($field);

        if ($response && isset($response['fields']['add'][0]['id'])) {
            return (int)$response['fields']['add'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available, the request returned the following response "' . print_r(
                    $response,
                    true
                ) . '"', 500
            );
        }
    }

    /**
     * Удаление ранее добавленного кастомного поля
     *
     * @param int    $id
     * @param string $origin – уникальный id, по которому будет доступно удаление
     *
     * @throws AmoException
     *
     * @return int
     */
    public function deleteField($id, $origin)
    {
        $field['request']['fields']['delete'] = [
            [
                'id'     => $id,
                'origin' => $origin,
            ],
        ];

        $response = $this->amoRestApi->setFields($field);

        if ($response && isset($response['fields']['delete'][0]['id'])) {
            return (int)$response['fields']['delete'][0]['id'];
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available, the request returned the following response "' . print_r(
                    $response,
                    true
                ) . '"', 500
            );
        }
    }

    /**
     * Получение значений дополнительных полей
     *
     * @param array   $customFields - массив доп. полей сущности (контакта, компании или сделки), но
     *                              без жёской типизации как array т.к. иногда передаём null, когда
     *                              нет доп. полей, но можно передать и весь массив сущности сразу.
     * @param int     $id           - id доп. поля, значение которого надо найти
     * @param boolean $enum         - вернуть поле enum или value (работает только при $allValues = false)
     * @param boolean $allValues    - вернуть весь список значений или только первое
     *
     * @return array|string|false
     */
    public function getCustomFieldValue($customFields, $id, $enum = false, $allValues = false)
    {
        if (!is_array($customFields)) {
            return false;
        }

        // если передали сущность, то возьмём только доп. поля, если они там есть
        if (array_key_exists('name', $customFields)) {
            if (!isset($customFields['custom_fields'])) {
                return false;
            }

            $customFields = $customFields['custom_fields'];
        }

        foreach ($customFields as $field) {
            if ($field['id'] == $id) {
                if ($allValues) {
                    return $field['values'];
                } elseif (isset($field['values'][0])) {
                    // там поля с датой без value
                    if (isset($field['values'][0][$enum ? 'enum' : 'value'])) {
                        return $field['values'][0][$enum ? 'enum' : 'value'];
                    } else {
                        return $field['values'][0];
                    }
                } else {
                    // выбранный элемент из радио кнопок без доп. массива отдаются
                    return $field['values'][$enum ? 'enum' : 'value'];
                }
            }
        }

        return false;
    }

    /**
     * Получение id значений из select и multiselect и др. доп. полей где есть выбор из списка
     * т.е. можно передать текстовое представление значений и обратно получить их id,
     * которые в дальнейшем будут использованны в добавлении сущности
     *
     * @deprecated - использовать вместо него
     *
     * @param array $customFields   - массив доп. полей сущности (контакта, компании или сделки)
     *                              без жёской типизации как array т.к. иногда передаём null, когда
     *                              нет доп. полей, но можно передать и весь массив сущности сразу.
     * @param array $ids            - массив где ключ id доп. поля, а значение массив (или строка) текстовых значений
     *                              доп. поля select
     *
     * @return array|false - массив похожий на $ids, но в значениях уже id, тех доп. полей что не смогли найти в
     *                     массиве не будет
     */
    public function getCustomFieldSelectId(array $customFields, array $ids)
    {
        if (!is_array($customFields)) {
            return false;
        }

        // если передали сущность, то возьмём только доп. поля, если они там есть
        if (array_key_exists('name', $customFields)) {
            if (!isset($customFields['custom_fields'])) {
                return false;
            }

            $customFields = $customFields['custom_fields'];
        }

        $cfIds  = array_keys($ids);
        $result = [];

        // ищим среди доп. полей подходящее по id
        foreach ($customFields as $field) {
            if (in_array($field['id'], $cfIds)) {
                // были ошибки что нет ключа enums, может тип поля меняли
                if ($field['enums']) {
                    // ищим среди значений доп. поля требуемое по тексту
                    foreach ($field['enums'] as $id => $enum) {
                        // пытаемся найти id значения по входящему значению
                        // во входящем значении может быть как строка так и массив строк
                        if (in_array(
                            $enum,
                            (is_array($ids[$field['id']])
                                ? $ids[$field['id']]
                                : [$ids[$field['id']]])
                        )) {
                            // если это мультисписок (5), то там просто массив id без value
                            if ($field['type_id'] == 5) {
                                // на тот случай если это первое значение
                                @$result[$field['id']] = (array)$result[$field['id']];
                                array_push($result[$field['id']], $id); // добавляем значение
                            } else {
                                $result[$field['id']] = ['value' => $id];
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * получение id значений из select и multiselect и др. доп. полей где есть выбор из списка
     * т.е. можно передать текстовое представление значений и обратно получить их id,
     * которые в дальнейшем будут использованны в добавлении сущности
     *
     * @param array $ids   - массив где ключ id доп. поля, а значение массив
     *                     (или строка) текстовых значений доп. поля select, либо
     *                     обыные поля, которые будут просто добавлятся в результат
     *
     * @throws AmoException
     *
     * @return array - массив похожий на $ids, но в значениях полей типа select уже id
     */
    public function normalizeSelect(array $ids)
    {
        $customFields = $this->getCustomFields();

        $cfIds  = array_keys($ids);
        $result = [];

        foreach ($customFields as $fields) {
            foreach ($fields as $field) {
                // если текущее совпадает с предложенным и это один из списков (select, multiselect, radiobutton)
                if (in_array($field['id'], $cfIds) && in_array($field['type_id'], [4, 5, 10])) {
                    // ищим среди значений доп. поля требуемое по тексту
                    foreach ($field['enums'] as $id => $enum) {
                        // пытаемся найти id значения по входящему значению
                        // во входящем значении может быть как строка так и массив строк
                        if (in_array(
                            $enum,
                            (is_array($ids[$field['id']]) ? $ids[$field['id']] : [$ids[$field['id']]])
                        )) {
                            // если это мультисписок (5), то там просто массив id без value
                            if ($field['type_id'] == 5) {
                                // на тот случай если это первое значение
                                @$result[$field['id']] = (array)$result[$field['id']];
                                array_push($result[$field['id']], $id); // добавляем значение
                            } else {
                                $result[$field['id']] = ['value' => $id];
                            }
                        }
                    }
                }
            }
        }

        // на выходе в старый массив подставляем новые значения
        // а те что не были поменены так и остануться
        return $this->arrayMerge($ids, $result);
    }

    /**
     * улучшенный array_merge, который оставляет числовые ключи как есть
     *
     * @return array
     */
    private function arrayMerge()
    {
        $argList = func_get_args();
        $result  = [];

        foreach ((array)$argList as $arg) {
            foreach ((array)$arg as $key => $value) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * получаем по id сделки саму сделку и связанные сущности (контакты и компании)
     *
     * @param int $leadId
     * @param     $returnLead
     * @param     $returnContact
     * @param     $returnCompany
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getRelatedEntityLead($leadId, $returnLead, $returnContact, $returnCompany)
    {
        $result = [];

        if ($returnLead) {
            $result[] = $this->getLead($leadId);
        }

        if ($returnContact) {
            $result[] = $this->getContactsByLead($leadId);
        }

        if ($returnCompany) {
            $result[] = $this->getCompaniesByLead($leadId);
        }

        return $result;
    }

    /**
     * Преобразует номер телефона к единому формату для поиска через API amoCRM.
     * Убирает код и берёт последние 10 цифр т.к. amoCRM так лучше ищет. Но есть
     * возможность оставить код страны, если передать true вторым параметром.
     *
     * 89051234578          -> 9051234578
     * +7 905 123-45-78     -> 9051234576
     * +33 (123) 213 23 3   -> 3123213233
     * 123 4567             -> 1234567
     * 1234567              -> 1234567
     *
     * @param string $phone
     * @param bool   $code
     *
     * @return string
     */
    public function phoneClear($phone, $code = false)
    {
        // Проверяем на наличие чего-то явно не телефонного и пустой строки
        if (!strlen(trim($phone)) || strlen(trim($phone)) < 7) {
            return $phone;
        }

        // Убираем пробелы и дефисы со скобками
        $trimmed = preg_replace('/[^\d]/', '', $phone);

        // Берем 'основной' номер (7 цифр с конца)
        preg_match('/.{7}$/', $trimmed, $main);
        if (array_key_exists(0, $main)) {
            $main = $main[0];
        } else {
            return $trimmed;
        }

        // Получаем префиксы
        $prefix = substr($trimmed, 0, strpos($trimmed, $main));

        // Выделяем среди префиксов код города
        preg_match('/\d{3}$/', $prefix, $cityCode);

        if (array_key_exists(0, $cityCode)) {
            $cityCode = $cityCode[0];
        } else {
            return $trimmed;
        }

        // Если кроме кода города в префиксе что-то есть, то это код страны
        if (strlen($prefix) - strlen($cityCode)) {
            $countryCode = substr($prefix, 0, strpos($prefix, $cityCode));
            $countryCode = ($countryCode == 8) ? '+7' : $countryCode;

            if (preg_match('/^\+/', $countryCode) && strlen($countryCode)) {
                $countryCode = preg_replace('/^\+/', '', $countryCode);
            }
        } else {
            $countryCode = '7';
        }

        return ($code ? $countryCode : '') . $cityCode . $main;
    }

    /**
     * Убирает всё круоме цифер из номера. Годится для записи в Контакт.
     *
     * 89051234578 -> 79051234567
     * +7 905 123-45-78 -> 79051234567
     * +33 (123) 213 23 3 -> 33123213233
     *
     * @param string $phone
     *
     * @return string
     *
     * @deprecated (использовать phoneClear со вторым параметром)
     */
    public function phoneSanitize($phone)
    {
        return $this->phoneClear($phone, true);
    }

    /**
     * Преобразует массив доп. полей в готовый для отправки по API и фильтрует пустые элементы массива,
     * причем фильтрует обычные поля и мультисписки.
     *
     * @param array $customFields Массив значений доп полей сущности.
     *
     * @return array
     */
    private function mapCustomFields($customFields)
    {
        $newCustomFields = [];

        foreach ($customFields as $key => $cf) {
            if (isset($cf['value']) && !is_null($cf['value']) && $cf['value'] !== false) {
                $tmp['value'] = $cf['value'];
                if (isset($cf['enum'])) {
                    $tmp['enum'] = $cf['enum'];
                }
                if (isset($cf['subtype'])) {
                    $tmp['subtype'] = $cf['subtype'];
                }
                $newCustomFields[] = [
                    'id'     => $key,
                    'values' => [$tmp],
                ];
            } elseif (is_array($cf) && !array_key_exists('value', $cf)) {
                // Фильтурем пустые элементы массива.
                $cfFiltered = [];
                foreach ($cf as $cfValueKey => $value) {
                    if (is_null($value) || $value === false) {
                        continue;
                    }

                    $cfFiltered[$cfValueKey] = $value;
                }

                $newCustomFields[] = [
                    'id'     => $key,
                    'values' => $cfFiltered,
                ];
            }
        }

        return $newCustomFields;
    }

    /**
     * Выбирает из переданного массива телефонов (или 1 телефона) те(тот), которых(ого) нет в кастомных полях сущности.
     *
     * @param array        $entity Массив сущности (Контакт или Компания), в которой будем искать телефоны.
     * @param array|string $phones Массив телефонов или 1, среди которых ищем.
     *
     * @return array|false Если нашли все переданные телефоны в кастомных полях, то false. Иначе - массив ненайденных.
     */
    public function findUnusedPhones($entity, $phones)
    {
        if (!$entity || !is_array($entity) || !$phones) {
            return false;
        }

        $phonesListNew = [];
        $phones        = is_array($phones) ? $phones : [$phones];

        foreach ($phones as $phone) {
            $phoneClear = $this->phoneClear($phone);

            // пробегаемся по всем доп. полям, что являются телефонами
            if (isset($entity['custom_fields'])) {
                $found = false;

                foreach ($entity['custom_fields'] as $field) {
                    if (isset($field['code']) && $field['code'] == 'PHONE') {
                        foreach ($field['values'] as $value) {
                            if ($phoneClear == $this->phoneClear($value['value'])) {
                                $found = true;
                                break 2;
                            }
                        }
                    }
                }

                // Если не нашли у контакта телефона из формы, то добавляем его
                if (!$found) {
                    $phonesListNew = array_merge($phonesListNew, [$phone]);
                }
            }
        }

        return $phonesListNew ? : false;
    }

    /**
     * Выбирает из переданного массива почт (или 1 почты) те(ту), которых(ой) нет в кастомных полях сущности.
     *
     * @param array        $entity Массив сущности (Контакт или Компания), в которой будем искать телефоны.
     * @param array|string $emails Массив почт или 1, среди которых ищем.
     *
     * @return array|false Если нашли все переданные почты в кастомных полях, то false. Иначе - массив ненайденных.
     */
    public function findUnusedEmails($entity, $emails)
    {
        if (!$entity || !is_array($entity) || !$emails) {
            return false;
        }

        $emailsListNew = [];
        $emails        = is_array($emails) ? $emails : [$emails];

        foreach ($emails as $email) {
            // Пробегаемся по всем доп. полям, что являются телефонами
            if (isset($entity['custom_fields'])) {
                $found = false;

                foreach ($entity['custom_fields'] as $field) {
                    if (isset($field['code']) && $field['code'] == 'EMAIL') {
                        foreach ($field['values'] as $value) {
                            if ($email == $value['value']) {
                                $found = true;
                                break 2;
                            }
                        }
                    }
                }

                // Если не нашли у контакта телефона из формы, то добавляем его
                if (!$found) {
                    $emailsListNew = array_merge($emailsListNew, [$email]);
                }
            }
        }

        return $emailsListNew ? : false;
    }

    /**
     * Проверяет наличие у сущности переданных телефонов и, если их нет возвращает массив кастомных полей с добавленными
     * новыми телефонами, иначе возвращает массив актуальных телефонов.
     *
     * @param array     $entity     Массив сущности (Контакт или Компания), в которой будем искать телефоны.
     * @param array|int $phones     Массив телефонов, которые нужно проверить.
     * @param string    $entityType Тип сущности, у которой проверяем телефоны (contacts или companies).
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCustomFieldWithUnusedPhones($entity, $phones, $entityType)
    {
        $customFields = [];

        if (!$entity || !$entityType) {
            // Если передели пустые данные - выходим.
            return $customFields;
        }

        $customFields = $this->getCustomFieldWithUnusedDataByType($entity, $phones, $entityType, 'phone');

        return count($customFields) > 0 ? $customFields : [];
    }

    /**
     * Проверяет наличие у сущности переданных email-адресов и, если их нет возвращает массив кастомных полей с
     * добавленными новыми email-адресами, иначе возвращает массив актуальных email-адресов.
     *
     * @param array     $entity     Массив сущности (Контакт или Компания), в которой будем искать телефоны.
     * @param array|int $emails     Массив email-адресов, которые нужно проверить.
     * @param string    $entityType Тип сущности, у которой проверяем телефоны (contacts или companies).
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCustomFieldWithUnusedEmails($entity, $emails, $entityType)
    {
        $customFields = [];

        if (!$entity || !$entityType) {
            // Если передели пустые данные - выходим.
            return $customFields;
        }

        $customFields = $this->getCustomFieldWithUnusedDataByType($entity, $emails, $entityType, 'email');

        return count($customFields) > 0 ? $customFields : [];
    }

    /**
     * Проверяет наличие у сущности переданных данных (телефонов или почт) в кастомных полях и, если их нет возвращает
     * массив кастомных полей с добавленными новыми даннми, иначе возвращает массив актуальных кастомных полей.
     *
     * @param array     $entity     Массив сущности (Контакт или Компания), в которой будем искать данные.
     * @param array|int $addData    Массив email-адресов или телефонов, которые нужно найти.
     * @param string    $entityType Тип сущности, у которой проверяем данные (contacts или companies).
     * @param string    $dataType   Тип данных, которые ищем (phone или email).
     *
     * @throws AmoException
     *
     * @return array
     */
    private function getCustomFieldWithUnusedDataByType($entity, $addData, $entityType, $dataType)
    {
        // Получаем данные о кастомном поле.
        $cfData       = $this->getCustomFieldByCode($dataType, $entityType);
        $dataListNew  = [];
        $customFields = [];

        if ($cfData) {
            $cfDataList = $this->getCustomFieldValue($entity, $cfData['id'], false, true);

            if ($cfDataList) {
                foreach ($cfDataList as $cfDataListVal) {
                    // Тут фича от amoCRM: enums приходит в виде ID (int), а задавать надо строкой
                    // потому массив нужно перелопатать, чтобы снова послать с новым значением.
                    $dataListNew[] = [
                        'value' => $cfDataListVal['value'],
                        'enum'  => $cfData['enums'][$cfDataListVal['enum']],
                    ];
                }
            }

            // Если передали пустые данные для поиска.
            if (!$addData) {
                $customFields[$cfData['id']] = $dataListNew;

                return $customFields;
            }

            // Если данные есть.
            $addData = is_array($addData) ? $addData : [$addData];

            foreach ($addData as $data) {
                $found = false;
                // Если телефон - его нужно почитстить, если почта - оставляем как есть.
                $dataClear = ($dataType == 'phone') ? $this->phoneClear($data) : $data;

                if ($cfDataList) {
                    foreach ($cfDataList as $cfDataListVal) {
                        if ($dataType == 'phone') {
                            if ($this->phoneClear($cfDataListVal['value']) == $dataClear) {
                                $found = true;
                                break;
                            }
                        } else {
                            if ($cfDataListVal['value'] == $dataClear) {
                                $found = true;
                                break;
                            }
                        }
                    }
                }

                // Если не нашли у контакта телефона или мыла из формы, то добавляем его
                if (!$found) {
                    $dataListNew = array_merge(
                        $dataListNew,
                        [
                            [
                                'value' => $data,
                                'enum'  => (($dataType == 'phone') ? 'MOB' : 'WORK'),
                            ],
                        ]
                    );
                }
            }
        }

        $customFields[$cfData['id']] = $dataListNew;

        return $customFields;
    }

    /**
     * Обработка результата поиска в амо
     *
     * @param $queryResult
     * @param $query
     * @param $cfId
     *
     * @return array
     */
    public function findInEntitiesByCfId($queryResult, $query, $cfId)
    {
        if (!$queryResult) {
            return [];
        }

        $result = [];

        foreach ($queryResult as $entity) {
            $cfValue = $this->getCustomFieldValue($entity, $cfId);

            if ($cfValue == $query) {
                $result[] = $entity;
            }
        }

        return $result;
    }

    /**
     * Обработка результата поиска в амо (возвращает первый верный результат)
     *
     * @param $queryResult
     * @param $query
     * @param $cfId
     *
     * @return array
     */
    public function findFirstInEntitiesByCfId($queryResult, $query, $cfId)
    {
        if (!$queryResult) {
            return [];
        }

        foreach ($queryResult as $entity) {
            $cfValue = $this->getCustomFieldValue($entity, $cfId);

            if ($cfValue == $query) {
                return $entity;
            }
        }

        return [];
    }

    /**
     * Поиск всех сделок по камтомному полю
     *
     * @param $query
     * @param $cfId
     *
     * @return array
     * @throws AmoException
     */
    public function findLeadByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        return $this->findInEntitiesByCfId($this->getLeads(null, null, $query), $query, $cfId);
    }

    /**
     * Поиск первой сделки по кастомному полю
     *
     * @param $query
     * @param $cfId
     *
     * @return mixed
     * @throws AmoException
     */
    public function findFirstLeadByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        return $this->findFirstInEntitiesByCfId($this->getLeads(null, null, $query), $query, $cfId);
    }

    /**
     * Поиск всех контактов по кастомному полю
     *
     * @param $query
     * @param $cfId
     *
     * @return array
     * @throws AmoException
     */
    public function findContactByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        return $this->findInEntitiesByCfId($this->getContacts(null, null, $query), $query, $cfId);
    }

    /**
     * Поиск первого контакта по кастомному полю
     *
     * @param $query
     * @param $cfId
     *
     * @return mixed
     * @throws AmoException
     */
    public function findFirstContactByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        return $this->findFirstInEntitiesByCfId($this->getContacts(null, null, $query), $query, $cfId);
    }

    /**
     * Поиск всех компаний по запросу
     *
     * @param $query
     * @param $cfId
     *
     * @return array
     * @throws AmoException
     */
    public function findCompaniesByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        return $this->findInEntitiesByCfId($this->getCompanies(null, null, $query), $query, $cfId);
    }

    /**
     * Поиск первой компании по кастомному полю
     *
     * @param $query
     * @param $cfId
     *
     * @return mixed
     * @throws AmoException
     */
    public function findFirstCompanyByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        return $this->findFirstInEntitiesByCfId($this->getCompanies(null, null, $query), $query, $cfId);
    }

    /**
     * Поиск всех покупателей по кастомному полю
     *
     * @param $query
     * @param $cfId
     *
     * @return array
     * @throws AmoException
     */
    public function findCustomersByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        $request = ['filter' => ['custom_fields' => [$cfId => $query]]];

        $result = $this->amoRestApi->getCustomersList($request);

        if (!isset($result['customers'])) {
            throw new AmoException('Пришел неверный ответ: ' . print_r($result, true));
        }

        return $this->findInEntitiesByCfId($result['customers'], $query, $cfId);
    }

    /**
     * Поиск первого покупателя по кастомному полю
     *
     * @param $query
     * @param $cfId
     *
     * @return mixed
     * @throws AmoException
     */
    public function findFirstCustomerByCustomField($query, $cfId)
    {
        if (!$query || !$cfId) {
            return [];
        }

        $request = ['filter' => ['custom_fields' => [$cfId => $query]]];

        $result = $this->amoRestApi->getCustomersList($request);

        if (!isset($result['customers'])) {
            throw new AmoException('Пришел неверный ответ: ' . print_r($result, true));
        }

        return $this->findFirstInEntitiesByCfId($result['customers'], $query, $cfId);
    }

    /**
     * Соединяет сущности
     *
     * @param $from
     * @param $fromId
     * @param $to
     * @param $toId
     *
     * @throws AmoException
     *
     * @return bool
     */
    public function linkEntities($from, $fromId, $to, $toId)
    {
        $request = [];

        if (is_array($toId)) {
            foreach ($toId as $entityId) {
                $request[] = [
                    'from'    => $from,
                    'from_id' => $fromId,
                    'to'      => $to,
                    'to_id'   => $entityId,
                ];
            }
        } else {
            $request[] = [
                'from'    => $from,
                'from_id' => $fromId,
                'to'      => $to,
                'to_id'   => $toId,
            ];
        }

        $response = $this->amoRestApi->setLinks(['request' => ['links' => ['link' => $request]]]);

        if ($response && isset($response['links']['link']['links'])) {
            if (isset($response['links']['link']['errors'])
                && count($response['links']['link']['errors'])
            ) {
                throw new AmoException(
                    'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Error: ' .
                    array_shift($response['links']['link']['errors'])['message'], 500
                );
            } else {
                return true;
            }
        } else {
            throw new AmoException(
                'API of an AMOCRM is not available. Method: ' . __FUNCTION__ . '(). Response: ' . print_r(
                    $response,
                    true
                ), 500
            );
        }
    }

    /**
     * Получение всех связанных сущностей
     *
     * @param $from
     * @param $fromId
     * @param $to
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getEntityLinks($from, $fromId, $to = null)
    {
        return $this->amoRestApi->getLinks(
            $from,
            $fromId,
            $to
        )['links'];
    }

    /**
     * Получает связб один ко многим по сущностостям
     *
     * @param $from
     * @param $fromId
     * @param $to
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getEntityToEntityLinks($from, $fromId, $to)
    {
        $links = $this->getEntityLinks($from, $fromId, $to);

        if ($links) {
            return array_column($links, 'to_id');
        } else {
            return [];
        }
    }

    /**
     * Получить связи контакта
     *
     * @param $contactId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getContactLinks($contactId)
    {
        return $this->getEntityLinks(self::ENTITY_LABEL_CONTACTS, $contactId);
    }

    /**
     * Получить связи контакта со сделками
     *
     * @param $contactId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getContactLeads($contactId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_CONTACTS, $contactId, self::ENTITY_LABEL_LEADS);
    }

    /**
     * Получить связи контакта
     *
     * @param $contactId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getContactCompany($contactId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_CONTACTS, $contactId, self::ENTITY_LABEL_LEADS);
    }

    /**
     * Получить связи контакта
     *
     * @param $contactId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getContactCustomers($contactId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_CONTACTS, $contactId, self::ENTITY_LABEL_CUSTOMERS);
    }

    /**
     * Получить связи контакта
     *
     * @param $leadId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getLeadLinks($leadId)
    {
        return $this->getEntityLinks(self::ENTITY_LABEL_LEADS, $leadId);
    }

    /**
     * Получить связи контакта
     *
     * @param $leadId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getLeadContacts($leadId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_LEADS, $leadId, self::ENTITY_LABEL_CONTACTS);
    }

    /**
     * Получить связи контакта
     *
     * @param $leadId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getLeadCompany($leadId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_LEADS, $leadId, self::ENTITY_LABEL_COMPANIES);
    }

    /**
     * Получить связи контакта
     *
     * @param $companyId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCompanyLinks($companyId)
    {
        return $this->getEntityLinks(self::ENTITY_LABEL_COMPANIES, $companyId);
    }

    /**
     * Получить связи контакта
     *
     * @param $companyId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCompanyLeads($companyId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_COMPANIES, $companyId, self::ENTITY_LABEL_LEADS);
    }

    /**
     * Получить связи контакта
     *
     * @param $companyId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCompanyContacts($companyId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_COMPANIES, $companyId, self::ENTITY_LABEL_CONTACTS);
    }

    /**
     * Получить связи контакта
     *
     * @param $companyId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCompanyCustomers($companyId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_COMPANIES, $companyId, self::ENTITY_LABEL_CUSTOMERS);
    }

    /**
     * Получить связи контакта
     *
     * @param $customerId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCustomerLinks($customerId)
    {
        return $this->getEntityLinks(self::ENTITY_LABEL_CUSTOMERS, $customerId);
    }

    /**
     * Получить связи контакта
     *
     * @param $customerId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCustomerContacts($customerId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_CUSTOMERS, $customerId, self::ENTITY_LABEL_CONTACTS);
    }

    /**
     * Получить связи контакта
     *
     * @param $customerId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getCustomerCompany($customerId)
    {
        return $this->getEntityToEntityLinks(self::ENTITY_LABEL_CUSTOMERS, $customerId, self::ENTITY_LABEL_COMPANIES);
    }

    /**
     * Связывает сделку с контактами
     *
     * @param $leadId
     * @param $contactsIds
     *
     * @return bool
     * @throws AmoException
     */
    public function linkLeadWithContacts($leadId, $contactsIds)
    {
        return $this->linkEntities(self::ENTITY_LABEL_LEADS, $leadId, self::ENTITY_LABEL_CONTACTS, $contactsIds);
    }

    /**
     * Связывает сделку с компанией
     *
     * @param $leadId
     * @param $companyId
     *
     * @return bool
     * @throws AmoException
     */
    public function linkLeadWithCompany($leadId, $companyId)
    {
        return $this->linkEntities(self::ENTITY_LABEL_LEADS, $leadId, self::ENTITY_LABEL_COMPANIES, $companyId);
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $contactId
     * @param $leadIds
     *
     * @return bool
     * @throws AmoException
     */
    public function linkContactWithLeads($contactId, $leadIds)
    {
        return $this->linkEntities(self::ENTITY_LABEL_CONTACTS, $contactId, self::ENTITY_LABEL_LEADS, $leadIds);
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $contactId
     * @param $companyId
     *
     * @return bool
     * @throws AmoException
     */
    public function linkContactWithCompany($contactId, $companyId)
    {
        return $this->linkEntities(self::ENTITY_LABEL_CONTACTS, $contactId, self::ENTITY_LABEL_COMPANIES, $companyId);
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $contactId
     * @param $customerIds
     *
     * @return bool
     * @throws AmoException
     */
    public function linkContactWithCustomers($contactId, $customerIds)
    {
        return $this->linkEntities(self::ENTITY_LABEL_CONTACTS, $contactId, self::ENTITY_LABEL_CUSTOMERS, $customerIds);
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $companyId
     * @param $leadsIds
     *
     * @return bool
     * @throws AmoException
     */
    public function linkCompanyWithLeads($companyId, $leadsIds)
    {
        return $this->linkEntities(self::ENTITY_LABEL_COMPANIES, $companyId, self::ENTITY_LABEL_LEADS, $leadsIds);
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $companyId
     * @param $contactIds
     *
     * @return bool
     * @throws AmoException
     */
    public function linkCompanyWithContacts($companyId, $contactIds)
    {
        return $this->linkEntities(self::ENTITY_LABEL_COMPANIES, $companyId, self::ENTITY_LABEL_CONTACTS, $contactIds);
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $companyId
     * @param $customerIds
     *
     * @return bool
     * @throws AmoException
     */
    public function linkCompanyWithCustomers($companyId, $customerIds)
    {
        return $this->linkEntities(
            self::ENTITY_LABEL_COMPANIES,
            $companyId,
            self::ENTITY_LABEL_CUSTOMERS,
            $customerIds
        );
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $customerId
     * @param $contactIds
     *
     * @return bool
     * @throws AmoException
     */
    public function linkCustomerWithContacts($customerId, $contactIds)
    {
        return $this->linkEntities(self::ENTITY_LABEL_CUSTOMERS, $customerId, self::ENTITY_LABEL_CONTACTS, $contactIds);
    }

    /**
     * Связывает контакт со сделками
     *
     * @param $customerId
     * @param $companyId
     *
     * @return bool
     * @throws AmoException
     */
    public function linkCustomerWithCompany($customerId, $companyId)
    {
        return $this->linkEntities(self::ENTITY_LABEL_CUSTOMERS, $customerId, self::ENTITY_LABEL_COMPANIES, $companyId);
    }

    /**
     * Вызов функций из оборачиваемого объекта
     *
     * @param       $method
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        return call_user_func_array([$this->amoRestApi, $method], $arguments);
    }
}
