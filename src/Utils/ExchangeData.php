<?php

namespace Utils;

/**
 * Class ExchangeData
 *
 * @package Utils
 */
class ExchangeData
{
	const NAME_FILE_DB = 'backups.json';
	const KEY_CURRENT = 'current';
	const KEY_PREVIOUS = 'previous';

	/**
	 * @var string
	 */
	private $filedb = '';

	/**
	 * @var array 
	 */
	private $current = [];

	/**
	 * @var array 
	 */
	private $data =  [
		self::KEY_CURRENT => [],
		self::KEY_PREVIOUS => []
	];

	/**
	 * ExchangeData constructor.
	 *
	 * @param string $cachePath
	 */
	public function __construct(string $cachePath)
	{
		$this->filedb = implode(
			DIRECTORY_SEPARATOR,
			[
				rtrim($cachePath, DIRECTORY_SEPARATOR),
				self::NAME_FILE_DB
			]
		);

		if(file_exists($this->filedb)){
			if(is_array($readDate = json_decode(file_get_contents($this->filedb), true))){
				$this->data = array_merge($this->data, $readDate);
			}
		}
	}

	/**
	 * @return array
	 */
	public function readData()
	{
		return $this->data;
	}

	/**
	 * @return mixed
	 */
	public function getUploadFiles()
	{
		return $this->data[self::KEY_CURRENT];
	}

	/**
	 * @param array $file
	 */
	public function addCurrentFile(array $file)
	{
		$this->current[] = $file;
	}

	public function clearUploadedFilesAndSave()
	{
		$this->data[self::KEY_CURRENT] = [];
		return $this->saveData();
	}

	/**
	 * @return bool|int
	 */
	public function saveData()
	{
		if(count($this->current) > 0){
			$this->data[self::KEY_PREVIOUS] = array_merge(
				$this->data[self::KEY_CURRENT],
				$this->data[self::KEY_PREVIOUS]
			) ;
			$this->data[self::KEY_CURRENT] = $this->current;
		}
		return file_put_contents($this->filedb, json_encode($this->data, JSON_PRETTY_PRINT) );
	}
}