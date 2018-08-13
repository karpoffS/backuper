<?php

namespace Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * Class BackupStartCommand
 * @package Command
 */
class StartCommand extends Command
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
		$this->config = $config;
		$this->backupPath = BACKUP_PATH;

		parent::__construct($name);
	}

	/**
	 * Настройка команды
	 */
	protected function configure()
	{
		$this
			->setName('app:start')
			->setDescription('Запускает резавное копирование и загрузку файлов на внешние источники
')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		try{
		   $this->getApplication()->find('backup:start')->run($input, $output);
		   $this->getApplication()->find('upload:start')->run($input, $output);

		} catch (\Exception $e){
			$this->logger->error($e->getMessage());
			$io->error($e->getMessage());
			return 255;
		}

		return 0;
	}
}
