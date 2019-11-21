<?php

namespace App\Common\Validation;

use Phalcon\Mvc\Model\Validator,
	Phalcon\Mvc\Model\ValidatorInterface;

/**
 * валидация на уникальность для ODM
 */
class UniqueValidator extends Validator implements ValidatorInterface
{
    public function validate(\Phalcon\Mvc\EntityInterface $record)
    {
		$idValue = $record->readAttribute('_id');
		$field = $this->getOption('field');
		$canBeEmpty = $this->getOption('can_be_empty');
		$fieldValue = $record->readAttribute($field);

		// может быть пустым
		if (is_null($fieldValue) && $canBeEmpty) return true;
		
		$conditions = array($field => $fieldValue);
		if (isset($idValue)) {
			$conditions['_id'] = array('$ne' => $idValue);
		}
		
		if ($record->count(array('conditions' => $conditions))) {
			$this->appendMessage("The " . $field . " must be unique", $field, "Unique");
			return false;
		}
		
		return true;
    }
}