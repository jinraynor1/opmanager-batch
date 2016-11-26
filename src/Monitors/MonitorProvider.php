<?php
namespace Jinraynor1\OpManager\Batch\Monitors;

use Jinraynor1\OpManager\Api\Main as ApiMain;
/**
 * Provides of custom monitors to a device
 *
 * @package    opmanager
 * @subpackage batchs
 * @author     Jimmy Atauje Hidalgo
 * @version    1.1
 *
 */

class MonitorProvider

{

    /**
     * File log resource
     * @var resource
     */
    protected $logHandler;
    protected $config;

    private $monitorOids=array();
    private $hasBaseTemplate=false;
    private $ifTypes=array();
    private $ifs=array();
    private $baseMonitors = array();


    private $sysOID = array();
    private $readCommunity = array();


    /** @var MonitorFiller */
    private $monitorFiller;
    /** @var MonitorTemplate */
    private $monitorTemplate;
    /** @var MonitorTracker */
    private $monitorTracker;

    public function setMonitorFiller($monitorFiller){
        $this->monitorFiller = $monitorFiller;
        return $this;
    }
    public function setMonitorTemplate($monitorTemplate){
        $this->monitorTemplate = $monitorTemplate;
        return $this;
    }

    public function setMonitorTracker($monitorTracker){
        $this->monitorTracker=$monitorTracker;
        return $this;
    }





    public function initialize($config,$deviceName){




        $this->config = $config;
        $this->deviceName = $deviceName;


        //inicializar ficheros para guardar logs
        $dirname = __ROOT_PATH__ . '/logs';

        if (!file_exists($dirname) || !is_dir($dirname)) {
            mkdir($dirname);
        }

        $this->logHandler = fopen("$dirname/$this->deviceName.log", 'a');

        if(!is_object($this->monitorFiller)){
            throw new Exception("Debes invocar al metodo setMonitorFiller");
        }

        if(!is_object($this->monitorTemplate)){
            throw new Exception("Debes invocar al metodo setmonitorTemplate");
        }

        $this->monitorFiller->setLogHandler($this->logHandler);
        $this->monitorTemplate->setLogHandler($this->logHandler);




        return $this;
    }


    /**
     *
     * @param $this ->deviceName
     */
    public function run()
    {



        //validar dispositivo
        if (!$this->deviceName) {
            $this->log("Ingrese nombre de dispositivo");
            return false;
        }

       //refrescar poleos
        $this->monitorTracker->refresh($this->deviceName);


        //fijar resumen del dispositivo
        $this->deviceSummary = ApiMain::dispatcher('getDeviceSummary', array('name' => $this->deviceName));





        if ($this->validateSummary() === false) {
            $this->log("La validacion ha fallado");
            return false;
        }

        //fija variables para la obtencion de monitores
        if ($this->initMonitorsParams() === false) {
            $this->log("La definicion de monitores ha fallado");
            return false;
        }


        //fija detalles de la conexion snmp
        if ($this->setSnmpConnectionDetails() === false) {
            $this->log("No se pudo determinar los parametros para la conexion SNMP");
            return false;
        }

        //valida nombre del sistema
        if ($this->checkSysName() === false) {
            $this->log("Fallo la revision de nombre de sistema del dispositivo");
            return false;
        }

        //fija las interfaces a la cual agregar monitores

        if ($this->filterInterfaces()=== false) {
            $this->log("Fallo al fijar interfaces");
            return false;
        }



        //Fijar valores para la plantilla
        $this->monitorTemplate->setDeviceName($this->deviceName);
        $this->monitorTemplate->setDeviceSummary($this->deviceSummary);
        $this->monitorTemplate->setBaseTemplate($this->hasBaseTemplate);
        $this->monitorTemplate->setSysOID($this->sysOID);

        $graphNames=$this->monitorTemplate->getGraphNames();


        //Fijar valores para el poller de monitores
        $this->monitorFiller->setDeviceName($this->deviceName);
        $this->monitorFiller->setDeviceSummary($this->deviceSummary);

        $this->monitorFiller->setGraphNames($graphNames);
        $this->monitorFiller->setBaseMonitors($this->baseMonitors);
        $this->monitorFiller->setMonitorOids($this->monitorOids);
        $this->monitorFiller->setIfs($this->ifs);
        $this->monitorFiller->setReadCommunity($this->readCommunity);
        $this->monitorFiller->setMonitorTracker($this->monitorTracker);


        //fija plantilla base (si la necesitase)
        if ($this->monitorTemplate->baseTemplate() === false) {
            $this->log("Fallo al fijar la plantilla base");
            return false;
        }

        //inicializa la plantilla
        if ($this->monitorTemplate->initTemplate() === false) {
            $this->log("Fallo al fijar la plantilla");
            return false;
        }

        //llena los monitores por default
        $this->monitorFiller->fillBaseMonitors();

        //llena los monitores dinamicos
        $this->monitorFiller->fillMonitors();

        //Obtiene monitores a procesar
        $currentMonitorIds = $this->monitorFiller->getCurrentMonitorIds();
        $currentMonitorGraphs = $this->monitorFiller->getCurrentMonitorGraphs();



        //Pasa a la plantilla los monitores
        $this->monitorTemplate->setCurrentMonitorIds($currentMonitorIds);
        $this->monitorTemplate->setCurrentMonitorGraphs($currentMonitorGraphs);


        // guarda la plantilla con los monitores
        if ($this->monitorTemplate->saveTemplate() === false) {
            $this->log("Fallo al guardar la plantilla");
            return false;
        }

        //asocia el dispositivo con la plantilla
        if ($this->monitorTemplate->bindTemplateDevice() === false) {
            $this->log("Fallo al asociar dispositivo con plantilla");
            return false;
        }

        return $this;

    }

