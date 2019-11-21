<?php

namespace App\Common\Library\Cache;

/**
 * Интерфейс для кеширующего объекта-декоратора
 */
interface CacheObjectInterface extends CacheInterface
{    
    /**
     * сброс переменных кеширования в исходное состояние
     */
    public function restore();

    /**
     * включения кеширования запроса в API
     * @param integer $ttl - количество секунд, на которое будет кешироваться запрос
     * @return this
     */
    public function cache($ttl = null);
}