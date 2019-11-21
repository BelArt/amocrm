<?php

namespace App\Common\Validation;

use Phalcon\Validation\Validator,
    Phalcon\Validation\ValidatorInterface,
    Phalcon\Validation\Message;

/**
 * валидация значения поля от пользователя на наличие телефонного номера, виды следующие:
 *  555-555-5555
 *  5555425555
 *  555 555 5555
 *  1(519) 555-4444
 *  1 (519) 555-4422
 *  1-555-555-5555
 *  1-(555)-555-5555
 */
class PhoneValidator extends Validator implements ValidatorInterface
{
	/**
     * Выполнение валидации
     *
     * @param Phalcon\Validation $validator
     * @param string $attribute
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validation, $attribute)
    {
        $value = $validation->getValue($attribute);
        $regex = "/^((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{7,10}$/i";
        if (!preg_match($regex, $value)) {
	        $message = $this->getOption('message');
            $validation->appendMessage(new Message($message?:'Field must contain phone number', $attribute, 'Phone'));
            return false;
        }
        return true;
    }
}