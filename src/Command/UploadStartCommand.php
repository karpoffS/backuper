<?php

namespace Command;

use League\Flysystem\Adapter\Local;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Utils\ExchangeData;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\Sftp\SftpAdapter;
use Symfony\Component\Finder\Comparator as Comparators;
/**
 * Class BackupStartCommand
 * @package Command
 */
class UploadStartCommand extends Command
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
	 * @var array 
	 */
	private $backupItems = [];

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var array
	 */
	protected $defaultItem = [
		'type' => null,
		'host' => null,
		'username' => null,
		'password' => null,
		'root' => null
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
		if(isset($config['backuper']['folders'])){
			$this->backupItems = array_keys($config['backuper']['folders']);
		}
		$this->config = $config['uploader'];
		$this->backupPath = BACKUP_PATH;

		parent::__construct($name);
	}

	/**
	 * Настройка команды
	 */
	protected function configure()
	{
		$this
			->setName('upload:start')
			->setDescription('Выполняет загрузку файлов резервного на внешние источники')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{

		dump($this->config);
		$io = new SymfonyStyle($input, $output);
		try {
			if($connections = $this->config['connections']){
				// Загружаем предыдущие данные
				$exchange = new ExchangeData(CACHE_PATH);

				$uploadFiles = $exchange->getUploadFiles();
				dump($uploadFiles);

				foreach ($connections as $name=>$conn){
					$setting = $this->getConnectionSetting($conn);

					// Если FTP подключение
					if($setting['type'] === 'ftp'){
						/** @var FilesystemInterface $filesystem */
						$filesystem = new Filesystem(new FtpAdapter($setting));
					} elseif ($setting['type'] === 'sftp') {
						/** @var FilesystemInterface $filesystem */
						$filesystem = new Filesystem(new SftpAdapter($setting));
					} else {
						$filesystem = null;
					}

					// Если указыны верные настроки storages
					if($filesystem instanceof FilesystemInterface){

						// Если путь в storages существует
						if($filesystem->has($setting['path'])) {

							if(isset($setting['cleanBackups']) && is_string($setting['cleanBackups'])){
								$this->logger->notice('[Clean]: Start cleaning up old backup files...');

								// Если есть какой-либо контент
								if($contents = $filesystem->listContents($setting['path'])){

									// Чистим файлы
									foreach ($contents as $content){
										if($content['type'] === 'file'){
											if(preg_match('/('.implode('|',$this->backupItems).')/i', $content['basename'])){                      $comparator = new Comparators\DateComparator($setting['cleanBackups']);
												dump(date(\DateTime::ATOM, $filesystem->getTimestamp($content['path']))) ;
												dump(date(\DateTime::ATOM, (int) $comparator->getTarget())) ;
//												dump((int) $comparator->getTarget()) ;
												dump($filesystem->getTimestamp($content['path']) < (int) $comparator->getTarget());
												if($filesystem->getTimestamp($content['path']) < (int) $comparator->getTarget()){
													try{
														if($filesystem->delete($content['path'])){
															$this->logger->info('Delete old file backup in path: '.$content['path']);
														} else {
															$this->logger->warning('Filed delete old file: '.$content['path']);

														}
													} catch (FileNotFoundException $e){}
												}
											}

										}
									}
								}
							}
						} else {
							$paths = array_filter(explode(DIRECTORY_SEPARATOR, $setting['path']));
							if(!$filesystem->has($setting['path'])){

								if($this->CreateDirectory($filesystem, $paths, current($paths))){
									$this->logger->info('[Clean]: Create folder in path: '.$setting['path']);
								} else {
									$this->logger->warning('Failed create folder in path: '.$setting['path']);
								}
							}

						}

						// Есди есть что копировать
						if(count($uploadFiles)){
							foreach ($uploadFiles as $upload){

								$locaStorageFilePath = $this->getBackupFilePath($upload['file']);
								$toStorageFilePath = implode(DIRECTORY_SEPARATOR, [rtrim($setting['path'], DIRECTORY_SEPARATOR), $upload['file']]);

								// Если файла нет
								if(!$filesystem->has($toStorageFilePath)){
									$stream = fopen($locaStorageFilePath, 'r+');
									if($filesystem->writeStream($toStorageFilePath, $stream)){
										$this->logger->info('Upload file: '.$locaStorageFilePath.' to '.$toStorageFilePath);
									}else {
										$this->logger->warning('Filed upload file: '.$locaStorageFilePath.' to '.$toStorageFilePath);
									}
									if (is_resource($stream)) {
										fclose($stream);
									}
								}


								$filesystem->put(implode(DIRECTORY_SEPARATOR, [rtrim($setting['path'], DIRECTORY_SEPARATOR), rtrim($upload['file'], $upload['ext'])]).'.manifest', json_encode($upload, JSON_PRETTY_PRINT));
							}

							$exchange->clearUploadedFilesAndSave();
						}


					} // if filesystem object
				} // connections while
			}



		} catch (\Exception $e){
			$this->logger->error($e->getMessage());
			$io->error($e->getMessage());
			return 255;
		}

		return 0;
	}

	/**
	 * @param FilesystemInterface $filesystem
	 * @param array               $paths
	 * @param string              $next
	 * @return bool
	 */
	private function CreateDirectory(FilesystemInterface $filesystem, array $paths, $next = '')
	{
		try{
			$check = [];
			foreach ($paths as $key=>$path){
				if($next === $path){
					if(isset($paths[$key+1])){
						$next = $paths[$key+1];
					}
				}
				$check[] = $path;
			}

			if(!$filesystem->has(implode(DIRECTORY_SEPARATOR, $check))){
				return $filesystem->createDir(implode(DIRECTORY_SEPARATOR, $check));
			} else {
				return $this->CreateDirectory($filesystem, $paths, $next);
			}
		} catch (\Exception $e){

		}
	}

	/**
	 * @param array $conn
	 * @return array
	 */
	private function getConnectionSetting(array $conn)
	{
		if(isset($conn['type'])){
			if($conn['type'] === 'ftp'){
				$this->defaultItem['ssl'] = false;
				$this->defaultItem['passive'] = false;
				$this->defaultItem['port'] = 21;
				$this->defaultItem['timeout'] = 30;
			}
			if($conn['type'] === 'sftp'){
				$this->defaultItem['port'] = 22;
				$this->defaultItem['timeout'] = 10;
			}
		}

		$temConn = array_merge(
			array_diff($this->defaultItem, $conn),
			array_diff($conn, $this->defaultItem)
		);

		return array_merge($this->defaultItem, $temConn);

	}

	private function getBackupFilePath(string $file)
	{
		return implode(DIRECTORY_SEPARATOR, [rtrim($this->backupPath, DIRECTORY_SEPARATOR), $file]);
	}

}
