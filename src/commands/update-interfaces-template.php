<?php
require_once __DIR__ . '/../../bootstrap.php';

use Jinraynor1\OpManager\Batch\UpdateInterfacesTemplate;

$cmd = new Commando\Command();

$cmd->useDefaultHelp();

$cmd->setHelp('Updates the poll interval from interfaces template and apply the changes, e.g

        Set the polling to this interval:
                    --poll-interval 300

        ');

$cmd->option('poll-interval')
    ->default(300)
    ->describedAs("poll interval value")
;



$obj = new UpdateInterfacesTemplate($cmd['poll-interval']);
$obj->run();