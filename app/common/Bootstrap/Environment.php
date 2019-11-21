<?php

namespace App\Common\Bootstrap;

/**
 * класс для создания окружения, чтобы в дальнейшем в зависимости от него менять поведение
 * создаётся с помощью статического вызова несуществующего метода, где имя и будет название окружения
 * Application::dev()
 */
final class Environment
{
    /**
     * The specified environment.
     * @var string
     */
    protected $slug;

    private function __construct($slug)
    {
        $slug = trim($slug);
        if (!in_array($slug, ['prod', 'dev', 'test'])) {
            throw new \Exception('Invalid environment specified.');
        }
        $this->slug = (string) $slug;
    }

    /**
     * в зависимости от того какой метод вызывается такое окружение и создаётся
     */
    public static function __callStatic($name, $args)
    {
        return new self($name);
    }

    /**
     * @return Environment
     */
    public static function fromEnvironmentVariable()
    {
        return new self(isset($_SERVER['MMCOEXPO_ENV'])?$_SERVER['MMCOEXPO_ENV']:'prod');
    }

    /**
     * @param $environmentString
     *
     * @return Environment
     */
    public static function fromString($environmentString)
    {
        return new self($environmentString);
    }

    /**
     * @param $path
     *
     * @return Environment
     */
    public static function fromFile($path)
    {
        return new self(file_exists($path)?file_get_contents($path):'prod');
    }

    /**
     * @param  Environment $environment
     * @return bool
     */
    public function equals(Environment $environment)
    {
        return $this->slug === (string) $environment;
    }

    public function __toString()
    {
        return $this->slug;
    }
}
