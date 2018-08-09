<?php

namespace Library;

use Interfaces\CompressorInterface;

/**
 * Class Compressor
 *
 * @package Library
 */
class Compressor  implements CompressorInterface
{
	protected $template = 'tar %s %s %s %s';

	/**
	 * Directry separator short format
	 */
	const DS = DIRECTORY_SEPARATOR;

    /**
     * @var null|string
     */
    protected $type = null;

    /**
     * @var bool
     */
    protected $ignoreFailedRead = false;
    
	/**
	 * @var  string
	 */
	private $path;

	/**
	 * @var array
	 */
	private $includes = [];

	/**
	 * @var array
	 */
	private $excludes = [];

	/**
	 * Compressor constructor.
	 *
	 * @param string $type
	 */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

	/**
	 * @param bool $bool
	 */
	public function setIgnoreFailedRead(bool $bool)
	{
		$this->ignoreFailedRead = $bool;
	}

	/**
	 * @param string $path
	 */
	public function setPath(string $path)
	{
		$this->path = $path;
	}

	/**
	 * @param string $path
	 */
	public function addInclude(string $path)
	{
		$this->includes[] = $path;
	}

	/**
	 * @param string $path
	 */
	public function addExclude(string $path)
	{
		$this->excludes[] = $path;
	}

	/**
	 * @param string $archivePath
	 * @return string
	 */
	public function compile(string $archivePath)
	{
		// Получаем пути для включения архивации
		$includes = $this->generateIncludes();

		// Получаем пути исключённые из процесса архивации
		$excludes = $this->generateExcludes();

		$compress = '-cvf';
		$archiveFullPath = implode('.',[$archivePath, CompressorInterface::COMPRESSOR_TAR]);

		if ($this->type === CompressorInterface::COMPRESSOR_GZIP){
			$compress = '-cvzf';
			$archiveFullPath = implode(
				'.',
				[
					$archivePath,
					CompressorInterface::COMPRESSOR_TAR,
					CompressorInterface::COMPRESSOR_GZIP
				]
			);
		} elseif ($this->type === CompressorInterface::COMPRESSOR_BZIP2){
			$compress = '-cvjf';
			$archiveFullPath = implode(
				'.',
				[
					$archivePath,
					CompressorInterface::COMPRESSOR_TAR,
					CompressorInterface::COMPRESSOR_BZIP2
				]
			);
		} elseif ($this->type === CompressorInterface::COMPRESSOR_LZMA){
			$compress = '-cvf --lzma';
			$archiveFullPath = implode(
				'.',
				[
					$archivePath,
					CompressorInterface::COMPRESSOR_TAR,
					CompressorInterface::COMPRESSOR_LZMA
				]
			);
		}

		$cmd = sprintf($this->template, $compress, '"'.$archiveFullPath.'"', $includes, $excludes);
		$cmd = trim($cmd);
		if($this->ignoreFailedRead){
			$cmd.= ' --ignore-failed-read';
		}
		return $cmd;
	}

	/**
	 * @return string
	 */
    private function generateIncludes()
    {

	    if(count($this->includes)){
		    return implode(
			    self::DS,
			    array_map(
				    function ($include){
					    return ' '.implode(
						    self::DS,
						    [
							    rtrim($this->path, self::DS),
							    ltrim($include, self::DS)
						    ]
					    );
				    },
				    $this->includes
			    )
		    );
	    }
	    
		return $this->path;
    }

	/**
	 * @return string
	 */
    private function generateExcludes()
    {

	    if(count($this->excludes)){
		    return implode(
			    self::DS,
			    array_map(
				    function ($exclude){
					    return ' --exclude='.implode(
						    self::DS,
						    [
							    rtrim($this->path, self::DS),
							    ltrim($exclude, self::DS)
						    ]
					    );
				    },
				    $this->excludes
			    )
		    );
	    }

		return '';
    }

	/**
	 * @return array
	 */
	public static function getSupported(): array
	{
		return [
			CompressorInterface::COMPRESSOR_TAR,
			CompressorInterface::COMPRESSOR_GZIP,
			CompressorInterface::COMPRESSOR_BZIP2,
			CompressorInterface::COMPRESSOR_LZMA
		];
	}
}