<?php

namespace App\Common\Validation;

use Phalcon\Mvc\Model\Validator,
	Phalcon\Mvc\Model\ValidatorInterface;

/**
 * валидация на непустое содержимое проверямого поля для ODM
 */
class NotEmptyValidator extends Validator implements ValidatorInterface
{
    public function validate(\Phalcon\Mvc\EntityInterface $record)
    {
        $field = $this->getOption('field');
        if ($field === null || $field == '') {
	        $message = $this->getOption('message');
            $this->appendMessage($message?:'The '.$field.' must not be empty', $field, 'NotEmpty');
            return false;
        }
        return true;
    }
}