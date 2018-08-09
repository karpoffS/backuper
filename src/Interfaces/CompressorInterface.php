<?php

namespace Interfaces;

/**
 * Class CompressorInterface
 *
 * @package Interfaces
 */
interface CompressorInterface
{
    const COMPRESSOR_TAR = 'tar';
    const COMPRESSOR_GZIP = 'gz';
    const COMPRESSOR_BZIP2 = 'bz2';
    const COMPRESSOR_LZMA = 'lzma';

	/**
	 * @param bool $bool
	 */
	public function setIgnoreFailedRead(bool $bool);

	/**
	 * @param string $path
	 */
	public function setPath(string $path);

	/**
	 * @param string $path
	 */
	public function addInclude(string $path);

	/**
	 * @param string $path
	 */
	public function addExclude(string $path);

	/**
	 * @param string $archivePath
	 * @return mixed
	 */
	public function compile(string $archivePath);

	/**
	 * @return array
	 */
	public static function getSupported():array;
}