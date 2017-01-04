<?php

require_once __DIR__ . '/../bootstrap.php';

use Jinraynor1\OpManager\Batch\Monitors;
use Jinraynor1\Threading\Pcntl\ThreadQueue;

$cmd = new Commando\Command();

$cmd->useDefaultHelp();

$cmd->setHelp('Supply custom monitors of devices, e.g

        Supply all custom monitors from al devices:
                    --mode all-devices

        Supply custommonitors by business view:
                    --mode business-view --name my_business_view_1

        Supply interfaces from single device by name:
                    --mode single --name cisco_server_1 ');


$cmd->option('mode')
    ->require()
    ->must(function ($mode) {
        return in_array($mode, array('single', 'all-devices', 'business-view'));
    })
    ->describedAs("Mode for list devices to be updated")

    ->option('name')
    ->needs(array('mode'))
    ->default('')
    ->describedAs("Name of business view or device depending on mode selected")

    ->option('threads')
    ->default(1)
    ->must(function ($threads) use ($cmd){
        return ctype_digit($threads);
    })
    ->describedAs("Number of threads for this job");

;

// adjust some resources
ini_set("memory_limit", "512M");

// include config for monitors
$config = include_once(__DIR__ . '/../config/monitors.php');



// get the devices
try{
$base = new \Jinraynor1\OpManager\Batch\Base();
$base ->setDevicesBy($cmd['mode'],$cmd['name']);
$devices= $base->getDevices();
} catch (Exception $e) {
    echo("Error: " . $e->getMessage()."\n");
    exit(0);

}


/**
 * Call supply monitors for a device
 * @param $device
 * @param $config
 * @return bool
 */
function addMonitor($args){
    $device=$args['device'];
    $config=$args['config'];

    try {


        $monitorProvider = new Monitors\Provider($device,$config['monitorProvider']);


        $monitorProvider

            ->setMonitorFiller(new Monitors\Filler($config['monitorFiller']))
            ->setMonitorTemplate(new Monitors\Template($config['monitorTemplate']))
            ->setMonitorTracker(new Monitors\Tracker($config['monitorTracker']));


        $monitorProvider
            ->run();

    } catch (Exception $e) {
        echo("Error was found: " . $e->getMessage()."\n");
        return false;

    }
}

// process single device
if(count($devices) == 1){
    addMonitor(array(
            'device'=>$devices[0]->deviceName,
            'config'=>$config)
    );
    exit(0);
}

// process multiple devices
$TQ = new ThreadQueue("addMonitor");
$TQ->queueSize = $cmd['threads'];



foreach($devices as $device){
    $TQ->add(array(
        'device'=>$device->deviceName,
        'config'=>$config
    ));
}

while(  count( $TQ->threads() )  ){     // there are existing processes in the background?
    $TQ->tick();  // mandatory!
}


