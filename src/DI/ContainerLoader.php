<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;


/**
 * DI container loader.
 *
 * @author     David Grudl
 */
class ContainerLoader extends Nette\Object
{
	/** @var bool */
	private $autoRebuild = FALSE;

	/** @var string */
	private $tempDirectory;


	public function __construct($tempDirectory, $autoRebuild = FALSE)
	{
		$this->tempDirectory = $tempDirectory;
		$this->autoRebuild = $autoRebuild;
	}


	/**
	 * @param  mixed
	 * @param  callable  function(string $class): [code, files]
	 * @return string
	 */
	public function load($key, $generator)
	{
		$class = $this->getClassName($key);
		if (!class_exists($class)) {
			$this->loadFile($class, $generator);
		}
		return $class;
	}


	/**
	 * @return string
	 */
	public function getClassName($key)
	{
		return 'Container_' . substr(md5(serialize($key)), 0, 10);
	}


	/**
	 * @return void
	 */
	private function loadFile($class, $generator)
	{
		$file = "$this->tempDirectory/$class.php";
		if (!$this->isExpired($file) && (@include $file) !== FALSE) {
			return;
		}

		if (!is_dir($this->tempDirectory)) {
			@mkdir($this->tempDirectory); // @ - directory may already exist
		}

		$handle = fopen("$file.lock", 'c+');
		if (!$handle || !flock($handle, LOCK_EX)) {
			throw new Nette\IOException("Unable to acquire exclusive lock on '$file.lock'.");
		}

		if (!is_file($file) || $this->isExpired($file)) {
			list($code, $files) = call_user_func($generator, $class);

			$files = $files ? array_combine($files, $files) : array();
			$toWrite["$file.meta"] = serialize(@array_map('filemtime', $files)); // @ - file may not exist
			$toWrite[$file] = "<?php\n$code";

			foreach ($toWrite as $name => $content) {
				if (file_put_contents("$name.tmp", $content) !== strlen($content) || !rename("$name.tmp", $name)) {
					@unlink("$name.tmp"); // @ - file may not exist
					throw new Nette\IOException("Unable to create file '$name'.");
				}
			}
		}

		if ((@include $file) === FALSE) { // @ - error escalated to exception
			throw new Nette\IOException("Unable to include '$file'.");
		}
		flock($handle, LOCK_UN);
	}


	private function isExpired($file)
	{
		if ($this->autoRebuild) {
			$meta = @unserialize(file_get_contents("$file.meta")); // @ - files may not exist
			$files = $meta ? array_combine($tmp = array_keys($meta), $tmp) : array();
			return $meta !== @array_map('filemtime', $files); // @ - files may not exist
		}
		return FALSE;
	}

}