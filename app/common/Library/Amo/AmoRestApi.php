<?php

namespace App\Common\Library\Amo;

use Phalcon\Cache\Backend\Redis;
use Phalcon\Logger\AdapterInterface;

/**
 * The main class for working with amoCRM
 */
class AmoRestApi
{
    /*
     * URL for RestAPI
     */
    const URL = 'https://%s.%s/private/api/v2/json/';

    /*
     * Auth URL for RestAPI
     */
    const URL_AUTH = 'https://%s.%s/private/api/';

    /*
     * Unsorted URL for RestAPI
     */
    const URL_UNSORTED = 'https://%s.%s/api/';

    /*
     * Methods
     */
    const METHOD_GET  = 'GET';
    const METHOD_POST = 'POST';

    /**
     * Login access to API
     *
     * @var string
     * @access private
     */
    private $login;

    /**
     * Hash
     *
     * @var string
     * @access private
     */
    private $key;

    /**
     * Sub domain
     *
     * @var string
     * @access private
     */
    private $subDomain;

    /**
     * Основной домен на который нужно слать запросы
     *
     * @var string
     * @access private
     */
    private $domain = 'amocrm.ru';

    /**
     * Язык пользователя
     *
     * @var string
     * @access private
     */
    private $lang = 'ru';

    /**
     * Min sleeping curl request, µs (1s = 1000000µs)
     *
     * @var int
     * @access private
     */
    private $usleep;

    /**
     * Phalcon logger
     *
     * @var AdapterInterface
     * @access private
     */
    private $log;

    /**
     * Phalcon cache
     *
     * @var Redis
     * @access private
     */
    private $cache;

    /**
     * Class constructor
     *
     * @param string $subDomain
     * @param string $login
     * @param string $key
     * @param array  $options  - доп. опции:
     *                         usleep - искусственный таймаут, чтобы amoCRM не банило, на всякий случай
     *                         log - сервис логирования
     *                         domain - основной домен
     *                         lang - язык для локализации
     *
     * @return void
     * @access public
     */
    public function __construct($subDomain, $login, $key, array $options = [])
    {
        $this->subDomain = $subDomain;
        $this->login     = $login;
        $this->key       = $key;

        if (isset($options['usleep']) && $options['usleep']) {
            $this->usleep = (int)$options['usleep'];
        }

        if (isset($options['domain']) && $options['domain']) {
            $this->domain = $options['domain'];
        }

        if (isset($options['lang']) && $options['lang']) {
            $this->lang = $options['lang'];
        }

        if (isset($options['log']) && $options['log'] instanceof AdapterInterface) {
            $this->log = $options['log'];
        }

        if (isset($options['cache']) && $options['cache'] instanceof Redis) {
            $this->cache = $options['cache'];
        }
    }

    /**
     * Get Accounts
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     */
    public function getAccounts()
    {
        return $this->request('accounts/current');
    }

    /**
     * Set Contacts
     *
     * @throws AmoException
     *
     * @param array $contacts
     *
     * @return array|false
     * @access public
     */
    public function setContacts($contacts = null)
    {
        if (is_null($contacts)) {
            return false;
        }

        return $this->request('contacts/set', self::METHOD_POST, $contacts);
    }

    /**
     * Get Contacts List
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param mixed     $ids
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
     * @access public
     */
    public function getContactsList(
        $limitRows = null,
        $limitOffset = null,
        $ids = null,
        $query = null,
        $responsible = null,
        $type = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $headers = [];
        if (is_null($dateModified) === false) {
            $headers = ['if-modified-since: ' . $dateModified->format('D, d M Y H:i:s')];
        }

        $parameters = [];
        if (is_null($limitRows) === false) {
            $parameters['limit_rows'] = $limitRows;
            if (is_null($limitRows) === false) {
                $parameters['limit_offset'] = $limitOffset;
            }
        }

        if (is_null($ids) === false) {
            $parameters['id'] = $ids;
        }

        if (is_null($query) === false) {
            $parameters['query'] = $query;
        }

        if (is_null($responsible) === false) {
            $parameters['responsible_user_id'] = $responsible;
        }

        if (is_null($type) === false) {
            $parameters['type'] = $type;
        }

        if (is_null($dateCreateFrom) === false) {
            $parameters['date_create[from]'] = $dateCreateFrom->format('U');
        }

        if (is_null($dateCreateTo) === false) {
            $parameters['date_create[to]'] = $dateCreateTo->format('U');
        }

        return $this->request(
            'contacts/list',
            self::METHOD_GET,
            $parameters,
            $headers
        );
    }

