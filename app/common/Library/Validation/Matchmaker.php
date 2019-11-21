<?php

namespace App\Common\Library\Validation;

class Matchmaker
{
	private $rules = [];

	public function __construct() 
	{
		$this->rules = [
	        /*
	         * General
	         */    
	        '$empty' => function ($value) {
	                return empty($value);
	            },
	        '$equal' =>
	            function ($value, $sample) {
	                return $value === $sample;
	            },    
	        '$required' =>
	            function ($value) {
	                return !empty($value);
	            },    
	        '$in' =>
	            function ($value) {
	                return in_array($value, array_slice(func_get_args(), 1));
	            },    
	        '$mixed' =>
	            function () {
	                return true;
	            },    
	        '$any' =>
	            function () {
	                return true;
	            },
	        /*
	         * Types
	         */    
	        '$array' => 'is_array',    
	        '$bool' => 'is_bool',    
	        '$boolean' => 'is_bool',    
	        '$callable' => 'is_callable',    
	        '$double' => 'is_double',    
	        '$float' => 'is_float',    
	        '$int' => 'is_int',    
	        '$integer' => 'is_integer',    
	        '$long' => 'is_long',    
	        '$numeric' => 'is_numeric',    
	        '$number' => 'is_numeric',    
	        '$object' => 'is_object',    
	        '$real' => 'is_real',    
	        '$resource' => 'is_resource',    
	        '$scalar' => 'is_scalar',    
	        '$string' => 'is_string',
	        /*
	         * Numbers
	         */    
	        '$gt' =>
	            function ($value, $n) {
	                return $value > $n;
	            },    
	        '$gte' =>
	            function ($value, $n) {
	                return $value >= $n;
	            },    
	        '$lt' =>
	            function ($value, $n) {
	                return $value < $n;
	            },    
	        '$lte' =>
	            function ($value, $n) {
	                return $value <= $n;
	            },    
	        '$negative' =>
	            function ($value) {
	                return $value < 0;
	            },    
	        '$positive' =>
	            function ($value) {
	                return $value > 0;
	            },    
	        '$between' =>
	            function ($value, $a, $b) {
	                return $value >= $a && $value <= $b;
	            },
	        /*
	         * Strings
	         */    
	        '$alnum' => 'ctype_​alnum',    
	        '$alpha' => 'ctype_​alpha',    
	        '$cntrl' => 'ctype_​cntrl',    
	        '$digit' => 'ctype_​digit',    
	        '$graph' => 'ctype_​graph',    
	        '$lower' => 'ctype_​lower',    
	        '$print' => 'ctype_​print',    
	        '$punct' => 'ctype_​punct',    
	        '$space' => 'ctype_​space',    
	        '$upper' => 'ctype_​upper',    
	        '$xdigit' => 'ctype_​xdigit',    
	        '$regexp' =>
	            function ($value, $regexp) {
	                return preg_match($regexp, $value);
	            },    
	        '$email' =>
	            function ($value) {
	                return filter_var($value, FILTER_VALIDATE_EMAIL);
	            },    
	        '$url' =>
	            function ($value) {
	                return filter_var($value, FILTER_VALIDATE_URL);
	            },    
	        '$ip' =>
	            function ($value) {
	                return filter_var($value, FILTER_VALIDATE_IP);
	            },    
	        '$length' =>
	            function ($value, $length) {
	                return mb_strlen($value, 'utf-8') == $length;
	            },    
	        '$min' =>
	            function ($value, $min) {
	                return mb_strlen($value, 'utf-8') >= $min;
	            },    
	        '$max' =>
	            function ($value, $max) {
	                return mb_strlen($value, 'utf-8') <= $max;
	            },    
	        '$contains' =>
	            function ($value, $needle) {
	                return strpos($value, $needle) !== false;
	            },    
	        '$starts' =>
	            function ($value, $string) {
	                return mb_substr($value, 0, mb_strlen($string, 'utf-8'), 'utf-8') == $string;
	            },    
	        '$ends' =>
	            function ($value, $string) {
	                return mb_substr($value, -mb_strlen($string, 'utf-8'), 'utf-8') == $string;
	            },    
	        '$json' =>
	            function ($value) {
	                return @json_decode($value) !== null;
	            },    
	        '$date' =>
	            function ($value) {
	                return strtotime($value) !== false;
	            },
	        /*
	         * Arrays
	         */    
	        '$count' =>
	            function ($value, $count) {
	                return is_array($value) && count($value) == $count;
	            },    
	        '$keys' =>
	            function ($value) {
	                if (!is_array($value)) {
	                    return false;
	                }
	                foreach (array_slice(func_get_args(), 1) as $key) {
	                    if (!array_key_exists($key, $value)) {
	                        return false;
	                    }
	                }
	                return true;
	            },
	        /*
	         * Objects
	         */    
	        '$instance' =>
	            function ($value, $class) {
	                return is_object($value) && $value instanceof $class;
	            },    
	        '$property' =>
	            function ($value, $property, $expected) {
	                return
	                    is_object($value)
	                    && (property_exists($value, $property) || property_exists($value, '__get'))
	                    && $value->$property == $expected;
	            },    
	        '$method' =>
	            function ($value, $method, $expected) {
	                return
	                    is_object($value)
	                    && (method_exists($value, $method) || method_exists($value, '__call'))
	                    && $value->$method() == $expected;
	            },
	    ];
	}
	
