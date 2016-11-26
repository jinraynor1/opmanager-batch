<?php
namespace Jinraynor1\OpManager\Batch\Monitors;

/**
 * Obtains the custom monitors to be added
 * Class MonitorFiller
 * @package Jinraynor1\OpManager\Batch\Monitors
 */
class MonitorFiller
{
    private $deviceName = null; //done
    private $deviceSummary = null; //done
    private $_base_monitors = array(); //done
    private $graphIdCount = 1;

    private $_monitor_oids = array(); //done
    private $_ifs = array(); // done
    private $readCommunity = null; //done
    private $monitorTracker = null; // done


    private $current_monitor_ids = array();
    private $current_monitor_graphs = array();

    private $graphNames = array();


    private $logHandler;

    /**
     * @param mixed $deviceName
     */
    public function setDeviceName($deviceName)
    {
        $this->deviceName = $deviceName;
    }

    /**
     * @param mixed $deviceSummary
     */
    public function setDeviceSummary($deviceSummary)
    {
        $this->deviceSummary = $deviceSummary;
    }

    /**
     * @param mixed $base_monitors
     */
    public function setBaseMonitors($base_monitors)
    {
        $this->_base_monitors = $base_monitors;
    }

    /**
     * @param mixed $graphIdCount
     */
    public function setGraphIdCount($graphIdCount)
    {
        $this->graphIdCount = $graphIdCount;
    }

    /**
     * @param mixed $monitor_oids
     */
    public function setMonitorOids($monitor_oids)
    {
        $this->_monitor_oids = $monitor_oids;
    }

    /**
     * @param mixed $ifs
     */
    public function setIfs($ifs)
    {
        $this->_ifs = $ifs;
    }

    /**
     * @param mixed $readCommunity
     */
    public function setReadCommunity($readCommunity)
    {
        $this->readCommunity = $readCommunity;
    }

    /**
     * @param mixed $monitorTracker
     */
    public function setMonitorTracker($monitorTracker)
    {
        $this->monitorTracker = $monitorTracker;
    }

    /**
     * @param array $graphNames
     */
    public function setGraphNames($graphNames)
    {
        $this->graphNames = $graphNames;
    }


    /**
     * @return mixed
     */
    public function getCurrentMonitorIds()
    {
        return $this->current_monitor_ids;
    }

    /**
     * @return mixed
     */
    public function getCurrentMonitorGraphs()
    {
        return $this->current_monitor_graphs;
    }


    public function setLogHandler($logHandler)
    {
        $this->logHandler = $logHandler;
    }

    public function log($str)
    {
        echo "$str\n";
        fwrite($this->logHandler, date('Y-m-d H:i:s') . ' ' . $str . PHP_EOL);

    }

    public function __construct($config)
    {


        $this->config = $config;
    }

    public function fillBaseMonitors()
    {

        // Agregar monitores extra para el dispositivo

        if (!empty($this->_base_monitors)) {
            $this->log("empezando a determinar los monitores base ,cantidad:" . count($this->_base_monitors));
            $base_monitor_added = 0;
            foreach ($this->_base_monitors as $_base_monitor) {


                $_base_monitor_name = $_base_monitor['name'];
                $_base_monitor_index = isset($_base_monitor['ifIndex']) ? $_base_monitor['ifIndex'] : '';
                $_base_monitor_oid = $_base_monitor['oid'];

                $_base_monitor_function = isset($_base_monitor['function']) ? $_base_monitor['function'] : '';

                if ($_base_monitor_function) {
                    $_base_monitor_has_function = function_exists($_base_monitor_function);
                } else {
                    $_base_monitor_has_function = false;
                }

                $base_monitor_oid = '.' . $_base_monitor_oid . ($_base_monitor_index ? ('.' . $_base_monitor_index) : '');


                $command = "snmpwalk -v 2c -c $this->readCommunity $this->deviceName  $base_monitor_oid ";
                $resultCommand = shell_exec($command);


                //  Verificar que exista

                if (preg_match('/[a-z_\-0-9]+:\s([0-9]+)/i', $resultCommand, $resultMatches)) {

                    if ($_base_monitor_has_function) {

                        $resultValidBaseMonitor = call_user_func_array($_base_monitor_function, array('matches' => $resultMatches[1]));


                        $isValidBaseMonitor = $resultValidBaseMonitor['result'];


                        if (!$isValidBaseMonitor) {
                            $this->log("monitor base " . $_base_monitor_name . " no es valido, no se creara");
                            continue;
                        } else {
                            $base_monitor_added++;
                        }

                    } else {
                        if (isset($resultMatches[1]) && $resultMatches[1] > 0) {
                            $base_monitor_added++;
                        } else {
                            $this->log("monitor base " . $_base_monitor_name . " valor del monitor es cero, no se creara");
                            continue;
                        }

                    }

                } else {
                    $this->log("monitor base " . $_base_monitor_name . " no encontrado, no se creara");
                    continue;
                }


                $base_monitor_name = "$_base_monitor_name $this->deviceName";

                if (isset($this->graphNames[$base_monitor_name])) {

                    $base_monitor_id = $this->graphNames[$base_monitor_name];
                } else {
                    $base_monitor_id = 'custGraph_' . $this->graphIdCount;
                    $this->graphIdCount++;
                }

                $this->current_monitor_ids[$base_monitor_name] = $base_monitor_id;


              $default_base_monitors =  array(
                  'graphName' => $base_monitor_name,
                  'displayName' => $base_monitor_name,
                  'oid' => $base_monitor_oid,
                  'id' => $base_monitor_id,
              );


                if(!empty($this->config['graphs']['baseMonitors']) && is_array($this->config['graphs']['baseMonitors'])){
                        $default_base_monitors =  $this->config['graphs']['baseMonitors'] + $default_base_monitors;
                }

                array_push($this->current_monitor_graphs, $default_base_monitors);


            }
            $this->log("se agregaran " . $base_monitor_added . " monitores base(" . implode(',', $this->current_monitor_ids) . ")");

        }

        return true;
    }

