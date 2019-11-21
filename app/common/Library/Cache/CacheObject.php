<?php

namespace App\Common\Library\Cache;

/**
 * класс реализующий паттерн Декоратор для внедрения кеширования в методы объекта
 */
class CacheObject extends Cache implements CacheObjectInterface
{
	protected $provider; // объект предоставляющий кешируемые данные
    /**
	 * включено или выключено кеширование ответов на момент запроса
	 * перед запросом включаем режим кеширования (cache()) и тогда будет происходить проверка кеша
	 * в функции вызова метода API (call()), а самой проверкой и возвращением будет заниматься сервис $cache
	 * после обработки кеширования состояние переменной (и двух нижних) возвращается в исходное (restore())
	 */
    protected $enable;
    protected $ttl;

    public function __construct($redis, $provider)
    {
	    parent::__construct($redis);
        $this->provider = $provider;
        
        $this->restore();
    }
    
    /**
     * {@inheritdoc}
     */
    public function cache($ttl = 259200)
    {
	    $this->enable = true;
	    if ($ttl) $this->ttl = $ttl;
	    
	    return $this;
	}
    
    /**
     * {@inheritdoc}
     */
    public function restore()
    {
	    $this->enable = false;
	    $this->ttl = 259200;
	}
	
    /**
     * если установленно кеширование (перед вызываемым методом вызван cache())
     * то оборачиваем метод в кеширующую конструкцию изменяя тем самым поведение исходного метода
     * Эти мы реализуем паттерн Декоратор.
     * Кеширование запроса происходит с защитой от эффекта dog-pile 
	 * когда при истечении кеша на генерацию нового уходит много запросов от разных потоков
     * @param string $name - название вызываемого метода
     * @param array $args - аргументы вызываемого метода
     * @return string
     */
	public function __call($name, $args) {
        $value = null;
        $callMethod = false; // чтобы не делать повторный запрос, если метод в констуркции кеширования ничего не вернул
        if ($this->enable) {
	        $key = md5(get_class($this->provider).'-'.$name.'-'.serialize($args));
	        $value = $this->read($key, $ttlLeft);
	        // защита от dog-pile эффекта 
	        // если значение получили, но время хранения подходит к концу, 
	        // то пытаемся заблокировать запись, чтобы перегенерировать кеш
			if (!empty($value) && $ttlLeft < $this->timeLeft && $this->lock($key))
			{
				// время генерации нового кеша должно быть не больше времени истечения срока годности кеша иначе лавина
				$value = call_user_func_array(array($this->provider, $name), $args);
				$callMethod = true;
				if (!empty($value))
					$this->add($key, $value, $this->ttl);
			}
        }
        
        
        if (empty($value) && !$callMethod) { 
        	$value = call_user_func_array(array($this->provider, $name), $args);
        	if ($this->enable && !empty($value)) {
				$this->add($key, $value, $this->ttl);
			}
        }
        
        $this->restore();
        return $value;
    }
}