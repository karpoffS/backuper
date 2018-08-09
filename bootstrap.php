<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

// Подключаем загрузку переменных
$dotenv = new \Symfony\Component\Dotenv\Dotenv();
$dotenv->load(__DIR__.'/.env');

// Генерируем путь к конфигурационному файлу
if(substr(getenv('CONFIG_PATH'), 0,1) === DIRECTORY_SEPARATOR){
	$configPath = getenv('CONFIG_PATH');
} else {
	if(substr(getenv('CONFIG_PATH'), 0,1) === '.'){
		$configPath  = ltrim(getenv('CONFIG_PATH'), '.');

		if(substr($configPath, 0,1) === DIRECTORY_SEPARATOR){
			$configPath = __DIR__ . $configPath;
		} else {
			$configPath = __DIR__ . $configPath;
		}
	} else {
		$configPath = getenv('CONFIG_PATH');
	}
}

// Если файла нету
if(!file_exists($configPath)){
	echo PHP_EOL;
	echo('ERROR: Config file not found! - '.$configPath.PHP_EOL);
	echo PHP_EOL;
	exit(0);
}

// Загружаем общий конфиг
define(
	'CONFIG',
	\Symfony\Component\Yaml\Yaml::parseFile($configPath)
);
unset($configPath);

if(substr(CONFIG['logger']['log_path'], 0,1) === DIRECTORY_SEPARATOR){
	define(
		'logPath',
		CONFIG['logger']['log_path']
	);
} else {
	define(
		'logPath',
		implode(
			DIRECTORY_SEPARATOR,
			[__DIR__ , CONFIG['logger']['log_path']]
		)
	);
}
