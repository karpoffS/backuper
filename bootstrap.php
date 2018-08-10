<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

define('BASE_DIR', __DIR__);

// Подключаем загрузку переменных
(new \Symfony\Component\Dotenv\Dotenv())->load(__DIR__.'/.env');

// Генерируем пути
$configPath = \Utils\HelperFunctions::generatePath(getenv('CONFIG_PATH'));
define('BACKUP_PATH',  \Utils\HelperFunctions::generatePath(getenv('BACKUP_PATH')));

// Если файла нету
if(!file_exists($configPath)){
	echo PHP_EOL;
	echo('ERROR: Config file not found! - '.$configPath.PHP_EOL);
	echo PHP_EOL;
	exit(0);
}

// Загружаем общий конфиг
define('CONFIG',  \Symfony\Component\Yaml\Yaml::parseFile($configPath));
unset($configPath);

$logger = new Utils\Logging([ 'path' => \Utils\HelperFunctions::generatePath(getenv('LOG_PATH')), 'format' => CONFIG['logger']['format']]);

define('CACHE_PATH', \Utils\HelperFunctions::generatePath(getenv('CACHE_PATH')));