    /**
     * Get Contacts Links
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param mixed     $ids
     * @param int       $elementType - тип элемента id которых переданы (1 - контакт, 2 - сделка)
     * @param \DateTime $dateModified
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     */
    public function getContactsLinks(
        $limitRows = null,
        $limitOffset = null,
        $ids = null,
        $elementType = null,
        \DateTime $dateModified = null
    ) {
        $headers = [];
        if (is_null($dateModified) === false) {
            $headers = ['if-modified-since: ' . $dateModified->format('D, d M Y H:i:s')];
        }

        $parameters = [];
        if (is_null($limitRows) === false) {
            $parameters['limit_rows'] = $limitRows;
            if (is_null($limitRows) === false) {
                $parameters['limit_offset'] = $limitOffset;
            }
        }

        if (is_null($ids) === false) {
            if ($elementType == 1) {
                $parameters['contacts_link'] = $ids;
            } else {
                $parameters['deals_link'] = $ids;
            }
        }

        return $this->request(
            'contacts/links',
            self::METHOD_GET,
            $parameters,
            $headers
        );
    }

    /**
     * Set Leads
     *
     * @param array $leads
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     */
    public function setLeads($leads = null)
    {
        if (is_null($leads)) {
            return false;
        }

        return $this->request('leads/set', self::METHOD_POST, $leads);
    }

    /**
     * Get Leads List
     *
     * @param int            $limitRows
     * @param int            $limitOffset
     * @param mixed          $ids
     * @param string         $query
     * @param string         $responsible
     * @param mixed          $status
     * @param \DateTime|null $dateModified
     * @param \DateTime|null $dateCreateFrom
     * @param \DateTime|null $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     */
    public function getLeadsList(
        $limitRows = null,
        $limitOffset = null,
        $ids = null,
        $query = null,
        $responsible = null,
        $status = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $headers = [];
        if (is_null($dateModified) === false) {
            $headers = ['if-modified-since: ' . $dateModified->format('D, d M Y H:i:s')];
        }

        $parameters = [];
        if (is_null($limitRows) === false) {
            $parameters['limit_rows'] = $limitRows;
            if (is_null($limitRows) === false) {
                $parameters['limit_offset'] = $limitOffset;
            }
        }

        if (is_null($ids) === false) {
            $parameters['id'] = $ids;
        }

        if (is_null($query) === false) {
            $parameters['query'] = $query;
        }

        if (is_null($responsible) === false) {
            $parameters['responsible_user_id'] = $responsible;
        }

        if (is_null($status) === false) {
            $parameters['status'] = $status;
        }

        if (is_null($dateCreateFrom) === false) {
            $parameters['date_create[from]'] = $dateCreateFrom->format('U');
        }

        if (is_null($dateCreateTo) === false) {
            $parameters['date_create[to]'] = $dateCreateTo->format('U');
        }

        return $this->request(
            'leads/list',
            self::METHOD_GET,
            $parameters,
            $headers
        );
    }

    /**
     * Set Company
     *
     * @param array $company
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     */
    public function setCompany($company = null)
    {
        if (is_null($company)) {
            return false;
        }

        return $this->request('company/set', self::METHOD_POST, $company);
    }

