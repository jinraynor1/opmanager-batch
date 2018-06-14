<?php
namespace Jinraynor1\OpManager\Batch;

use Jinraynor1\OpManager\Api\Main as ApiMain;




/**
 * Updates Polling interval on devices
 */
class UpdateInterfacesTemplate extends Base
{

    private $poll_interval = 300;
    private $name = null;
    public function __construct($poll_interval, $name)
    {
        if ($poll_interval) {
            $this->poll_interval = $poll_interval;
        }

        if ($name) {
            $this->name = $name;
        }




    }
    public function run(){

        $interfaceTemplates= ApiMain::dispatcher('listInterfaceTemplates',array(
            'showMode'=>'allInterfaces'
        ));


        if(!$interfaceTemplates || isset($interfaceTemplates->error->message) ){
            $error=isset($interfaceTemplates->error->message)?$interfaceTemplates->error->message:"Empty";
            echo sprintf("Error fetching device templates: %s\n",$error);
            return false;
        }
        $intfsUpdated=array();

        #$regexItf= '/docs[\s]+cable/i';
        $regexItf = false;

        /**
         * Parametros que deseamos sobreescribir de los parametros por defecto
         * ver variable --> $paramsIntfDef mas abajo
         */
        $paramsIntCustom=array(
            'pollInterval' => $this->poll_interval
        );



        foreach($interfaceTemplates as $interfaceTemplate){

            if($this->name && $this->name != $interfaceTemplate->ifname){
                continue;
            }

            $interface =  ApiMain::dispatcher('viewInterfaceTemplates',array('typeName'=>$interfaceTemplate->ifname,'typeId'=>$interfaceTemplate->typeid));




            $paramsIntfDef=array(
                'typeName' => $interfaceTemplate->ifname,
                'moid' => '',
                'intfEnabled' =>  $interface->AdminConfiguration->ConfigData->intfEnabled,
                'statusPoll' =>  $interface->AdminConfiguration->ConfigData->statusPoll,
                'applyToAll' => 'true',
                'enableIntfUtilTemplate' =>  $interface->AdminConfiguration->ConfigData->enableIntfUtilTemplate,
                'enableIntfErrorTemplate' =>  $interface->AdminConfiguration->ConfigData->enableIntfErrorTemplate,
                'enableIntfDiscTemplate' =>  $interface->AdminConfiguration->ConfigData->enableIntfDiscTemplate,
                'pollInterval' => $interface->AdminConfiguration->ConfigData->pollInterval,
                'failureThreshold' =>  $interface->AdminConfiguration->ConfigData->failureThreshold,
                'statusPollFT' =>  $interface->AdminConfiguration->ConfigData->statusPollFT,
                'utilThreshold' =>  $interface->AdminConfiguration->ConfigData->utilThreshold,
                'utilRearm' =>  $interface->AdminConfiguration->ConfigData->utilRearm,
                'utilCondition' =>  $interface->AdminConfiguration->ConfigData->utilCondition,
                'errorThreshold' => $interface->AdminConfiguration->ConfigData->errorThreshold,
                'errorRearm' => $interface->AdminConfiguration->ConfigData->errorRearm,
                'errorCondition' => $interface->AdminConfiguration->ConfigData->errorCondition,
                'discThreshold' => $interface->AdminConfiguration->ConfigData->discThreshold,
                'discRearm' => $interface->AdminConfiguration->ConfigData->discRearm,
                'discCondition' => $interface->AdminConfiguration->ConfigData->discCondition,

            );

            $settingsIntf = array_intersect_key($paramsIntCustom + $paramsIntfDef, $paramsIntfDef);

            #var_dump($settingsIntf);die;

            $responseIntTemplateDevices =  ApiMain::dispatcher('associateIntfTemplateToDevices',$settingsIntf);


            $paramsIntfDev=array(
                'typeName' => $interfaceTemplate->ifname,
                'moid' => '',
                'intfEnabled' => 'on',
                'statusPoll' => 'on',
                'applyToAll' => 'true',
                'checkAllSetting' => 'true',
                'checkMonitoring' => 'true',
                'checkApplyUtil' => 'true',
                'checkApplyError' => 'true',
                'checkApplyDisc' => 'true',
                'checkFailureThreshold' => 'true',
                'checkSP' => 'true',
            );

            $responseIntDev=  ApiMain::dispatcher('applyIntfTemplateToDevices',$paramsIntfDev);



            $intfsUpdated[]=$interfaceTemplate->ifname;



        }
        if(!empty($intfsUpdated)) {
            echo sprintf("Template devices updated, interval:%s ,interfaces: %s\n",
                    $this->poll_interval,
                    implode(',',$intfsUpdated)
            );

        }
    }
}