<?php

namespace App\Common\Bootstrap;

/**
 * класс для создания типа приложения, чтобы в дальнейшем в зависимости от типа менять поведение
 * создаётся с помощью статического вызова несуществующего метода, где имя и будет типом приложения
 * Application::api()
 */
final class Application
{
	/**
	 * The specified application.
	 * @var string
	 */
	protected $slug;

	private function __construct($slug)
	{
		if (!in_array($slug, ['api', 'gui', 'cli', 'core', 'console'])) {
			throw new \Exception('Invalid application specified.');
		}
		$this->slug = (string) $slug;
	}

	/**
	 * в зависимости от того какой метод вызывается такой тип приложение и создаётся
	 */
	public static function __callStatic($name, $args)
	{
		return new self($name);
	}

	/**
	 * @param $applicationString
	 *
	 * @return Application
	 */
	public static function fromString($applicationString)
	{
		return new self($applicationString);
	}

	/**
	 * @param  Application $application
	 * @return bool
	 */
	public function equals(Application $application)
	{
		return $this->slug === (string) $application;
	}

	public function __toString()
	{
		return $this->slug;
	}
}