    /**
     * Inicializa parametros para poleo de monitores
     * @return bool
     */
    public function initMonitorsParams()
    {
        $this->vendor_device = strtolower($this->deviceSummary->vendorName);

        $this->monitorOids = $this->config[$this->vendor_device]['monitors'];


        $this->ifTypes = $this->config[$this->vendor_device]['ifTypes'];

        if(isset($this->config[$this->vendor_device]['baseTemplate']) ){
            $this->hasBaseTemplate = $this->config[$this->vendor_device]['baseTemplate'];
        }


        if(isset($this->config[$this->vendor_device]['baseMonitors'])){

           $baseMonitors=$this->config[$this->vendor_device]['baseMonitors']($this->deviceSummary->sysDescr);

            if($baseMonitors === false ){
                $this->log("No se pudo obtener monitores base del dispositivo con descripcion " . $this->deviceSummary->sysDescr);
                return false;
            }

            $this->baseMonitors = $baseMonitors;
        }


        return true;
    }

    /**
     * Valida el resumen del dispositivo
     * @return bool
     */
    public function validateSummary()
    {

        if (is_object($this->deviceSummary) && isset($this->deviceSummary->error)) {

            $this->log("error al obtener el resumen del dispositivo");
            $this->log("error code: " . $this->deviceSummary->error->code . ', error message: ' . $this->deviceSummary->error->message);
            return false;
        }

        if (!isset($this->deviceSummary->sysName) || !$this->deviceSummary->sysName || trim($this->deviceSummary->sysName == '')) {
            $this->log("el nombre del sistema del dispositivo esta vacio y este se usa como el nombre para la plantilla, imposible continuar");
            return false;
        }

        if (!in_array(strtolower($this->deviceSummary->vendorName), array_keys($this->config))) {
            $this->log("el vendor ".$this->deviceSummary->vendorName." del dispositivo no pertenece a uno de los vendors contemplados: " . implode(',', array_keys($this->config)));
            return false;
        }

        $vendor_device = strtolower($this->deviceSummary->vendorName);

        if (!isset($this->config[$vendor_device])) {
            $this->log("El Vendor: (".$vendor_device.") no esta contemplado");
            return false;
        }


        return true;
    }

    public function log($str)
    {
        echo "$str\n";
        fwrite($this->logHandler, date('Y-m-d H:i:s') . ' ' . $str . PHP_EOL);

    }


