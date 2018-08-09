<?php

namespace Interfaces;

/**
 * Interface ArchivatorInterface
 *
 * @package Interfaces
 */
interface ArchivatorInterface
{
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
	public function addExclude(string $path);

	/**
	 * @param string $path
	 */
	public function addInclude(string $path);

	/**
	 * @return string
	 */
	public function compile();

	/**
	 * @return array
	 */
	public static function getSupported():array;
}