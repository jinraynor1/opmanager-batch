<?php
require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/vendor/autoload.php';

use Jinraynor1\OpManager\Api\Main as ApiMain;

ApiMain::initialize(array(
    'apiUrl' => 'http://10.192.17.120:8000',
    'apiKey' => 'df6cf9013a17771e38493a01551d873a'
));