    /**
     * Set Companies
     *
     * @param array $companies
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     */
    public function setCompanies($companies = null)
    {
        return $this->setCompany($companies);
    }

    /**
     * Get Company List
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param mixed     $ids
     * @param string    $query
     * @param string    $responsible
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     */
    public function getCompanyList(
        $limitRows = null,
        $limitOffset = null,
        $ids = null,
        $query = null,
        $responsible = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $headers = [];
        if (is_null($dateModified) === false) {
            $headers = ['if-modified-since: ' . $dateModified->format('D, d M Y H:i:s')];
        }

        $parameters = [];
        if (is_null($limitRows) === false) {
            $parameters['limit_rows'] = $limitRows;
            if (is_null($limitRows) === false) {
                $parameters['limit_offset'] = $limitOffset;
            }
        }

        if (is_null($ids) === false) {
            $parameters['id'] = $ids;
        }

        if (is_null($query) === false) {
            $parameters['query'] = $query;
        }

        if (is_null($responsible) === false) {
            $parameters['responsible_user_id'] = $responsible;
        }

        if (is_null($dateCreateFrom) === false) {
            $parameters['date_create[from]'] = $dateCreateFrom->format('U');
        }

        if (is_null($dateCreateTo) === false) {
            $parameters['date_create[to]'] = $dateCreateTo->format('U');
        }

        return $this->request(
            'company/list',
            self::METHOD_GET,
            $parameters,
            $headers
        );
    }

    /**
     * Set Tasks
     *
     * @param array $tasks
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     */
    public function setTasks($tasks = null)
    {
        if (is_null($tasks)) {
            return false;
        }

        return $this->request('tasks/set', self::METHOD_POST, $tasks);
    }

    /**
     * Get Tasks List
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
     * @access public
     */
    public function getTasksList(
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
        $headers = [];
        if (is_null($dateModified) === false) {
            $headers = ['if-modified-since: ' . $dateModified->format('D, d M Y H:i:s')];
        }

        $parameters = [];
        if (is_null($limitRows) === false) {
            $parameters['limit_rows'] = $limitRows;
            if (is_null($limitRows) === false) {
                $parameters['limit_offset'] = $limitOffset;
            }
        }

        if (is_null($ids) === false) {
            $parameters['id'] = $ids;
        }

        if (is_null($elementId) === false) {
            $parameters['element_id'] = $elementId;
        }

        if (is_null($responsible) === false) {
            $parameters['responsible_user_id'] = $responsible;
        }

        if (is_null($type) === false) {
            $parameters['type'] = $type;
        }

        if (is_null($dateCreateFrom) === false) {
            $parameters['date_create[from]'] = $dateCreateFrom->format('U');
        }

        if (is_null($dateCreateTo) === false) {
            $parameters['date_create[to]'] = $dateCreateTo->format('U');
        }

        return $this->request(
            'tasks/list',
            self::METHOD_GET,
            $parameters,
            $headers
        );
    }

    /**
     * Set Notes
     *
     * @param array $notes
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/notes_set.php
     */
    public function setNotes($notes = null)
    {
        if (is_null($notes)) {
            return false;
        }

        return $this->request('notes/set', self::METHOD_POST, $notes);
    }

