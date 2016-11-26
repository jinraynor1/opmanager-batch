<?php

require_once __DIR__ . '/../../bootstrap.php';

$cmd = new Commando\Command();

$cmd->useDefaultHelp();

$cmd->setHelp('Discover custom monitors of devices, e.g

        Discover all custom monitors from al devices:
                    --mode all-devices

        Discover custommonitors by business view:
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



// adjust some resources
ini_set("memory_limit", "512M");






//El dispositivo debe ser pasado como argumento a este script
$deviceName = isset($argv[1]) ? $argv[1] : false;

$config = include_once(__DIR__ . '/config/monitores.php');


$monitorProvider = new MonitorProvider();

$monitorProvider
    ->setMonitorFiller(new MonitorFiller($config['monitorFiller']))
    ->setMonitorTemplate(new MonitorTemplate($config['monitorTemplate']))
    ->setMonitorTracker(new MonitorTracker($config['monitorTracker']));


try {

    $monitorProvider
        ->initialize($config['monitorProvider'], $deviceName)
        ->run();

} catch (Exception $e) {
    echo("Excepcion en el proceso: " . $e->getMessage()."\n");
    exit(250);

}
