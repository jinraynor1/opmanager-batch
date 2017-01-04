<?php
require_once __DIR__ . '/../bootstrap.php';


$cmd = new Commando\Command();

$cmd->useDefaultHelp();

$cmd->setHelp('Discover interfaces of devices, e.g

        Discover all interfaces from devices:
                    --mode all-devices

        Discover interfaces by business view:
                    --mode business-view --name my_business_view_1

        Discover interfaces from single device by name:
                    --mode single --name cisco_server_1 ');

$cmd->option('m')
    ->aka('mode')
    ->require()
    ->must(function ($type) {
        return in_array($type, array('single', 'all-devices', 'business-view'));
    })
    ->describedAs("Mode for list devices to be updated")
    ->option('n')
    ->aka('name')
    ->needs(array('mode'))
    ->default('')
    ->describedAs("Name of business view or device depending on mode selected");


try {
    $obj = new Jinraynor1\OpManager\Batch\DiscoverInterfaces($cmd['mode'], $cmd['name']);
    $obj->run();
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