    /**
     * Get Notes List
     *
     * @param int       $limitRows
     * @param int       $limitOffset
     * @param mixed     $ids
     * @param string    $element_id
     * @param string    $type
     * @param \DateTime $dateModified
     * @param \DateTime $dateCreateFrom
     * @param \DateTime $dateCreateTo
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/notes_list.php
     */
    public function getNotesList(
        $limitRows = null,
        $limitOffset = null,
        $ids = null,
        $element_id = null,
        $type = null,
        \DateTime $dateModified = null,
        \DateTime $dateCreateFrom = null,
        \DateTime $dateCreateTo = null
    ) {
        $headers = [];
        if (is_null($dateModified) === false) {
            $headers = ['if-modified-since: ' . $dateModified->format('D, d M Y H:i:s')];
        }

        $parameters = [];
        if (is_null($limitRows) === false) {
            $parameters['limit_rows'] = $limitRows;
            if (is_null($limitRows) === false) {
                $parameters['limit_offset'] = $limitOffset;
            }
        }

        if (is_null($ids) === false) {
            $parameters['id'] = $ids;
        }

        if (is_null($element_id) === false) {
            $parameters['element_id'] = $element_id;
        }

        if (is_null($type) === false) {
            $parameters['type'] = $type;
        }

        if (is_null($dateCreateFrom) === false) {
            $parameters['date_create[from]'] = $dateCreateFrom->format('U');
        }

        if (is_null($dateCreateTo) === false) {
            $parameters['date_create[to]'] = $dateCreateTo->format('U');
        }

        return $this->request(
            'notes/list',
            self::METHOD_GET,
            $parameters,
            $headers
        );
    }

    /**
     * Get webhooks list
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/webhooks/list.php
     */
    public function getWebhooksList()
    {
        return $this->request('webhooks/list');
    }

    /**
     * Subscribe to webhooks (limit 100)
     *
     * @param array $webhooks
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/webhooks/subscribe.php
     */
    public function subscribeWebhooks($webhooks)
    {
        if (is_null($webhooks)) {
            return false;
        }

        return $this->request('webhooks/subscribe', self::METHOD_POST, $webhooks);
    }

    /**
     * Unsubscribe from webhooks (limit 100)
     *
     * @param array $webhooks
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/webhooks/subscribe.php
     */
    public function unsubscribeWebhooks($webhooks)
    {
        if (is_null($webhooks)) {
            return false;
        }

        return $this->request('webhooks/unsubscribe', self::METHOD_POST, $webhooks);
    }

    /**
     * Set Fields
     *
     * @param array $fields
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     */
    public function setFields($fields = null)
    {
        if (is_null($fields)) {
            return false;
        }

        return $this->request('fields/set', self::METHOD_POST, $fields);
    }

    /**
     * Получение списка покупателей аккаунта
     *
     * @param array $parameters
     *
     * @return array
     *
     * @throws AmoException
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/customers/list.php
     */
    public function getCustomersList($parameters = [])
    {
        // Раньше здесь было присваивание ID решил отсавить фоллбек, но вообще такой поход deprecated
        if (is_scalar($parameters)) {
            $parameters = ['id' => $parameters];
        }
        return $this->request('customers/list', self::METHOD_GET, $parameters);
    }

    /**
     * @param $request
     *
     * @return mixed
     * @throws AmoException
     */
    public function getCustomersTransactionsList($request)
    {
        return $this->request('transactions/list', self::METHOD_GET, $request);
    }

    /**
     * @param $request
     *
     * @return mixed
     * @throws AmoException
     */
    public function setCustomersTransactionsList($request)
    {
        return $this->request('transactions/set', self::METHOD_POST, $request);
    }

    /**
     * Добавление, обновление и удаление покупателей
     *
     * @param array $customers
     *
     * @throws AmoException
     *
     * @return array|bool
     *
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/customers/set.php
     */
    public function setCustomers($customers = null)
    {
        if (is_null($customers)) {
            return false;
        }

        return $this->request('customers/set', self::METHOD_POST, $customers);
    }

    /**
     * Получение списка каталогов аккаунта
     *
     * @param int $id
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     * @see    https://developer.amocrm.ru/rest_api/catalogs/list.php
     */
    public function getCatalogsList($id = null)
    {
        $parameters = [];
        if (is_null($id) === false) {
            $parameters['id'] = $id;
        }

        return $this->request('catalogs/list', self::METHOD_GET, $parameters);
    }

