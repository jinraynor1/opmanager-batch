<?php
require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/vendor/autoload.php';


use Jinraynor1\OpManager\Api\Main as ApiMain;

$opmanager_config = include_once __DIR__ . '/config/opmanager.php';

ApiMain::initialize(array(
    'apiUrl' => $opmanager_config['apiUrl'],
    'apiKey' => $opmanager_config['apiKey'],
));


