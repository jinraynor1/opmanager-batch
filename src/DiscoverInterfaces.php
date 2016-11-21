<?php
namespace Jinraynor1\OpManager\Batch;

use Jinraynor1\OpManager\Api\Main as ApiMain;

/**
 * Discover interfaces on devices
 */
class DiscoverInterfaces extends Base
{

    private $adminStates;
    private $intftypes;
    private $operStates;

    public function __construct($type, $name, $adminStates = '$SelectAll$', $intftypes = '$SelectAll$', $operStates = '$SelectAll$')
    {

        $this->adminStates = $adminStates;
        $this->intftypes = $intftypes;
        $this->operStates = $operStates;

        $this->setDevicesBy($type,$name);

    }

    public function run()
    {


        foreach ($this->devices as $i => $device) {


            $paramsDI = array(
                'adminStates' => $this->adminStates,
                'devicesList' => $device->deviceName,
                'intftypes' => $this->intftypes,
                'operStates' => $this->operStates
            );


            $responseDiscoverInterface = ApiMain::dispatcher('discoverInterface', $paramsDI);

            if (isset($responseDiscoverInterface->error->code) && isset($responseDiscoverInterface->error->message)) {

                echo "$device->deviceName | error al descubrir interfaces \n";
                echo "$device->deviceName | error code: " . $responseDiscoverInterface->error->code . ', error message: ' . $responseDiscoverInterface->error->message . "\n";
            } else {

                echo "$device->deviceName | Se descrubrio interfaces satisfactoriamente\n";

                if (isset($responseDiscoverInterface->result->message) && isset($responseDiscoverInterface->result->message)) {
                    echo "$device->deviceName | " . $responseDiscoverInterface->result->message . "\n";
                }
            }


        }
    }

}