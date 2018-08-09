<?php
namespace  Utils;

/**
 * Class Logging
 * @package Utils
 */
class Logging
{
	/**
	 * @var string
	 */
	private static $config;

	/**
	 * Logging constructor.
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		self::$config = $config;
	}


	/**
	 * Получаем настроенный логгер
	 * @param string $cmd_name
	 * @return \Monolog\Logger|null
	 */
	public function getMonologLoggerByCommandName(string $cmd_name)
	{
		try {
			$streamHandler = new \Monolog\Handler\StreamHandler(
				self::$config['path'] . $cmd_name . '.log',
				\Monolog\Logger::DEBUG
			);

			$streamHandler->setFormatter(
				new \Monolog\Formatter\LineFormatter(
					self::$config['format']
				)
			);

			$logger = new \Monolog\Logger('Logger');
			$logger->pushHandler($streamHandler);
			return $logger;

		} catch (\Exception $e) {
			return null;
		}
	}
}