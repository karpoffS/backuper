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
		$this->logger = $logger;
		$this->config = $config['backuper'];
		$this->backupPath = $config['global']['backup_path'];

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
		try{
			$io = new SymfonyStyle($input, $output);

			// Если есть что что бекапить
			if(isset($this->config['folders']) && is_array($this->config['folders'])){

				$folders = $this->config['folders'];
				foreach ($folders as $name=>$folder) {

					// Получаем текущие настройки
					if(is_array($folder)){
						$settings = $this->getItemSettings($folder);
					} else {
						$settings = $this->getItemSettings([ 'path' => $folder]);
					}
					
					$fileName = implode(DIRECTORY_SEPARATOR,
							[ rtrim($this->backupPath, DIRECTORY_SEPARATOR), $name ]
						).'-'.(new \DateTime('now'))->format($this->config['format_date']);

					// Создаём объект сборшика данных
					$archive = new Archivator(
						$fileName,
						$settings['compressor']
					);
					$archive->setPath(is_array($folder) ? $folder['path'] : $folder);
					$archive->setIgnoreFailedRead($settings['ignoreFailedRead']);

					if(isset($settings['include'])){
						foreach($settings['include'] as $item){
							if(is_string($item)){
								$archive->addInclude($item);
							}
						}
					}
					
					if(isset($settings['exclude'])){
						foreach($settings['exclude'] as $item){
							if(is_string($item)){
								$archive->addExclude($item);
							}
						}
					}

					// Статистика
					$stats = [
						'folders' => 0,
						'files' => 0
					];

					$cmd = $archive->compile();
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

					// Результат работы
					$this->logger->emergency(
						sprintf(
							'Зарезервировано файлов %d шт. в %d директориях.',
							$stats['files'],
							$stats['folders']
						)
					);
				}
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
