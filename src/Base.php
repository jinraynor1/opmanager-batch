<?php
namespace Jinraynor1\OpManager\Batch;

use Jinraynor1\OpManager\Api\Main as ApiMain;

class Base {

    protected $devices = array();

    public function getDevices(){
        return $this->devices;
    }

    /**
     * Fill device list as requested
     * @param $type
     * @param $name
     * @throws \Exception
     */
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
            $this->devices =  $response;
        }elseif(is_object($response) && isset($response->message)){

            throw new \Exception($response->message);

        }else {
            throw new \Exception("No devices were found: ".json_encode($response));

        }

    }





    /**
     * Handles response for common requests
     * @param $response
     * @param $deviceDisplayName
     * @param $action
     * @return array
     */
    public function handleCommonResponse($response, $deviceDisplayName, $action)
    {
        // if error present
        if($message = $this->checkCommonErrorResponse($response, $deviceDisplayName, $action)){
           $result = false;
        } else {
            // no error founded
            $result = true;

            if (isset($response->result) && isset($response->result->message)) {
                $message = "$deviceDisplayName | " . $response->result->message . "\n";
            }else{
                $message = "$deviceDisplayName | operation $action success\n";
            }


        }
        return array(
            'result' => $result,
            'message' => $message,
            'error_code'=>isset($response->error->code)?$response->error->code:null,
            'error_message'=>isset($response->error->message)?$response->error->message:null,
        );
    }





    /**
     * Parse response from common errors
     * @param $response
     * @param $deviceDisplayName
     * @param $action
     * @return boolean
     */
    public function checkCommonErrorResponse($response, $deviceDisplayName, $action)
    {
        $error=false;
        if (!$response || is_null($response)) {

            $error= "$deviceDisplayName | no response when trying to $action\n";

        }elseif (is_object($response) && isset($response->error)) {

            $error = "$deviceDisplayName | error at $action \n";

            if (isset($response->error->code) && isset($response->error->message)) {
                $response->error->code;
                $response->error->message;
                $error = "$deviceDisplayName | error at $action ,error code: " . $response->error->code . ', error message: ' . $response->error->message . "\n";
            }
        }

        return $error;
    }

    /**
     * Custom ip validation
     * @param null $ip
     * @return bool
     */
    public function validate_ip($ip = null)
    {

        if (!$ip or strlen(trim($ip)) == 0) {
            return false;
        }

        $ip = trim($ip);
        if (preg_match("/^[0-9]{1,3}(.[0-9]{1,3}){3}$/", $ip)) {
            foreach (explode(".", $ip) as $block)
                if ($block < 0 || $block > 255)
                    return false;
            return true;
        }
        return false;
    }
}