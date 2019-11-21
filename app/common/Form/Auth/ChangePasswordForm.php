<?php

namespace App\Common\Form\Auth;

use Phalcon\Forms\Form,
	Phalcon\Forms\Element\Password,
	Phalcon\Forms\Element\Submit,
	Phalcon\Forms\Element\Hidden,
	Phalcon\Validation\Validator\PresenceOf,
	Phalcon\Validation\Validator\StringLength,
	Phalcon\Validation\Validator\Confirmation,
	Phalcon\Validation\Validator\Identical;

class ChangePasswordForm extends Form
{
    public function initialize()
    {
        $this
        	->add(
        		(new Password('pass', ['placeholder' => 'Новый пароль']))
	        		->addValidators([
						new PresenceOf(['message' => 'Введите новый пароль']) ,
						new StringLength(['min' => 8 , 'messageMinimum' => 'Пароль слишком короткий. Минимум 8 символов']) ,
						new Confirmation(['message' => 'Пароль не совпадает с подтверждением ниже' , 'with' => 'confirmPass'])
					])
        	)
        	->add(
        		(new Password('confirmPass', ['placeholder' => 'Подтверждение нового пароля']))
	        		->addValidator(new PresenceOf(['message' => 'Введите новый пароль']))
        	)
        	->add(
	        	(new Hidden('csrf', ['value' => $this->security->getSessionToken()]))
	        		->addValidator(new Identical([ 
		            	'value' => $this->security->getSessionToken(), 
		            	'message' => 'Похоже форма протухла, заполните заного, пожалуйста.' 
					]))
        	)
        	->add(
				new Submit('submit', ['class' => 'btn btn-lg btn-primary btn-block', 'value' => 'Отправить'])
			)
		;
    }
}