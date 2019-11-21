<?php

namespace App\Common\Library\Cache;

/**
 * класс реализующий паттерн Декоратор для внедрения кеширования в методы объекта
 */
abstract class Cache implements CacheInterface
{
	protected $redis; // объект key-value storeg для кеширования
    
    // префиксы для прелючей сохраняемых значений
    protected $prefixCache = 'cache.';
    protected $prefixLock = 'lock.';
    protected $prefixSerialize = 'ser.';
    // время для блокирования записи
    protected $timeLock = 30;
    // время до истечения кеша когда уже можно начать перегенерацию кеша с блокировкой для избежания dog-pile эффекта
    protected $timeLeft = 50;

    public function __construct($redis)
    {        
        $this->redis = $redis;
    }
	
	/**
     * {@inheritdoc}
     */
	public function read($key, &$ttlLeft = -1)
	{
		$value = $this->redis->get($this->prefixCache.$key);
		
		if ($this->redis->exists($this->prefixSerialize.$key))
		{
			$value = unserialize($value);
		}
		
		if ($ttlLeft!==-1)
		{
			$ttlLeft = $this->redis->ttl($this->prefixCache.$key);
			if ($ttlLeft < 0) $ttlLeft = 0;
		}
		return $value;
	}
	
	/**
     * {@inheritdoc}
     */
	public function save($key, $value, $ttl = 259200)
	{
		if ($ttl > 0)
		{
			if (is_scalar($value))
			{
				$set = $this->redis->setex($this->prefixCache.$key, $ttl, $value);
				$this->keySerialization(false, $key, $ttl);
			}
			else
			{
				$set = $this->redis->setex($this->prefixCache.$key, $ttl, serialize($value));
				$this->keySerialization(true, $key, $ttl);
			}
		} else {
			if (is_scalar($value))
			{
				$set = $this->redis->set($this->prefixCache.$key, $value);
				$this->keySerialization(false, $key, 0);
			}
			else
			{
				$set = $this->redis->set($this->prefixCache.$key, serialize($value));
				$this->keySerialization(true, $key, $ttl);
			}
			
		}
		if (!$set) return false;
		return true;
	}
	
	/**
     * {@inheritdoc}
     */
	public function add($key, $value, $ttl = 259200)
	{
		$redisKey = $this->prefixCache.$key;
		
		if (is_scalar($value))
		{
			$set = $this->redis->setnx($redisKey, $value);
			if (!$set) return false;
			$this->keySerialization(false, $key, $ttl);
		} else {
			$set = $this->redis->setnx($redisKey, serialize($value));
			if (!$set) return false;
			$this->keySerialization(true, $key, $ttl);
		}

		if ($ttl > 0)
		{
			$this->redis->expire($redisKey, $ttl);
		}
		return true;
	}
	
	/**
     * {@inheritdoc}
     */
	public function lock($key)
	{
		$r = $this->redis->setnx($this->prefixLock.$key, 1);
		if (!$r) return false;
		$this->redis->expire($this->prefixLock.$key, $this->timeLock);
		return true;
	}
	
	/**
     * {@inheritdoc}
     */
	public function keySerialization($isSerialized, $key, $ttl)
	{
		if (!$isSerialized)
		{
			$this->redis->del($this->prefixSerialize.$key);
		} else {
			if ($ttl > 0)
			{
				$this->redis->setex($this->prefixSerialize.$key, $ttl, 1);
			}
			else
			{
				$this->redis->set($this->prefixSerialize.$key, 1);
			}
		}
	}
	
	/**
     * {@inheritdoc}
     */
	public function del($keys)
	{
		if (empty($keys)) return false;
		if (!is_array($keys)) $keys = array($keys);
		$todel = array();
		foreach ($keys as $key)
		{
			$todel[] = $this->prefixCache.$key;
			$todel[] = $this->prefixSerialize.$key;
			$todel[] = $this->prefixLock.$key;
		}
		return $this->redis->del($todel);
	}
}