<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';


// Загружаем общий конфиг
define(
	'CONFIG',
	\Symfony\Component\Yaml\Yaml::parseFile(
		__DIR__ . '/config/config.yaml'
	)
);

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