    /**
     * Добавление, обновление и удаление каталогов
     *
     * @param array $catalogs
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     * @see    https://developer.amocrm.ru/rest_api/catalogs/set.php
     */
    public function setCatalogs($catalogs = null)
    {
        if (is_null($catalogs)) {
            return false;
        }

        return $this->request('catalogs/set', self::METHOD_POST, $catalogs);
    }

    /**
     * Получение элементов каталога аккаунта.
     *
     * @param int       $catalogId
     * @param array|int $id
     * @param string    $term
     * @param string    $orderBy   - сортировка по name, date_create, date_modify
     * @param string    $orderType - направление сортировки: ASC, DESC
     * @param int       $pagen     - страница выборки
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     * @see    https://developer.amocrm.ru/rest_api/catalog_elements/list.php
     */
    public function getCatalogElementsList(
        $catalogId = null,
        $id = null,
        $term = null,
        $orderBy = null,
        $orderType = null,
        $pagen = null
    ) {
        $parameters = [];

        if (is_null($catalogId) === false) {
            $parameters['catalog_id'] = $catalogId;
        }

        if (is_null($id) === false) {
            $parameters['id'] = $id;
        }

        if (is_null($term) === false) {
            $parameters['term'] = $term;
        }

        if (is_null($orderBy) === false) {
            $parameters['order_by'] = $orderBy;
        }

        if (is_null($orderType) === false) {
            $parameters['order_type'] = $orderType;
        }

        if (is_null($pagen) === false) {
            $parameters['PAGEN_1'] = $pagen;
        }

        return $this->request(
            'catalog_elements/list',
            self::METHOD_GET,
            $parameters
        );
    }

    /**
     * Добавление, обновление и удаление элементов каталога
     *
     * @param array $catalogElements
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     * @see    https://developer.amocrm.ru/rest_api/catalog_elements/set.php
     */
    public function setCatalogElements(array $catalogElements)
    {
        return $this->request('catalog_elements/set', self::METHOD_POST, $catalogElements);
    }

    /**
     * Получение cвязей между любыми сущностями, например, между Контактом и Компанией
     * или между Элементами каталогов и Сделками др.
     *
     * @deprecated
     *
     * @param      $from
     * @param      $fromId
     * @param null $to
     * @param null $toCatalogId
     *
     * @return array
     * @throws AmoException
     * @access public
     */
    public function getCatalogElementsLinksList($from, $fromId, $to = null, $toCatalogId = null)
    {
        return $this->getLinks($from, $fromId, $to, $toCatalogId);
    }

    /**
     * Получение cвязей между любыми сущностями, например, между Контактом и Компанией
     * или между Элементами каталогов и Сделками др.
     *
     * @param string $from         Сущность, к которой осуществленна привязка
     *                             (leads, contacts, companies, customers, catalog_elements)
     * @param int    $fromId
     * @param string $to           Привязанная сущность (leads, contacts,
     *                             companies, customers, catalog_elements)
     * @param int    $toCatalogId
     *
     * @throws AmoException
     *
     * @return array
     */
    public function getLinks($from, $fromId, $to = null, $toCatalogId = null)
    {
        $parameters = [
            'from'    => $from,
            'from_id' => $fromId,
        ];

        if ($to) {
            $parameters['to'] = $to;
        }

        if ($toCatalogId) {
            $parameters['to_catalog_id'] = $toCatalogId;
        } elseif ($to === Amo::ENTITY_LABEL_CATALOGS_ELEMENT) {
            throw new AmoException('Нет ID каталога, не можем определить связи');
        }

        $request['links'] = [$parameters];

        return $this->request(
            'links/list',
            self::METHOD_GET,
            $request
        );
    }

