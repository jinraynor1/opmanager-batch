<?php
require_once __DIR__ . '/../../bootstrap.php';

use Jinraynor1\OpManager\Batch\ConfigPollingInterval;

$cmd = new Commando\Command();

$cmd->useDefaultHelp();

$cmd->setHelp('Updates the interval polling of devices, e.g

        Update all devices:
                    --mode all-devices --interval 5

        Update devices by business view:
                    --mode business-view --name my_business_view_1 --interval 1

        Update single device by name:
                    --mode single --name cisco_server_1 --interval 15');

$cmd->option('m')
    ->aka('mode')
    ->require()
    ->must(function($type) {
        return in_array($type, array('single','all-devices','business-view'));
    })
    ->describedAs("Mode for list devices to be updated")

    ->option('n')
    ->aka('name')
    ->needs(array('mode'))
    ->default('')
    ->describedAs("Name of business view or device depending on mode selected")

    ->option('i')
    ->aka('interval')
    ->default(5)
    ->describedAs("Interval minutes for polling default is 5");



$obj = new ConfigPollingInterval($cmd['mode'],$cmd['name'],$cmd['interval']);
$obj->run();