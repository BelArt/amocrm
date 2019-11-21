<?php

namespace App\Common\Form\Auth;

use Phalcon\Forms\Form,
	Phalcon\Forms\Element\Text,
	Phalcon\Forms\Element\Submit,
	Phalcon\Validation\Validator\PresenceOf;

class ForgotPasswordForm extends Form
{
    public function initialize()
    {
        $this
        	->add(
        		(new Text('name', ['placeholder' => 'Введите Ваш логин']))
	        		->addValidator(new PresenceOf(['message' => 'Не стоит оставлять это пустым']))
        	)
        	->add(
				new Submit('submit', ['class' => 'btn btn-lg btn-primary btn-block', 'value' => 'Отправить'])
			)
		;
    }
}