	/**
	 * валидация массива по указанным правилам
	 * 
	 * @param array $data
	 * @param array $rules - массив правил вида: 0 - ключи проверяемого массива, 1 - правило валидации, 2 - сообщение ошибки
	 * @return boolean
	 */
	public function validate(array $data, array $rules)
	{
		foreach ($rules as $rule) {
			$references = $this->expandKey($rule[0]);
			if ($this->validateRule($data, $references, isset($rule[1])?$rule[1]:null) === false) 
				return false;
		}
		return true;
	}
	
	/**
	 * валидация всего массива по указанным правилам, 
	 * с возвратом массива со всеми ошибками
	 * 
	 * @param array $data
	 * @param array $rules
	 * @return array|true
	 */
	public function assert(array $data, array $rules)
	{
		$result = [];
		foreach ($rules as $rule) {
			$references = $this->expandKey($rule[0]);
			if ($this->validateRule($data, $references, isset($rule[1])?$rule[1]:null) === false) 
				$result[] = 
					isset($rule[2])
					? $rule[2]
					: 'Reference "'.$rule[0].'" is invalid.';
		}
		
		if (count($result) > 0)
			return $result;
		
		return true;
	}
	
	/**
	 * валидация массива по указанным правилам, 
	 * с возвратом сообщения первой попавшейся ошибки
	 * 
	 * @param array $data
	 * @param array $rules
	 * @return string|true
	 */
	public function check(array $data, array $rules)
	{
		$result = [];
		foreach ($rules as $rule) {
			$references = $this->expandKey($rule[0]);
			if ($this->validateRule($data, $references, isset($rule[1])?$rule[1]:null) === false)
				return isset($rule[2])
					? $rule[2]
					: 'Reference "'.$rule[0].'" is invalid.';
					
		}
		
		return true;
	}
	
	/**
	 * валидация массива по указанному правилу
	 * 
	 * @param mixed $values - значение для валидации
	 * @param array $references - путь по массиву точечной нотацией
	 * @param mixed $rules - правило(а) для валидации, если null, то просто проверить на существование путь по массиву
	 * @param string $message - сообщение об ошибке
	 * @return boolean
	 */
	private function validateRule($values, array $references, $rules = null, $message = null)
	{
		foreach($references as $key => $reference) {
			// рекурсивный проход по всему нумерованному массиву
			if ($reference === '$') {
				// если это нумерованный массив
				if (is_array($values) && (array_values($values) === $values)) {
					// набираем в массив оставшийся путь, если он остался
					$tmp = ((count($references) - 1) > $key)?array_slice($references, $key + 1):[];
					$length = count($values);
					for ($i = 0; $i < $length; $i++) {
						// необходимо, чтобы хотя бы один элемент массива удовлетворял правилу
						if ($this->validateRule($values, array_merge([$i], $tmp), $rules, $message) === true) return true;
					}
				}
				return false;
			}
			
			// если указанного пути не существует, то сообщим, что правило не сработало
			if (!isset($values[$reference]) || is_null($values[$reference]))
				return false;
				
			$values = $values[$reference];	
		}
		
		// применяем правило, если оно есть – иначе валидация 
		// пройдена успешно т.к. элемент массива уже найден
		if (is_null($rules)) return true;
		
		// если правило не массив, то проверяем на равенство и всё
		if (!is_array($rules)) {
			return $values === $rules;
		} else {
			foreach ($rules as $rule) {
				// если правило не массив, то оно идёт без дополнительных аргументов
				if (!is_array($rule)) {
					if ($this->runRule($rule, [$values]) === false) return false;
				} else {
					// если это не ассоциативный массив, то правило задано неверно
					if (array_values($rule) === $rule)
						throw new \InvalidArgumentException(
							'Rule "'.print_r($rule, true).'" is invalid. Rule with args must be associative array.'
						);
					
					// правило вида [<rul_name> => <array_of_args>]
					$tmp = each($rule);
					$name = $tmp['key'];
					$args = is_array($tmp['value'])?$tmp['value']:[$tmp['value']]; // делаем в любом случае массивом
					
					if ($this->runRule($name, array_merge([$values], $args)) === false) return false;
				}
			}
			return true;
		}
	}
	
    /**
     * возвращаем существующее правило по имени или ничего
     *
     * @param string $name
     * @return callable|string|false
     * @throw \InvalidArgumentException
     */
    private function getRule($name)
    {
	    if (is_string($name) === false)
			trigger_error('Method '.__METHOD__.' of '.__CLASS__.' expected Argument 1 to be String', E_USER_WARNING);
		
	    if (!isset($this->rules[$name]))
			throw new \InvalidArgumentException("Rule $name not found");
		
	    return $this->rules[$name];
	}
	
    /**
     * запускаем правило для проверки данных
     *
     * @param string $name
     * @param array $args
     * @return boolean
     */
    private function runRule($name, array $args = [])
    {
	    $func = $this->getRule($name);
	    
	    if (is_callable($func))
	    	return call_user_func_array($func, $args);
		else
			return $func($args[0]);
        
	}
	
    /**
     * Expand a key into an array of references.
     *
     * @param string $key - Key to expand.
     * @return array - Array of references.
     */
    private function expandKey($key)
    {
        $result = array();
        $hold   = "";
        $references = explode(".", $key);
        $last = count($references) - 1;
        foreach ($references as $i => $reference)
        {
            if ($reference[strlen($reference) - 1] === '\\' &&
                $i < $last)
            {
                $hold .= substr($reference, 0, -1) . ".";
            }
            else if (!empty($hold))
            {
                $result[] = $hold . $reference;
                $hold = "";
            }
            else
            {
                $result[] = $reference;
            }
        }
        return $result;
    }
}