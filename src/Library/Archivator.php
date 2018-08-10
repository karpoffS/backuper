<?php

namespace Library;


use Interfaces\ArchivatorInterface;
use Interfaces\CompressorInterface;

/**
 * Class Archivator
 *
 * @package Library
 */
class Archivator implements ArchivatorInterface
{
	/**
	 * @var  CompressorInterface
	 */
	private $compressor;

	/**
	 * Archivator constructor.
	 * @param string $fileName
	 * @param string $ext
	 */
	public function __construct(string $fileName, string $ext)
	{
		$this->compressor = new Compressor($fileName, $ext);
	}

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function setSavePath(string $path)
	{
		return $this->compressor->setSavePath($path);
	}
	
	/**
	 * @return string
	 */
	public function getFullFileName()
	{
		return $this->compressor->getFullFileName();
	}

	public function getFullPathForSaveFileName(){
		return $this->compressor->getFullPathForSaveFileName();
	}

	public function setIgnoreFailedRead(bool $bool)
	{
		$this->compressor->setIgnoreFailedRead($bool);
	}

	/**
	 * @param string $path
	 */
	public function setPath(string $path)
	{
		$this->compressor->setPath($path);
	}

	/**
	 * @param string $path
	 */
	public function addInclude(string $path)
	{
		$this->compressor->addInclude($path);
	}

	/**
	 * @param string $path
	 */
	public function addExclude(string $path)
	{
		$this->compressor->addExclude($path);
	}

	/**
	 * @return string
	 */
	public function compile()
	{
		return $this->compressor->compile();
	}

	public static function getSupported(): array
	{
		return Compressor::getSupported();
	}
}