    /**
     * Получение связей между любыми сущностями, например, между Контактом и Компанией
     * или между Элементами каталогов и Сделками др.
     *
     * @param [[
     * from => string, Сущность, к которой осуществленна привязка (leads, contacts, companies, customers,
     * catalog_elements) from_id => int|string, Id искомой сущности to => string, Привязанная сущность (leads,
     * contacts, companies, customers, catalog_elements) to_catalog_id => int|string Id Id каталога к которому
     * осуществлена привязка
     * ]] $batchParams Параметры для получения связей
     *
     * @throws AmoException
     *
     * @return mixed
     */
    public function getCatalogElementsLinksListBatch($batchParams)
    {
        $request['links'] = $batchParams;

        return $this->request(
            'links/list',
            self::METHOD_GET,
            $request
        );
    }

    /**
     * Установка и разрыв связи между сущностями
     *
     * @param array $links
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     * @see    https://developer.amocrm.ru/rest_api/links/set.php
     * @deprecated
     */
    public function setCatalogElementsLinks(array $links)
    {
        return $this->setLinks($links);
    }

    /**
     * Установка и разрыв связей между сущностями
     *
     * @param array $links
     *
     * @return mixed
     * @throws AmoException
     */
    public function setLinks(array $links)
    {
        return $this->request('links/set', self::METHOD_POST, $links);
    }

    /**
     * Возвращает список неразобранных
     *
     * @param array $categories - категории для выборки [sip, mail, forms]
     * @param array $orderBy    - сортировка по определённому полюс [<field_name> => 'desc|asc']
     * @param int   $pageSize
     * @param int   $pagen
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/unsorted/list.php
     */
    public function getUnsortedList(
        $categories = null,
        $orderBy = null,
        $pageSize = null,
        $pagen = null
    ) {
        $parameters = [];

        if (is_null($pageSize) === false) {
            $parameters['page_size'] = $pageSize;
        }

        if (is_null($pagen) === false) {
            $parameters['PAGEN_1'] = $pagen;
        }

        if (is_null($categories) === false) {
            $parameters['categories'] = $categories;
        }

        if (is_null($orderBy) === false) {
            $parameters['order_by'] = $orderBy;
        }

        return $this->requestUnsorted(
            'unsorted/list',
            self::METHOD_GET,
            $parameters
        );
    }

    /**
     * Добавляет неразобранные заявки
     *
     * @param array $unsorted
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     * @see    https://developers.amocrm.ru/rest_api/unsorted/add.php
     */
    public function setUnsorted($unsorted)
    {
        if (is_null($unsorted)) {
            return false;
        }

        return $this->requestUnsorted('unsorted/add', self::METHOD_POST, $unsorted);
    }

    /**
     * Set widgets
     *
     * @param array $widgets
     *
     * @throws AmoException
     *
     * @return array|false
     * @access public
     */
    public function setWidgets($widgets = null)
    {
        if (is_null($widgets)) {
            return false;
        }

        return $this->request('widgets/set', self::METHOD_POST, $widgets);
    }

    /**
     * Get widgets list
     *
     * @param int|array $widgetCodes
     * @param int|array $widgetIds
     *
     * @throws AmoException
     *
     * @return array
     * @access public
     */
    public function getWidgetsList($widgetCodes = null, $widgetIds = null)
    {
        $parameters = [];

        if ($widgetCodes) {
            $parameters['widget_code'] = is_array($widgetCodes) ? $widgetCodes : [$widgetCodes];
        }

        if ($widgetIds) {
            $parameters['widget_id'] = is_array($widgetIds) ? $widgetIds : [$widgetIds];
        }

        return $this->request(
            'widgets/list',
            self::METHOD_GET,
            $parameters
        );
    }

    /**
     * Authorization in amoCRM
     *
     * @throws AmoException
     *
     * @return bool
     *
     * @access private
     */
    public function auth()
    {
        if (!$this->subDomain || !$this->login || !$this->key) {
            throw new AmoException('Authorization error. Not exists domain or login or key.');
        }

        $auth = $this->curlRequest(
            sprintf(self::URL_AUTH . 'auth.php?type=json', $this->subDomain, $this->domain),
            self::METHOD_POST,
            ['USER_LOGIN' => $this->login, 'USER_HASH' => $this->key]
        );

        if ($auth['auth'] !== true) {
            throw new AmoException('Authorization error.');
        }

        return true;
    }

