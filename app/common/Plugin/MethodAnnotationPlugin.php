<?php

namespace App\Common\Plugin;

/**
 * аннотация для экшенов контроллеров для валидации доступного метода запроса
 */
class MethodAnnotationPlugin extends \Phalcon\Mvc\User\Plugin
{
    /**
     * это событие запускается перед запуском каждого маршрута в диспетчере
     */
    public function beforeExecuteRoute(\Phalcon\Events\Event$event, \Phalcon\Mvc\Dispatcher $dispatcher)
    {
	    // Разбор аннотаций в текущем запущенном методе
/*
        $annotations = $this->annotations->getMethod(
            $dispatcher->getControllerClass(),
            $dispatcher->getActiveMethod()
        );
*/
        
// 	    $annotations = $this->annotations->getMethods($dispatcher->getActiveController());
	    $annotations = $this->annotations->getMethods($dispatcher->getControllerClass());
	    
	    if (!$annotations) return true;
	    
	    $method = strtolower($dispatcher->getActiveMethod());
	    $annotations = array_change_key_case($annotations);
	    
	    // анотаций у метода может и не быть
	    if (!isset($annotations[$method])) return true;
	    
	    // так не чувствительно к регистру
	    $annotations = $annotations[$method];
        
        if ($annotations->has('Method')) {
            $annotation = $annotations->get('Method');
            
            if ($methods = $annotation->getArguments()) {
                foreach ($methods as $method)
					if ($this->request->isMethod(strtoupper($method)))
						return true;
				
		    	throw new \Exception('This HTTP-request method is not supported. You can use: '.implode(', ', $methods).'.', 200);
            }
        }
    }
}