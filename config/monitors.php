<?php

return array(

    'monitorTracker' => array(

        'database' => __DIR__ . '/monitors/database/localStorage.db',
        'busyTimeout' => '5000',
        'snmp_hour' => date('Y-m-d H'),
        'snmp_historic_from' => date('Y-m-d H', strtotime("-3 day")),

    ),
    'monitorTemplate' => array(

        'chunk_monitor_size' => 250, //cantidad de monitores a enviar al guardar plantilla (no se puede enviar demasiados)
        'sleep_for_chunked_monitors' => 120,

    ),
    'monitorFiller' => array(

        'rangeModemOnLineUp' => range(536870914, 536870949),

        'graphs' => array(

            'baseMonitors' => array(
                'interval' => 900,
                'graphDescription' => 'Dynamic monitor',
                'functionalExp' => '',
                'units' => 'units',
                'protocol' => 'SNMP',
                'isEnabled' => 'Not Enabled',
                'warningType' => 'min', 'warningVal' => '', 'warningMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'troubleType' => 'min', 'troubleVal' => '', 'troubleMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'criticalType' => 'min', 'criticalVal' => '', 'criticalMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'rearmType' => 'max', 'rearmVal' => '', 'rearmMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'timeout' => '1'
            ),
            'childMonitors' => array(
                'interval' => 300,
                'graphDescription' => 'Dynamic monitor',
                'functionalExp' => '',
                'protocol' => 'SNMP',
                'isEnabled' => 'Not Enabled',
                'warningType' => 'min', 'warningVal' => '', 'warningMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'troubleType' => 'min', 'troubleVal' => '', 'troubleMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'criticalType' => 'min', 'criticalVal' => '', 'criticalMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'rearmType' => 'max', 'rearmVal' => '', 'rearmMsg' => '$MONITOR is $CURRENTVALUE $UNITS, threshold value for this monitor is $THRESHOLDVALUE $UNITS',
                'timeout' => '1'

            )


        ),
        'thresholds' => array(
            'snr' => array(
                'troubleVal' => 250,
                'rearmVal' => 260,
                'units' => 'dB'
            ),

            'modemonlineup' => array(
                'validate' => function ($snmp_integer) {
                    return $snmp_integer > 20;
                    //return true
                },
                'troubleVal' => function ($snmp_integer) {
                    return '';
                    //return floor($snmp_integer * 0.85);
                },
                'rearmVal' => function ($snmp_integer) {
                    return '';
                    //return floor($snmp_integer * 0.90);
                },
                'units' => 'users'
            )
        ),
    ),

    'monitorProvider' => array(
        //nombre del vendor
        'arris' =>
            array(
                //tipo de oids a agregar
                'monitors' => array(
                    'snr' => '1.3.6.1.2.1.10.127.1.1.4.1.5',
                    'modemonlineup' => '1.3.6.1.4.1.4998.1.1.20.2.27.1.13'
                ),

                //nombre del tipo de interfaz al cual agregar los monitores
                'ifTypes' => array(
                    'docs cableupstream channel',
                    'docs cable downstream'
                ),

                // monitores base
                'baseMonitors' => function ($sysDesc) {
                    // dependiendo del tipo de cmts agregamos uno u otros monitores
                    if (preg_match('/CMTS_V08.02.00.97/i', $sysDesc)) {
                        return include_once __DIR__ . '/monitors/arrisC4.php';
                    } elseif (preg_match('/CER_V01.01.01.0009/i', $sysDesc)) {
                        return include_once __DIR__ . '/monitors/arrisE6000.php';
                    } else {
                        //no base manitors
                        return array();
                    }
                }
            ),
        'cisco' =>
            array(
                'monitors' => array(
                    'snr' => '1.3.6.1.2.1.10.127.1.1.4.1.5',
                    'modemonlineup' => '1.3.6.1.4.1.9.9.116.1.4.1.1.5'
                ),
                'ifTypes' => array(
                    'docs cable upstream',
                    'docs cable downstream'
                ),

                'baseMonitors' => function ($sysDesc) {

                    return include __DIR__ . '/monitors/cisco.php';
                }
            ),
        'motorola' =>
            array(
                'monitors' => array(
                    'snr' => '1.3.6.1.2.1.10.127.1.1.4.1.5',
                    'modemonlineup' => '1.3.6.1.4.1.4981.2.1.2.1.7'
                ),
                'ifTypes' => array(
                    'docs cable upstream',
                    'docs cableupstream channel'
                ),

                'baseMonitors' => function ($sysDesc) {
                    return include_once __DIR__ . '/monitors/motorola.php';
                }
            )
    )
);