    /**
     * Prepare of the request
     *
     * @param string $url
     * @param string $method
     * @param array  $parameters
     * @param array  $headers
     * @param int    $timeout
     *
     * @throws  AmoException
     *
     * @return mixed
     * @access private
     */
    private function request($url, $method = 'GET', array $parameters = [], array $headers = [], $timeout = 30)
    {
        $url = sprintf(self::URL . $url, $this->subDomain, $this->domain);

        return $this->curlRequest($url, $method, $parameters, $headers, $timeout);
    }

    /**
     * Подготавливает запрос по API специально для неразобранных заявок т.к. их делал какой-то фрилансер
     *
     * @param string $url
     * @param string $method
     * @param array  $parameters
     * @param array  $headers
     * @param int    $timeout
     *
     * @return mixed
     * @throws AmoException
     * @access private
     */
    private function requestUnsorted($url, $method = 'GET', array $parameters = [], array $headers = [], $timeout = 30)
    {
        $url = sprintf(self::URL_UNSORTED . $url, $this->subDomain, $this->domain);

        return $this->curlRequestUnsorted($url, $method, $parameters, $headers, $timeout);
    }

    /**
     * Execution of the request
     *
     * @param string $url
     * @param string $method
     * @param array  $parameters
     * @param array  $headers
     * @param int    $timeout
     *
     * @throws  AmoException
     *
     * @return mixed
     * @access private
     */
    private function curlRequest($url, $method = 'GET', array $parameters = [], array $headers = [], $timeout = 30)
    {
        $uniq = uniqid(); // для логирования, чтобы одновременные запросы из разных потоков не путать

        if (!isset($parameters['USER_LOGIN']) && !isset($parameters['USER_HASH'])) {
            $url .= '?USER_LOGIN=' . $this->login . '&USER_HASH=' . $this->key;
        }

        $parameters['lang'] = $this->lang;

        if ($method == self::METHOD_GET && $parameters) {
            $url .= '&' . http_build_query($parameters);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, false);

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method == self::METHOD_POST && $parameters) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        }

        // логируем исходные данные запроса
        if ($this->log) {
            $this->log->info(
                '[' . $uniq . '] ' .
                'Запрос в amoCRM по restAPI.' . "\n" .
                ' > url: ' . $url . "\n" .
                ' > method: ' . $method . "\n" .
                ' > timeout: ' . $timeout .
                (
                ($method == self::METHOD_POST && $parameters)
                    ? "\n" . ' > parameters: ' . print_r($parameters, true)
                    : ''
                ) .
                ($headers ? "\n" . ' > headers: ' . print_r($headers, true) : '')
            );
        }

        if ($this->usleep) {
            $startRequest = microtime(true);
        }

        $response = curl_exec($ch);

        if ($this->usleep) {
            // если запрос длился меньше, чем отпущено на один запрос, то выжидаем оставшееся время
            $timeRequest = round(microtime(true) - $startRequest, 6) * 1000000;
            $diff        = $this->usleep - $timeRequest;
            if ($diff > 0) {
                usleep($diff);
            }
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new AmoException('Запрос произвести не удалось: ' . $error, $errno);
        }

        $result = json_decode($response, true);

        // логируем результаты запроса
        if ($this->log) {
            $this->log->info(
                '[' . $uniq . '] ' .
                'Ответ на запрос.' . "\n" .
                ' > code: ' . $statusCode . "\n" .
                ' > response: ' . print_r($result, true)
            );
        }

        if ($statusCode != 200 && $statusCode != 204) {
            // пока перетаём делать повторы, а нормально обрабатываем пришедшие данные
            $message = 'Ошибка при работе с API amoCRM: ' .
                $result['response']['error'] . ', ' .
                'code: ' . $result['response']['error_code'] . ', ' .
                (isset($result['response']['ip']) ? 'IP: ' . $result['response']['ip'] . ', ' : '') .
                (isset($result['response']['domain']) ? 'domain: ' . $result['response']['domain'] . ', ' : '') .
                'HTTP status: ' . $statusCode . '. ' .
                'Подробней об ошибках amoCRM `https://www.amocrm.ru/developers/content/api/errors`';

            if ($this->log) {
                $this->log->error($message);
            }

            throw new AmoException($message, $result['response']['error_code']);
        }

        return isset($result['response']) && count($result['response']) == 0 ? true : $result['response'];
    }

    /**
     * Производит запрос по API специально для неразобранных заявок т.к. их делал какой-то фрилансер
     *
     * @param string $url
     * @param string $method
     * @param array  $parameters
     * @param array  $headers
     * @param int    $timeout
     *
     * @throws AmoException
     *
     * @return mixed
     * @access private
     */
    private function curlRequestUnsorted(
        $url,
        $method = 'GET',
        array $parameters = [],
        array $headers = [],
        $timeout = 30
    ) {
        $uniq = uniqid(); // для логирования, чтобы одновременные запросы из разных потоков не путать

        if (!isset($parameters['USER_LOGIN']) && !isset($parameters['USER_HASH'])) {
            $url .= '?login=' . $this->login . '&api_key=' . $this->key;
        }

        if (!isset($parameters['lang'])) {
            $parameters['lang'] = 'ru';
        }

        if ($method == self::METHOD_GET && $parameters) {
            $url .= '&' . http_build_query($parameters);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, false);

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method == self::METHOD_POST && $parameters) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        }

        // логируем исходные данные запроса
        if ($this->log) {
            $this->log->info(
                '[' . $uniq . '] ' .
                'Запрос в amoCRM по restAPI.' . "\n" .
                ' > url: ' . $url . "\n" .
                ' > method: ' . $method . "\n" .
                ' > timeout: ' . $timeout .
                (
                ($method == self::METHOD_POST && $parameters)
                    ? "\n" . ' > parameters: ' . print_r($parameters, true)
                    : ''
                ) .
                ($headers ? "\n" . ' > headers: ' . print_r($headers, true) : '')
            );
        }

        if ($this->usleep) {
            $startRequest = microtime(true);
        }

        $response = curl_exec($ch);

        if ($this->usleep) {
            // если запрос длился меньше, чем отпущено на один запрос, то выжидаем оставшееся время
            $timeRequest = round(microtime(true) - $startRequest, 6) * 1000000;
            $diff        = $this->usleep - $timeRequest;
            if ($diff > 0) {
                usleep($diff);
            }
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new AmoException('Запрос произвести не удалось: ' . $error, $errno);
        }

        $result = json_decode($response, true);

        // логируем результаты запроса
        if ($this->log) {
            $this->log->info(
                '[' . $uniq . '] ' .
                'Ответ на запрос.' . "\n" .
                ' > code: ' . $statusCode . "\n" .
                ' > response: ' . print_r($result, true)
            );
        }

        if ($statusCode != 200 && $statusCode != 201) {
            $entity = $result['response']['unsorted'];

            if (!isset($result['response']['unsorted']['error'])) {
                $entity = array_shift($entity);
            }

            // пока перетаём делать повторы, а нормально обрабатываем пришедшие данные
            $message = 'Ошибка при работе с API amoCRM unsorted: ' .
                $entity['error'] . ', ' .
                'status: ' . $entity['status'] .
                'HTTP status: ' . $statusCode . '. ' .
                'Подробней об ошибках amoCRM `https://www.amocrm.ru/developers/content/api/errors`';

            if ($this->log) {
                $this->log->error($message);
            }

            throw new AmoException($message, 200);
        }

        return isset($result['response']) && count($result['response']) == 0 ? true : $result['response'];
    }

    /**
     * Возвращает почту пользователя, через которого работаем
     *
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }
}
