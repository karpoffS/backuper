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
	 * @var  string
	 */
	private $fileName;

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
		$this->fileName = $fileName;
		$this->compressor = new Compressor($ext);
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
		return $this->compressor->compile($this->fileName);
	}

	public static function getSupported(): array
	{
		return Compressor::getSupported();
	}
}