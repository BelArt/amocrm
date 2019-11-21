<?php

namespace App\Common\Library\VoltEngine;

class FilterExtension
{
	public function compileFilter($name, $arguments)
	{
		if ($name == 'instanceof') {
			$args = explode(', ', $arguments);
			if (count($args) == 2)
				return $args[0].' '.$name.' ' .trim($args[1], '\'"');
		}
		
		if ($name == 'merge') {
			$args = explode(', ', $arguments);
			$args = array_map(function($array) {
				return '(array)'.$array;
			}, $args);
			return 'array_merge('.implode(', ', $args).')';
		}
	}
}