    public function fillMonitors()
    {


        // variables para calcular el porcentaje de monitores que vamos actualmente

        $cantidadMons = count($this->_monitor_oids);
        $cantidadTot = count($this->_ifs) * $cantidadMons;


        $rangeModemOnLineUp = $this->config['rangeModemOnLineUp'];

        // cada 5% del total mostrar mensaje de avance
        $itemPercAt = floor($cantidadTot * 0.05);
        if ($itemPercAt <= 1) {
            $itemPercAt = ceil($cantidadTot * 0.05);
        }

        $idxMon = 1;

        // Este bloque determina los oids de los monitores a agregar

        foreach ($this->_monitor_oids as $_monitor_oid_tipo => $monitor_oid) {


            foreach ($this->_ifs as $ifIndex => $if) {

                $idxMon++;


                //Notificar avance

                if ($idxMon % $itemPercAt == 0) {
                    $porcentajeActual = number_format(($idxMon * 100) / $cantidadTot, 2);
                    $this->log("calculando interfaz($_monitor_oid_tipo) $idxMon de $cantidadTot - $porcentajeActual% de las interfaces");
                }


                $current_monitor_name = "$_monitor_oid_tipo $this->deviceName $ifIndex";
                $current_monitor_display_name = "$_monitor_oid_tipo $this->deviceName $if";

                if (isset($this->graphNames[$current_monitor_name])) {

                    $current_monitor_id = $this->graphNames[$current_monitor_name];
                } else {
                    $current_monitor_id = 'custGraph_' . $this->graphIdCount;
                    $this->graphIdCount++;
                }


                // Comunmente el OID del monitor es el oid del tipo mas el indice de la interfaz
                $current_monitor_oid = '.' . $monitor_oid . '.' . $ifIndex;


                // Si dispositivo es del vendor Arris y el tipo de monitor es modemonlineup realizar
                // los siguientes calculos para determinar el OID de cada monitor

                if ($_monitor_oid_tipo == 'modemonlineup' && strtolower($this->deviceSummary->vendorName) == 'arris') {


                    //si es modemonlineup entonces realizar snmp walk manual debido a que es un indice/oid dinamico

                    //todo: barrer con snmpwalk


                    foreach ($rangeModemOnLineUp as $_rangeModemOnLineUp) {


                        $command = "snmpwalk -v 2c -c $this->readCommunity $this->deviceName  $monitor_oid.$_rangeModemOnLineUp.$ifIndex ";
                        $resultCommand = shell_exec($command);


                        //Realizar bucle hasta obtener respuesta

                        if (preg_match('/INTEGER:\s([0-9]+)/i', $resultCommand, $resultMatches)) {


                            // Si numero entero obtenido es mayor que cero salir del bucle porque ya encontramos un OID
                            // valido el cual monitorear

                            if (isset($resultMatches[1]) && $resultMatches[1] > 0) {
                                break;
                            }

                        }
                    }


                    // Terminado el bucle de busqueda de OID por rangos verificar por ultima vez si
                    // se obtuvo respuesta valida

                    if (!preg_match('/INTEGER:\s([0-9]+)/i', $resultCommand, $resultMatches)) {
                        //$this->log("Interfaz $ifIndex no encontrada en el OID($_monitor_oid_tipo):$monitor_oid");

                        continue;
                    } else {

                        // Si numero entero obtenido es invalido continuar con el siguiente item del bucle
                        // ya que solo monitoreamos OID con valores validos

                        if (!isset($resultMatches[1]) || is_null($resultMatches[1])) {
                            continue;
                        }

                    }


                    $resultFields=explode(' ', $resultCommand);
                    $resultParts = reset($resultFields);
                    $OIDparts = (explode('.', $resultParts));
                    $ifIndexPart = end($OIDparts);
                    $slotPart = prev($OIDparts);

                    if (!$slotPart) {
                      //  $this->log("Interfaz $ifIndex no encontrada en el OID($_monitor_oid_tipo):$monitor_oid");
                        continue;
                    } else {
                        $current_monitor_oid = '.' . $monitor_oid . '.' . $slotPart . '.' . $ifIndex;
                    }
                } else {

                    //Flujo comun para verificar que existe el monitor

                    $command = "snmpwalk -v 2c -c $this->readCommunity $this->deviceName  $monitor_oid.$ifIndex ";
                    $resultCommand = shell_exec($command);

                    //Si no existe el oid entonces probar con el siguiente
                    if (!preg_match('/INTEGER:\s([0-9]+)/i', $resultCommand, $resultMatches)) {
                        //$this->log("Interfaz $ifIndex no encontrada en el OID($_monitor_oid_tipo):$monitor_oid");
                        continue;
                    } else {

                        // Si numero entero obtenido es invalido continuar con el siguiente item del bucle
                        // ya que solo monitoreamos OID con valores validos

                        if (!isset($resultMatches[1]) || is_null($resultMatches[1])) {
                            continue;
                        }
                    }


                }


                $snmp_integer = $resultMatches[1];

                //Almacenar el valor snmp
                if (!$this->monitorTracker->save($this->deviceName, $current_monitor_oid, $snmp_integer)) {
                    $this->log("No se pudo guardar el snmp historico de $current_monitor_oid " . $this->monitorTracker->getError());
                };

                //Si el valor snmp actual es 0, revisar si no se debe a una baja temporal
                if ($snmp_integer <= 0) {


                    //Consultar ultimo historico de valores snmp para determinar si estuvo activo
                    $snmp_last_polled_value = $this->monitorTracker->get($this->deviceName, $current_monitor_oid);;

                    if ($snmp_last_polled_value === false) {
                        $this->log("No se pudo consultar el historico de $current_monitor_oid " . $this->monitorTracker->getError());
                    }


                    if (!$snmp_last_polled_value) {
                        //Si no existe un valor registrado entonces no seguir
                        continue;
                    } else {
                        //Sobreecribbimos el valor obtenido por uno anterior
                        $snmp_integer = $snmp_last_polled_value;
                    }


                }

                switch ($_monitor_oid_tipo) {


                    // Si tipo de monitor es ruido(snr) entonces ponemos un valor fijo en trouble

                    case 'snr':
                        $troubleVal = $this->config['thresholds']['snr']['troubleVal'];
                        $rearmVal = $this->config['thresholds']['snr']['rearmVal'];
                        $units = $this->config['thresholds']['snr']['units'];
                        break;


                    // Si tipo de monitor es de cantidad de usuarios conectados (modemonlineup) entonces el valor para trouble es
                    // el 50% del ultimo valor obtenido al descubrir el OID

                    case 'modemonlineup':
                        $validateModem = $this->config['thresholds']['modemonlineup']['validate']($snmp_integer); //invoca funcion

                        if(!$validateModem){
                            $this->log("Valor no valido par monitorear, OID: $current_monitor_oid, modemonlineup: $snmp_integer ");
                            //si no es valido saltamos a las siguiente iteracion
                            continue(2);
                        }

                        $troubleVal = $this->config['thresholds']['modemonlineup']['troubleVal']($snmp_integer); //invoca funcion
                        $rearmVal = $this->config['thresholds']['modemonlineup']['rearmVal']($snmp_integer); //invoca funcion
                        $units = $this->config['thresholds']['modemonlineup']['units'];
                        break;


                    default:
                        $troubleVal = '';
                        $rearmVal = '';
                        $units = 'units';
                        break;
                }


                $this->current_monitor_ids[$current_monitor_name] = $current_monitor_id;

                $default_monitors = array(
                    'graphName' => $current_monitor_name,
                    'displayName' => $current_monitor_display_name,
                    'oid' => $current_monitor_oid,
                    'units' => $units,
                    'id' => $current_monitor_id,
                    'troubleVal' => $troubleVal,
                    'rearmVal' => $rearmVal,
                    );



                if(!empty($this->config['graphs']['childMonitors']) && is_array($this->config['graphs']['childMonitors'])){
                    $default_monitors =  $this->config['graphs']['childMonitors'] + $default_monitors;
                }

                array_push($this->current_monitor_graphs,$default_monitors);


            }


        }

        if (empty($this->current_monitor_ids)) {
            $this->log("No se encontraron oids de monitores para este dispositivo");
        }

        return true;
    }

}