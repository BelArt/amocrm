<?php

namespace App\Common\Library\Validation;

/**
 * декоратор для стандартного валидатора \Phalcon\Validation
 * реализует дополнительное поведение и упрощает использование стандартных валидаторов
 * каждый валидатор приравнен к определённому типу и можно использовать тип для указания какой валидатор использовать
 * но если это не стандартный валидатор, то допускается передать вместо типа уже объект своего валидатора
 */
class Validation
{
	// станадртные типы валидаторв приравненые к своим неймспейсам
	private $types = [
		'presence_of' => 'Phalcon\Validation\Validator\PresenceOf',
		'identical' => 'Phalcon\Validation\Validator\Identical',
		'email' => 'Phalcon\Validation\Validator\Email',
		'exclusion_in' => 'Phalcon\Validation\Validator\ExclusionIn',
		'inclusion_in' => 'Phalcon\Validation\Validator\InclusionIn',
		'regex' => 'Phalcon\Validation\Validator\Regex',
		'string_length' => 'Phalcon\Validation\Validator\StringLength',
		'between' => 'Phalcon\Validation\Validator\Between',
		'confirmation' => 'Phalcon\Validation\Validator\Confirmation',
		
		'phone' => 'App\Common\Validation\PhoneValidator',
	];
	
	private $validation = null;
	
	/**
	 * в конструктор передаётся стандартный объект валидатора
	 * @param \Phalcon\Validation $validation
	 */ 
	public function __construct(\Phalcon\Validation $validation) {
		$this->validation = $validation;
	}
	
	/**
	 * проведение валидации указанной информации в соответствие с требованиями
	 * @param array $fields - массив с требованиями к валидности array(array(<field_name>, [<validate_type>, <validate_settings>]))
	 *  - <field_name> - имя валидируемого поля
	 *	- <validate_type> - тип стандартного валидатора, либо namespace своего валидатора,
	 *		если ничего не указано, то прост опроверяем чтобы поле было не пустое (PresenceOf)
	 *	- <validate_settings> - настройки для валидаторов, если не указан, то используются дефолтовые настроки
	 * элемент массива может быть как массив, так и строка (имя поля), например [[name1, type], name2, [name3, type, settings]]
	 * @param array|object $data - валидируемые данные
	 * @param boolean $named - возвращать в виде именнованного массива либо объект Phalcon\Validation\Message\Group
	 * @return array|Phalcon\Validation\Message\Group
	 */ 
	public function validate(array $fields, $data, $named = false) {
		foreach ($fields as $field) {
			// если это просто строка, то значит там имя поля и по умолчанию валидатор PresenceOf
			if (is_string($field)) {
				$this->validation->add($field, new $this->types['presence_of']());
				continue;	
			}
			
			$val = null;
			if (class_exists($field[1])) {
				$val = new $field[1](isset($field[2])?$field[2]:[]);
			} else {
				if (isset($field[1])) {
					if (!isset($this->types[$field[1]]))
						throw new \Exception('Missing type of standard validator: "'.$field[1].'". You can use: '.implode(', ', array_keys($this->types)).'.', 500);
					
					// проверим есть ли настройки для валидатора или использовать дефолтовые
					$val = new $this->types[$field[1]](isset($field[2])?$field[2]:[]);
				} else {
					$val = new $this->types['presence_of']();
				}
			}
			
			$this->validation->add($field[0], $val);
		}
		
		$results = null;
		if (is_object($data))
			$results = $this->validation->validate(null, $data);
		else
			$results = $this->validation->validate($data);
        
        if (!$named) return $results;
        
        $tmp = [];
		foreach ($results as $result) 
			$tmp[$result->getField()] = $result->getMessage();
		return $tmp;
	}
}