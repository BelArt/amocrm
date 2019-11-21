<?php

namespace App\Common\Library\VoltEngine;

class VoltEngine extends \Phalcon\Mvc\View\Engine\Volt
{
	public function getCompiler()
	{
		if (empty($this->_compiler)) {
			parent::getCompiler();
		}
		// добавим свои кастомные фильтры
		$this->_compiler->addExtension(new FilterExtension());
		return $this->_compiler;
	}
}