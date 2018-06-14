<?php

/**
 * Actualiza los detalles de monitor de un dispositivo
 * Ciertos paremtros del monitor no se pueden actualizar en la plantilla
 * por lo tanto se debe actualizar sobre el dispositivo
 * 
 */

set_time_limit(0);
ini_set("memory_limit", "128M");
ob_implicit_flush(1);

require_once dirname(dirname(__FILE__)).'/bootstrap.php';


if (PHP_SAPI == 'cli') {
    $deviceName = isset($argv[1]) ? $argv[1] : null;
}else{
  $deviceName = isset($_REQUEST['item']['changeDeviceTemplate']['deviceName']) ? $_REQUEST['item']['changeDeviceTemplate']['deviceName'] : null;
  
}

$associatedMonitors = ApiMain::dispatcher('getAssociatedMonitors', array('name' => $deviceName));



$performanceMonitors = $associatedMonitors->performanceMonitors;



foreach ($performanceMonitors->monitors as $monitor) {

    if (preg_match("/(snr|modemonlineup)\s$deviceName/i", $monitor->name, $resultMatches)) {



        $monitorDetails = ApiMain::dispatcher('getPerfomanceMonitorDetails', array('name' => $deviceName,
                    'policyName' => $monitor->policyName, 'checkNumeric' => 'true', 'graphName' => $monitor->name));


        if (empty($monitorDetails->monitors->thresholdSet)) {
            continue;
        }

        $thresHoldDetails = reset($monitorDetails->monitors->thresholdSet);



   if($resultMatches[1]=='snr'){
     $yaxisText='dB';  
   }else{
       $yaxisText='users';  
   }

        $params = array(
            'name' => $deviceName, //required
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
            'yaxisText' => $yaxisText,//required
            'checkNumeric' => $monitorDetails->monitors->checkNumeric,//required
            
        
          //  'warningThresholdType' => 'min',
          //  'warningThresholdValue' => '',
          //  'warningThresholdTextualType' => 'Contains',
          //  'warningMessage' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
          //  'warningThresholdTextValue' => '',
            'troubleThresholdType' => $thresHoldDetails->troubleThresholdType,//required
            'troubleThresholdValue' => $thresHoldDetails->troubleThresholdValue,//required
         //   'troubleThresholdTextualType' => 'Contains',
            'troubleThresholdTextValue' => $thresHoldDetails->troubleThresholdValue,//required
            'troubleMessage' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',//$thresHoldDetails->troubleMessage,//required
         //   'criticalThresholdType' => 'min',
         //   'criticalThresholdValue' => '',
         //   'criticalThresholdTextualType' => 'Contains',
         //   'criticalThresholdTextValue' => '',
         //   'criticalMessage' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',

           'rearmValue' => $thresHoldDetails->rearmValue,//required
            'clearThresholdType' => 'max',//required
         //   'rearmTextualType' => 'Not Contains',
         //   'rearmTextValue' => '72',
            'clrMessage' => '$MONITOR is now back to normal, current value is $CURRENTVALUE%',//$monitorDetails->monitors->defaultClrMessage,//required
            'failureThreshold' => '1',//required
            //'oidType' => '',
            //'firstTime' => 'false',
            //'instanceName' => '',
            //'thresholdEnabled' => 'true',
            'isGraphNeeded' => 'true',//required
        );


        $editPerformanceMonitor = ApiMain::dispatcher('EditPerfomanceMonitor', $params);
        
      if (is_object($editPerformanceMonitor) && isset($editPerformanceMonitor->error)) {

        echo "$deviceName| error al actualizar ({$monitorDetails->monitors->policyName}) - message:$editPerformanceMonitor->error->message  code:{$editPerformanceMonitor->error->code}\n";
    
    
    }else{
        echo "$deviceName| satisfactoriamente actualizado{$monitorDetails->monitors->policyName}\n";
    }


        

        
    }
}
