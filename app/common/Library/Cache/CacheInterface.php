<?php

namespace App\Common\Library\Cache;

/**
 * Интерфейс для кеширующего объекта-декоратора, 
 * который может кешировать другие объекты с интерфейсом Model\CacheableInterface
 */
interface CacheInterface
{    
    /**
	 * чтение значения по ключу с возвратом оставшегося времени хранения
	 * @param $key
	 * @param $ttlLeft
	 * @return mixed|string
	 */
	public function read($key, &$ttlLeft = -1);

    /**
	 * сохранения значения на определённый срок
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param integer $ttl
	 * @return bool
	 */
	public function save($key, $value, $ttl = 259200);
	
	/**
	 * запомнить значение только если ключ не существует (или бы false возращён)
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param integer $ttl
	 * @return boolean
	 */
	public function add($key, $value, $ttl = 259200);
	
	/**
	 * если ключ не залочен, то залочить (по сути просто установить с истечением)
	 *
	 * @param string $key
	 * @return boolean
	 */
	public function lock($key);
	
	/**
	 * устанавливаем ключ что была серилизация для значения конкретного ключа
	 *
	 * @param boolean $isSerialized
	 * @param string $key
	 * @param integer $ttl
	 * @return avoid
	 */
	public function keySerialization($isSerialized, $key, $ttl);
	
	/**
	 * удаляем ключ или массив ключей
	 * @param string|array $keys - keys
	 * @return boolean|array - if array of keys was passed, on error will be returned array of not deleted keys, or 'true' on success.
	 */
	public function del($keys);
}