<?php

namespace App\Common\Plugin;

/**
 * аннотация для экшенов контроллеров для валидации входных параметров
 */
class ParamsAnnotationPlugin extends \Phalcon\Mvc\User\Plugin
{
    /**
     * это событие запускается перед запуском каждого маршрута в диспетчере
     */
    public function beforeExecuteRoute($event, $dispatcher)
    {
// 	    $annotations = $this->annotations->getMethods($dispatcher->getActiveController());
	    $annotations = $this->annotations->getMethods($dispatcher->getControllerClass());
	    
	    if (!$annotations) return true;
	    
	    $method = strtolower($dispatcher->getActiveMethod());
	    $annotations = array_change_key_case($annotations);
	    
	    // анотаций у метода может и не быть
	    if (!isset($annotations[$method])) return true;
	    
	    // так не чувствительно к регистру
	    $annotations = $annotations[$method];
        
        if ($annotations->has('Params')) {
            $annotation = $annotations->get('Params');
            
            if ($params = $annotation->getArgument(0)) {
	            $errors = (new \App\Common\Library\Validation\Validation(new \Phalcon\Validation()))
	            	->validate(
						$params, 
						$this->request->get(), 
						true
					);
				
				if ($errors)
					throw new \App\Common\Exception\JsonException($errors, 200);	
            }
        }
    }
}