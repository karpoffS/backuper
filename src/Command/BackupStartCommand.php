<?php

namespace Command;

use Interfaces\CompressorInterface;
use Library\Compressor;
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
				if(!in_array($this->config['default_compressor'], Compressor::getSupported())){
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

					// Если есть команды до выполнения основного задания
					if(isset($settings['commands'])){
						if(isset($settings['commands']['before'])){
							foreach ($settings['commands']['before'] as $command){
								$this->runExternalCommand($command, ['Command','Before']);
							}
						}
					}

					// Если установлен флаг очистки старых резервных копий
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
					$archive = new Compressor(
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

					// Если есть команда
					if(strlen($cmd = $archive->compile())){

						// Запись в лог
						$this->logger->info('[Command]: '.$cmd);

						// Создаём процесс
						$process = new Process($cmd);

						// Отключаем лимит выполнения команды
						$process->setTimeout(null);
						// Запускаем процесс
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

						// Если что-то пошло не так то сообщаем
						if (!$process->isSuccessful()) {
							throw new ProcessFailedException($process);
						}

						// Добовляем данные о новом файле
						$exchange->addCurrentFile(
							array_merge(
								[ 'mask' => $name ], // маска имени файла
								HelperFunctions::hash_file_multi(
									['md5', 'sha1', 'sha256'],
									$archive->getFullPathForSaveFileName()
								),
								[ 'ext' => $archive->getExtFile()],  // расширение файла
								[ 'unix_timestamp' => $nowDate->getTimestamp()], // Время в unixtimestamp
								[ 'human_date' => $humanDate ], // Время в формате \DateTime::ATOM
								[ 'stats' =>  $stats ]  // Статистика
							)
						);

						// Результат работы в лог
						$this->logger->emergency(
							sprintf(
								'Reserve copy files %d pcs. in %d directories.',
								$stats['files'],
								$stats['folders']
							)
						);
					}

					// Если есть команды после резервного копирвования
					if(isset($settings['commands'])){
						if(isset($settings['commands']['after'])){
							foreach ($settings['commands']['after'] as $command){
								$this->runExternalCommand($command, ['Command','After']);
							}
						}
					}
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
	 * Выполнение внешних команд
	 * 
	 * @param string $command
	 * @param array  $context
	 */
	private function runExternalCommand(string $command, array $context)
	{
		$context = implode('', array_map(function ($s){return '['.$s.']';}, $context));
		// Запись в лог
		$this->logger->info($context.': '.$command);
		$process = new Process($command);
		$process->setTimeout(null);
		$process->run(function ($type, $buffer) use($context) {
			if (Process::ERR === $type) {
				$this->logger->warning($context.': '. $buffer);
			} else {
				$strings = explode(PHP_EOL, $buffer);
				foreach ($strings as $string){
					if(mb_strlen($string)){
						$this->logger->info($context.': '. $string);
					}
				}
			}
		});

		if (!$process->isSuccessful()) {
			throw new ProcessFailedException($process);
		}
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
		if(in_array($compressor, Compressor::getSupported())){
			return $compressor;
		} else {
			$this->logger->warning(
				'Compressor not supported, choice default compressor ('.$this->defaultCompressor.')',
				$context ?? Compressor::getSupported()
			);
			return $this->defaultCompressor;
		}
	}

}
