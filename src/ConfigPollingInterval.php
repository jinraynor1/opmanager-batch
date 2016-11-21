<?php
namespace Jinraynor1\OpManager\Batch;

use Jinraynor1\OpManager\Api\Main as ApiMain;

/**
 * Updates Polling interval on devices
 */
class ConfigPollingInterval extends Base
{
    private $interval = 5;
    private $enabled = 'on';


    public function __construct($type, $name, $interval = null, $enabled = null)
    {
        if ($interval) {
            $this->interval = $interval;
        }

        if ($enabled) {
            $this->enabled = $enabled;
        }

        $this->setDevicesBy($type,$name);


    }


    public function run()
    {



        foreach ($this->devices as $device) {

            $params = array('name' => $device->deviceName, 'interval' => $this->interval, 'pollenabled' => $this->enabled);

            $response = ApiMain::dispatcher('ConfigureMonitoringInterval', $params);

            if (is_object($response) && isset($response->result) && isset($response->result->message)) {
                echo "{$device->deviceName} {$response->result->message} \n";
            } else {
                echo "{$device->deviceName} error al actualizar: " . json_encode($response) . "\n";
            }
        }
        return true;
    }

}