    /**
     * Determina valor de conexiones snmp
     * @return bool
     */
    public function setSnmpConnectionDetails()
    {
        //Credenciales para obtener datos del dispositvo
        $credentialsList = ApiMain::dispatcher('GetCredentialsForDevice', array('name' => $this->deviceName));

        //OpManager devuelve vacio por intervalos al consultar credenciales, en ese caso recaer en la informacion local
        if (is_null($credentialsList) || !$credentialsList) {
            $this->log("las credenciales se obtendra de manera local");
            $credentialsList = getHardCredentials();

        }
        if (is_object($credentialsList) && isset($credentialsList->error)) {

            $this->log("error al obtener las credenciales");
            $this->log("error code: " . $credentialsList->error->code . ', error message: ' . $credentialsList->error->message);
            return false;
        }

        $this->log("empezando a determinar el OID para el dispositivo");

        // Determinar las credenciales del dispositivo con las cuales realizar la consulta

        if (isset($credentialsList->snmp) && is_array($credentialsList->snmp) && !empty($credentialsList->snmp)) {

            foreach ($credentialsList->snmp as $_credential) {

                $this->log("obteniendo detalle de credencial ".$_credential->credentialName);

                $credentialDetails = ApiMain::dispatcher('getCredentialDetails', array('credentialName' => $_credential->credentialName, 'type' => $_credential->type));

                // OpManager devuelve vacio por intervalos al consultar credenciales, en ese caso recaer en la informacion local

                if (is_null($credentialDetails) || !$credentialDetails) {
                    $this->log("detalles de  credencial  ".$_credential->credentialName." se obtendra de manera local");
                    $credentialsList = getHardCredentialDetail($_credential->credentialName);

                }

                if (is_object($credentialDetails) && isset($credentialDetails->error)) {

                    continue;
                } else {

                    // Realizar tantos intentos como retries extistan en la credencial

                    for ($retries = 0; $retries <= $credentialDetails->retries; $retries++) {


                        $sysOIDResponse = ApiMain::dispatcher('queryDeviceForSysOID', array('deviceName' => $this->deviceName, 'community' => $credentialDetails->readCommunity, 'port' => $credentialDetails->port));


                        if (is_object($sysOIDResponse) && isset($sysOIDResponse->error)) {

                            continue;
                        } else {
                            $this->readCommunity = $credentialDetails->readCommunity;
                            $this->sysOID = $sysOIDResponse->sysOID;

                            //Oid debe ser unico sino opmanager no puede  asociar dispostivio con su respectivo tipo

                            $this->sysOID .= '.' . rand(1, 99) . '.' . rand(1, 99) . '.' . rand(1, 99) . '.' . rand(1, 99) . '.' . rand(1, 99);
                            break(2);
                        }
                    }
                }
            }
        } else {
            $this->log("las credenciales en el sistema estan vacias");
            return false;
        }


        if (!$this->sysOID) {
            $this->log("no se pudo determinar el sysOID del dispositivo");
            return false;
        }


        if (!$this->readCommunity) {
            $this->log("no se pudo determinar la comunidad para lectura");
            return false;
        }

        $this->log("se obtuvo el oid: ".$this->sysOID." y comunidad: ".$this->readCommunity." para el dispositivo");

        return true;
    }

    /**
     * Verificar que el nombre del sistema que nos proporciona OpManager sea igual al nombre del sistema
     * obtenido por snmpwalk, si no fuese igual signficaria que han actualizado el nombre del sistema en el equipo
     *
     * @return bool
     */
    public function checkSysName()
    {
        $commandChkSysName = "snmpwalk -c {$this->deviceSummary->community} -v2c {$this->deviceName} SNMPv2-MIB::sysName 2>&1";

        exec($commandChkSysName, $resultCommandChkSysName);

        if (empty($resultCommandChkSysName)) {

            $this->log("Sin respuesta al verificar el nombre del sistema, comando: ".$commandChkSysName);
            return false;
        } else {

            if (preg_match('/timeout/i', $resultCommandChkSysName[0])) {
                $this->log("Timeout al verificar el nombre del sistema, comando: ".$commandChkSysName);
                return false;
            } else {

                $matchesSysName = array();
                preg_match('/STRING\:\s(.*)/i', $resultCommandChkSysName[0], $matchesSysName);

                if (!empty($matchesSysName) && isset($matchesSysName[1])) {
                    $sysName = $matchesSysName[1];
                    $this->log(" Nombre del sistema obtenido por snmpwalk: ".$sysName);

                } else {
                    $this->log("No se pudo obtener el nombre del sistema de la cadena: " . $resultCommandChkSysName[0]);
                    exit;
                }

            }

        }

        if ($this->deviceSummary->sysName != $sysName) {
            $this->log("El nombre de sistema obtenido por OpManager es: ".$this->deviceSummary->sysName." y el obtenido por SNMP es: ".$sysName.", deben ser iguales");
            return false;
        }


        $this->log(" Nombre de sistema iguales: (".$this->deviceSummary->sysName.",".$sysName.")");

        return true;

    }



    /**
     * Fija interfaces del dispositivo
     * @return bool
     */
    public function filterInterfaces()
    {
        $ifs = array();

        $interfaces = ApiMain::dispatcher('getInterfaces', array('name' => $this->deviceName));


        if (empty($interfaces) || !is_array($interfaces)) {
            $this->log("no existen interfaces");

        } else {

            foreach ($interfaces as $interface) {

                // Si interfaz es del tipo esperado, alimentar la matriz de interfaces a consultar

                if (is_object($interface) && isset($interface->ifType)

                    && in_array(strtolower($interface->ifType), $this->ifTypes)
                ) {
                    $ifs[$interface->ifIndex] = $interface->displayName;
                }
            }
        }

        if (empty($ifs)) {
            $this->log("no se encontraron interfaces en el dispositivo");
            return false;
        }

        $this->log("Se encontraron: " . count($ifs) . " interfaces");



        $this->ifs= $ifs;

        return true;


    }






}
