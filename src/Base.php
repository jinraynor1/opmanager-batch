<?php
namespace Jinraynor1\OpManager\Batch;

use Jinraynor1\OpManager\Api\Main as ApiMain;


class Base {

    protected $devices = array();

    public function setDevicesBy($type, $name){


        switch ($type) {
            case 'business-view':
                $params=array('bvName'=>$name);
                break;

            case 'all-devices':
                    $params=array();
                break;

            case 'single':
                $params=array('deviceName'=>$name);
                break;

            default:
                throw new \Exception("Invalid option");

                break;

        }

        $response = ApiMain::dispatcher('listDevices',$params);

        if(is_array($response) && !empty($response)){
            $this->$devices =  $response;
        }elseif(is_object($response) && isset($response->message)){

            throw new \Exception($response->message);

        }else {
            throw new \Exception("No devices were found");

        }

    }
}