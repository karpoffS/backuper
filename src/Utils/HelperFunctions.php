<?php

namespace Utils;

/**
 * Class HelperFunctions
 *
 * @package Utils
 */
class HelperFunctions
{
	/**
	 * @param string $path
	 * @return string
	 */
	public static function generatePath(string $path)
	{
		if(substr($path, 0,1) === DIRECTORY_SEPARATOR){
			$fullPath = $path;
		} else {
			if(substr($path, 0,2) === $first = '.'.DIRECTORY_SEPARATOR){
				$fullPath = implode(
					DIRECTORY_SEPARATOR,
					[ BASE_DIR , ltrim($path, $first)]
				);

			} else {
				$fullPath = implode(DIRECTORY_SEPARATOR, [BASE_DIR , $path]);
			}
		}
		 dump($fullPath);
		return  $fullPath;
	}

	public static function  hash_file_multi($algos = [], $filename) {
		if (!is_array($algos)) {
			throw new \InvalidArgumentException('First argument must be an array');
		}

		if (!is_string($filename)) {
			throw new \InvalidArgumentException('Second argument must be a string');
		}

		if (!file_exists($filename)) {
			throw new \InvalidArgumentException('Second argument, file not found');
		}

		$result = [];
		$result['file'] = basename($filename);
		$fp = fopen($filename, "r");
		if ($fp) {
			// ini hash contexts
			foreach ($algos as $algo) {
				$ctx[$algo] = hash_init($algo);
			}

			// calculate hash
			while (!feof($fp)) {
				$buffer = fgets($fp, 65536);
				foreach ($ctx as $key => $context) {
					hash_update($ctx[$key], $buffer);
				}
			}

			// finalise hash and store in return
			foreach ($algos as $algo) {
				$result[$algo] = hash_final($ctx[$algo]);
			}

			fclose($fp);
		} else {
			throw new \InvalidArgumentException('Could not open file for reading');
		}
		return $result;
	}
}