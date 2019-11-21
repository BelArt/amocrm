<?php

namespace App\Common\Form\Auth;

use Phalcon\Forms\Form,
	Phalcon\Forms\Element\Text,
	Phalcon\Forms\Element\Password,
	Phalcon\Forms\Element\Submit,
	Phalcon\Forms\Element\Check,
	Phalcon\Validation\Validator\PresenceOf;

class LoginForm extends Form
{
    public function initialize()
    {
        $this
        	->add(
        		(new Text('name', ['placeholder' => 'Логин']))
	        		->addValidator(new PresenceOf(['message' => 'Не стоит оставлять это пустым']))
        	)
        	->add(
	        	(new Password('pass', ['placeholder' => 'Пароль']))
	        		->addValidator(new PresenceOf(['message' => 'Не стоит оставлять это пустым']))
        	)
			->add(
				(new Check('rem', array('value' => 'yes')))
					->setLabel('Запомнить меня')
			)
        	->add(
				new Submit('submit', ['class' => 'btn btn-lg btn-primary btn-block', 'value' => 'Войти'])
			)
		;
    }
}