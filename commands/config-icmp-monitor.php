<?php
require_once __DIR__ . '/../bootstrap.php';

use Jinraynor1\OpManager\Batch\ConfigIcmpMonitor;

$cmd = new Commando\Command();

$cmd->useDefaultHelp();

$cmd->setHelp('Updates the threshold of icmp monitor from devices, e.g

        Update all devices:
                    --mode all-devices --rearm 5 --critical 20

        Update devices by business view:
                    --mode business-view --name my_business_view_1 --rearm 5 --critical 20

        Update single device by name:
                    --mode single --name cisco_server_1 --rearm 5 --critical 20');

$cmd->option('mode')
    ->require()
    ->must(function($type) {
        return in_array($type, array('single','all-devices','business-view'));
    })
    ->describedAs("Mode for list devices to be updated")

    ->option('name')
    ->needs(array('mode'))
    ->default('')
    ->describedAs("Name of business view or device depending on mode selected")

    ->option('rearm')
    ->default('')
    ->describedAs("rearm value")

    ->option('critical')
    ->default('')
    ->describedAs("rearm value")
;



$obj = new ConfigIcmpMonitor($cmd['mode'],$cmd['rearm'],$cmd['critical']);
$obj->run();