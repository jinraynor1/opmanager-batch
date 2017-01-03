<?php
namespace Jinraynor1\OpManager\Batch;

use Jinraynor1\OpManager\Api\Main as ApiMain;

/**
 * Updates Polling interval on devices
 */
class ConfigIcmpMonitor extends Base
{

//Detalles que especifican el monitor a obtener
    private $details = array(
        'graphName' => 'stat',
        'instance' => 'Device',
        'checkNumeric' => 'true',
        'policyName' => 'OpManagerDesktopObjectStatusPoll',

    );

//parametros que deseamos sobreescribir del monitor
    private $params = array(
        'policyName' => 'OpManagerDesktopObjectStatusPoll',
        'type' => 'node',
        //Valores de umbrales criticos

        'criticalThresholdType' => 'max',
        'criticalThresholdValue' => '',
        'criticalThresholdTextualType' => 'Contains',
        'criticalThresholdTextValue' => '',

        //Valores de umbrales de rearme
        'rearmValue' => '',
        'clearThresholdType' => 'min',
        'rearmTextualType' => 'Not Contains',
        'rearmTextValue' => '',

    );

    public function __construct($type, $name, $rearmValue = null, $criticalValue = null)
    {
        if ($rearmValue) {
            $this->params['rearmValue'] = $rearmValue;
            $this->params['rearmTextValue'] = $rearmValue;

        }

        if ($criticalValue) {
            $this->params['criticalThresholdValue'] = $criticalValue;
            $this->params['criticalThresholdTextValue'] = $criticalValue;
        }

        $this->setDevicesBy($type, $name);


    }


    public function run()
    {

        foreach ($this->devices as $device) {

            $monitorDetails = ApiMain::dispatcher('getPerfomanceMonitorDetails',
                array(
                    'name' => $device->deviceName,
                    'graphName' => $this->details['graphName'],
                    'instance' => $this->details['instance'],
                    'checkNumeric' => $this->details['checkNumeric'],
                    'policyName' => $this->params['policyName']
                )
            );

            if (is_object($monitorDetails) && isset($monitorDetails->monitors)) {

                // Verifica si el monitor ya tiene un threshold configurado
                // si es asi obtiene estos parametros como default
                if (isset($monitorDetails->monitors->thresholdSet) && is_array($monitorDetails->monitors->thresholdSet)) {
                    $thresHoldDetails = reset($monitorDetails->monitors->thresholdSet);
                }


                $paramsDefault = array(
                    'name' => $device->deviceName, //required
                    'policyName' => $monitorDetails->monitors->policyName,//required
                    'graphName' => $monitorDetails->monitors->name,//required
                    'displayName' => $monitorDetails->monitors->DISPLAYNAME,//required
                    'thresholdName' => '',//required
                    'type' => $monitorDetails->monitors->type,//required
                    'timeAvg' => '',//required
                    'vendor' => '',//required
                    'sendClear' => 'true',//required
                    'oid' => $monitorDetails->monitors->oid,//required
                    'interval' => $monitorDetails->monitors->interval,//required
                    'yaxisText' => $monitorDetails->monitors->YAXISTEXT,//required
                    'checkNumeric' => $monitorDetails->monitors->checkNumeric,//required

                    //Umbrales de advertencia

                    'warningThresholdType' => null,
                    'warningThresholdValue' => null,
                    'warningThresholdTextualType' => null,
                    'warningThresholdTextValue' => null,
                    'warningMessage' => $monitorDetails->monitors->defaultMessage,

                    //Umbrales de problemas

                    'troubleThresholdType' => null,
                    'troubleThresholdValue' => null,
                    'troubleThresholdTextualType' => null,
                    'troubleThresholdTextValue' => null,
                    'troubleMessage' => $monitorDetails->monitors->defaultMessage,

                    //Umbrales de problemas criticos

                    'criticalThresholdType' => null,
                    'criticalThresholdValue' => null,
                    'criticalThresholdTextualType' => null,
                    'criticalThresholdTextValue' => null,
                    'criticalMessage' => $monitorDetails->monitors->defaultMessage,

                    //Umbrales de rearme(volvio a la normalidad)

                    'rearmValue' => null,
                    'clearThresholdType' => null,
                    'rearmTextualType' => null,
                    'rearmTextValue' => null,
                    'clrMessage' => $monitorDetails->monitors->clrMessage,

                    'failureThreshold' => $monitorDetails->monitors->FAILURETHRESHOLD,//required
                    //'oidType' => '',
                    //'firstTime' => 'false',
                    'instanceName' => $monitorDetails->monitors->instance,//required
                    //'thresholdEnabled' => 'true',
                    'isGraphNeeded' => 'false', //required
                );


                //Setear umbrales que tenia configurado el monitor(si es que se le habian asignado)
                if ($thresHoldDetails) {
                    foreach ($thresHoldDetails as $thresHoldDetailName => $thresHoldDetailValue) {
                        $paramsDefault[$thresHoldDetailName] = $thresHoldDetailValue;

                    }
                }

                $newParams = array_intersect_key($this->params + $paramsDefault, $paramsDefault);

                //Quitamos los parametros nulos
                foreach ($newParams as $newParamKey => $newParamValue) {
                    if (is_null($newParamValue)) {
                        unset($newParams[$newParamKey]);
                    }
                }


                $editPerformanceMonitor = ApiMain::dispatcher('EditPerfomanceMonitor', $newParams);

                if (is_object($editPerformanceMonitor) && isset($editPerformanceMonitor->error)) {

                    echo "$device->deviceName| error al actualizar monitor, message: {$editPerformanceMonitor->error->message}  code:{$editPerformanceMonitor->error->code}\n";


                } else {
                    echo "$device->deviceName| monitor satisfactoriamente actualizado\n";
                }


            }

        }

        return true;
    }

}


