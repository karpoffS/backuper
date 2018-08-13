<?php

namespace Command;

use Interfaces\CompressorInterface;
use Library\Archivator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Finder\Finder;
use Utils\ExchangeData;
use Utils\HelperFunctions;

/**
 * Class BackupStartCommand
 * @package Command
 */
class BackupStartCommand extends Command
{
	/**
	 * @var array
	 */
	private $config = [];

	/**
	 * @var string|null
	 */
	private $backupPath = null;

	/**
	 * @var string
	 */
	private $defaultCompressor;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var Finder
	 */
	private $finder;

	/**
	 * @var array
	 */
	protected $defaultItem = [
        'compressor' => '',
		'ignoreFailedRead' => false,
        'path' => '',
		'include' => [],
        'exclude' => []
	];


	/**
	 * BackupStartCommand constructor.
	 * @param array $config
	 * @param LoggerInterface $logger
	 * @param string|null $name
	 */
	public function __construct(array $config, LoggerInterface $logger, string $name = null)
	{
		$this->finder = new Finder();
		$this->logger = $logger;
		$this->config = $config['backuper'];
		$this->backupPath = BACKUP_PATH;

		if(isset($config['backuper']['ignoreFailedRead'])){
			$this->config['ignoreFailedRead'] = (bool) $config['backuper']['ignoreFailedRead'];
		} else {
			$this->config['ignoreFailedRead'] = false;
		}

		if(isset($this->config['default_compressor'])){
			if($this->config['default_compressor'] === null){
				$this->defaultCompressor = CompressorInterface::COMPRESSOR_TAR;
			} else {
				if(!in_array($this->config['default_compressor'], Archivator::getSupported())){
					$this->logger->info(
						'Compressor is not supported',
						['default_compressor' => $this->config['default_compressor'] ]
					);
					$this->defaultCompressor = CompressorInterface::COMPRESSOR_TAR;
				} else {
					$this->defaultCompressor = $this->config['default_compressor'];
				}
			}
		} else {
			$this->defaultCompressor = CompressorInterface::COMPRESSOR_TAR;
		}
		parent::__construct($name);
	}

	/**
	 * Настройка команды
	 */
	protected function configure()
	{
		$this
			->setName('backup:start')
			->setDescription('Выполняет резервное копирование файлов')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		try{

			// Если есть что что бекапить
			if(isset($this->config['folders']) && is_array($this->config['folders'])){

				// Загружаем предыдущие данные
				$exchange = new ExchangeData(CACHE_PATH);

				$folders = $this->config['folders'];
				foreach ($folders as $name=>$item) {

					$nowDate = new \DateTime('now');
					$humanDate = $nowDate->format($this->config['format_date']);

					// Получаем текущие настройки
					if(is_array($item)){
						$settings = $this->getItemSettings($item);
					} else {
						$settings = $this->getItemSettings([ 'path' => $item]);
					}

					if(isset($this->config['cleanBackups'])){
						$this->finder->in($this->backupPath)->files()
							->name($name.'*')->date($this->config['cleanBackups']);

						foreach ($this->finder as $file) {
							if(unlink($file->getRealPath())){
								$this->logger->notice(
									'[Delete]: Older backup file '. $file->getRelativePathname() .
									' in directory '. $file->getPath()
								);
							}
						}
					}
					
					// Создаём объект сборшика данных
					$archive = new Archivator(
						$name .'-'.$humanDate,
						$settings['compressor']
					);
					$archive->setSavePath($this->backupPath);
					$archive->setPath($settings['path']);
					$archive->setIgnoreFailedRead($settings['ignoreFailedRead']);

					if(isset($settings['include'])){
						foreach($settings['include'] as $include){
							if(is_string($include)){
								$archive->addInclude($include);
							}
						}
					}
					
					if(isset($settings['exclude'])){
						foreach($settings['exclude'] as $exclude){
							if(is_string($exclude)){
								$archive->addExclude($exclude);
							}
						}
					}

					// Статистика
					$stats = [
						'folders' => 0,
						'files' => 0
					];

					$cmd = $archive->compile();
					dump($cmd);
					$this->logger->info('[Command]: '.$cmd);
					$process = new Process($cmd);
					$process->run(function ($type, $buffer) use(&$stats) {
						if (Process::ERR === $type) {
							$this->logger->warning($buffer);
						} else {
							$paths = explode(PHP_EOL, $buffer);
					        foreach ($paths as $path){
					        	if(mb_strlen($path)){
					        		if(substr($path, -1) === DIRECTORY_SEPARATOR){
					        			$stats['folders']++;
							        } else {
								        $stats['files']++;
							        }
							        $this->logger->info('[Path]: '. $path);
						        }
					        }
						}
					});

					if (!$process->isSuccessful()) {
						throw new ProcessFailedException($process);
					}

					$exchange->addCurrentFile(
						array_merge(
							HelperFunctions::hash_file_multi(
								['md5', 'sha1', 'sha256'],
								$archive->getFullPathForSaveFileName()
							),
							[ 'unix_timestamp' => $nowDate->getTimestamp()],
							[ 'human_date' => $humanDate ],
							[ 'stats' =>  $stats ]
						)
					);

					// Результат работы
					$this->logger->emergency(
						sprintf(
							'Зарезервировано файлов %d шт. в %d директориях.',
							$stats['files'],
							$stats['folders']
						)
					);
				}

				$exchange->saveData();
			} else {
				throw new \Exception('Отсутвуют цели для резервного копирования', 125);
			}
		} catch (\Exception $e){
			$this->logger->error($e->getMessage());
			$io->error($e->getMessage());
			return 255;
		}

		return 0;
	}

	/**
	 * @param array $item
	 * @return array
	 */
	private function getItemSettings(array $item)
	{
		if(!isset($item['compressor'])){
			$item['compressor'] = $this->defaultCompressor;
		} else {
			$item['compressor'] = isset($item['compressor']) ?
				$this->getSupportedCompressor($item['compressor']) :
				$this->defaultCompressor;
		}

		if(!isset($item['ignoreFailedRead'])){
			$item['ignoreFailedRead'] = $this->config['ignoreFailedRead'];
		}

		return array_merge($this->defaultItem, $item);
	}

	/**
	 * @param string $compressor
	 * @param array  $context
	 * @return string
	 */
	protected function getSupportedCompressor($compressor, array $context = [])
	{
		if(in_array($compressor, Archivator::getSupported())){
			return $compressor;
		} else {
			$this->logger->warning(
				'Compressor not supported, choice default compressor ('.$this->defaultCompressor.')',
				$context ?? Archivator::getSupported()
			);
			return $this->defaultCompressor;
		}
	}

}
