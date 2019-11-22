<?php
	
namespace App\Common\Task;

/**
 * Задача-CRUD для работы с пользователями
 */
class AssetTask extends \Phalcon\Cli\Task
{
	/**
	 * генерация dump
	 * asset:dump 
	 */
    public function dumpAction() {
	    if (!isset($this->config['asset'])) die('Compile nothing');
	    $asset = $this->config['asset'];
			
	    // нужно ли компилировать less?
	    if (isset($asset['less']) && count($asset['less'])) {			
			foreach ($asset['less'] as $files) {
				$css = '';
				foreach ((is_string($files['input'])?[$files['input']]:$files['input']->toArray()) as $file) {
					if (!is_readable($file)) 
						throw new \Exception('load error: failed to find '.$file);
						
					$css .= $this->less->compileFile($file);
				}
				
				if (!isset($files['output']) && !is_readable($files['output']))
					throw new \Exception('load error: failed dir.');
				
				@mkdir(dirname($files['output']));
				
				if ($css)
					file_put_contents($files['output'], $this->cssmin->filter($css));
			}
			echo 'LESS compiled!'."\n";
	    }
			
	    // нужно ли мержить и минимизировать css?
	    if (isset($asset['css']) && count($asset['css'])) {
			foreach ($asset['css'] as $files) {
				$css = '';
				foreach ((is_string($files['input'])?[$files['input']]:$files['input']->toArray()) as $file) {
					if (!is_readable($file)) 
						throw new \Exception('load error: failed to find '.$file);
						
					$css .= file_get_contents($file);
				}
				
				if (!isset($files['output']) && !is_readable($files['output']))
					throw new \Exception('load error: failed dir.');
				
				@mkdir(dirname($files['output']));
				
				if ($css && $files['output'])
					file_put_contents($files['output'], $this->cssmin->filter($css));
			}
			echo 'CSS minfied!'."\n";
	    }
			
	    // нужно ли мержить и минимизировать js?
	    if (isset($asset['js']) && count($asset['js'])) {
			foreach ($asset['js'] as $files) {
				$js = '';
				foreach ((is_string($files['input'])?[$files['input']]:$files['input']->toArray()) as $file) {
					if (!is_readable($file)) 
						throw new \Exception('load error: failed to find '.$file);
						
					$js .= file_get_contents($file);
				}
				
				if (!isset($files['output']) && !is_readable($files['output']))
					throw new \Exception('load error: failed dir.');
				
				@mkdir(dirname($files['output']));
				
				if ($js && $files['output'])
					file_put_contents($files['output'], $this->jsmin->filter($js));
			}
			echo 'JS minfied!'."\n";
	    }
			
	    // перенесём указанные фонты в паблик-папку
	    if (isset($asset['font'])) {
		    $files = $asset['font'];
			if (!isset($files['output']) && !is_readable($files['output']))
				throw new \Exception('load error: failed dir.');
				
			@mkdir($files['output']);
			
			foreach ((is_string($files['input'])?[$files['input']]:$files['input']->toArray()) as $file) {
				if (!is_dir($file)) 
					throw new \Exception('load error: failed dir.');
					
				$this->recurseCopy($file, $files['output']);
			}
			echo 'Fonts moved!'."\n";
	    }
	    
	    echo 'Done!'."\n";
    }
    
    /**
	 * перенос файла из одной директории в другую, рекурсивный...
	 * @param string $src
	 * @param string $dst
	 */
    private function recurseCopy($src, $dst) { 
		$dir = opendir($src); 
		@mkdir($dst); 
		while(false !== ( $file = readdir($dir)) ) { 
		    if (( $file != '.' ) && ( $file != '..' )) { 
		        if ( is_dir($src . '/' . $file) ) { 
		            recurse_copy($src . '/' . $file,$dst . '/' . $file); 
		        } 
		        else { 
		            copy($src . '/' . $file,$dst . '/' . $file); 
		        } 
		    } 
		} 
		closedir($dir); 
	} 
}