<?php
namespace Jinraynor1\OpManager\Batch\Monitors;

use Jinraynor1\OpManager\Api\Main as ApiMain;

/**
 * Creates  the template of monitors and associates the device
 * Class MonitorTemplate
 * @package Jinraynor1\OpManager\Batch\Monitors
 */
class Template{

    private $deviceName; //done
    private $_base_template = false; //done
    private $_base_graph_ids = array(); //internal
    private $current_monitor_ids;//done
    private $current_monitor_graphs;//done
    private $deviceSummary; //done
    private $sysOID; //done

    protected $chunk_monitor_size; //done
    protected $sleep_for_chunked_monitors; //done

    private $templateName;
    private $paramsTemplate;

    private $logHandler;

    /**
     * @param mixed $deviceName
     */
    public function setDeviceName($deviceName)
    {
        $this->deviceName = $deviceName;
    }

    /**
     * @param mixed $_base_template
     */
    public function setBaseTemplate($_base_template){
        $this->_base_template;
    }
    /**
     * @param array $base_graph_ids
     */
    public function setBaseGraphIds($base_graph_ids)
    {
        $this->_base_graph_ids = $base_graph_ids;
    }

    /**
     * @param mixed $current_monitor_ids
     */
    public function setCurrentMonitorIds($current_monitor_ids)
    {
        $this->current_monitor_ids = $current_monitor_ids;
    }

    /**
     * @param mixed $current_monitor_graphs
     */
    public function setCurrentMonitorGraphs($current_monitor_graphs)
    {
        $this->current_monitor_graphs = $current_monitor_graphs;
    }

    /**
     * @param mixed $deviceSummary
     */
    public function setDeviceSummary($deviceSummary)
    {
        $this->deviceSummary = $deviceSummary;
    }

    /**
     * @param mixed $sysOID
     */
    public function setSysOID($sysOID)
    {
        $this->sysOID = $sysOID;
    }


    public function setLogHandler($logHandler){
        $this->logHandler = $logHandler;
    }

    public function log($str)
    {
        echo "$str\n";
        fwrite($this->logHandler, date('Y-m-d H:i:s') . ' ' . $str . PHP_EOL);

    }

    public function __construct($config){
        $this->chunk_monitor_size = $config['chunk_monitor_size'];
        $this->sleep_for_chunked_monitors = $config['sleep_for_chunked_monitors'];

    }
    public function baseTemplate()
    {


        //Determinar los ids de los monitores/graphs de la platilla base, si es que se ha configurado por el momento no se esta usando
        //esta caracteristica

        if ($this->_base_template) {
            $responseViewDeviceTemplate = ApiMain::dispatcher('viewDeviceTemplate', array('typeID' => $this->_base_template));

            if (is_object($responseViewDeviceTemplate) && isset($responseViewDeviceTemplate->error)) {

                $this->log("error al consultar la plantilla");
                $this->log("error code: " . $responseViewDeviceTemplate->error->code . ', error message: ' . $responseViewDeviceTemplate->error->message);

                return false;

            }


            if (isset($responseViewDeviceTemplate->configData) && isset($responseViewDeviceTemplate->configData->graphDetails) && !empty($responseViewDeviceTemplate->configData->graphDetails)) {

                foreach ($responseViewDeviceTemplate->configData->graphDetails as $graphDetails) {

                    $this->_base_graph_ids[] = $graphDetails->id;//Ids de la plantilla base


                }
            } else {
                $this->log("advertencia no se encontaron monitores en la plantilla base");
            }

            $this->log("se encontaron " . count($this->_base_graph_ids) . " monitores en la plantilla base");


        }

        return true;
    }

    public function initTemplate()
    {


        $templates = ApiMain::dispatcher('listDeviceTemplates');
        $_template_edit = false;

        //usar nombre del sistema del dispositivo como el nombre para la plantilla
        $this->templateName = $this->deviceSummary->sysName;


        if (is_object($templates) && isset($templates->error)) {

            $this->log("no se pudo listar plantillas del sistema");
            $this->log("error code: " . $templates->error->code . ', error message: ' . $templates->error->message);
            return false;
        }


        if (empty($templates) || !is_array($templates)) {
            $this->log("las plantillas del sistema no pueden estar vacias,detienendo proceso.");
            return false;
        }

        // Determina si la plantilla se insertara o solo actualizara

        foreach ($templates as $template) {
            if ($template->type == $this->templateName) {
                $_template_edit = true;
            }
        }

        // Parametros de la plantilla

        $this->paramsTemplate = array(
            'typeName' => $this->templateName,
            'graphID' => '',     //Inicializar plantilla con ids de monitores vacios    //implode(',', $current_monitor_ids),
            'deviceType' => $this->templateName,
            'IconName' => 'default.png',
            'pingInterval' => '5',//tambien es el  intervalo de interfaces al parecer
            'category' => $this->deviceSummary->category,
            'vendor' => $this->deviceSummary->vendorName,
            'sysOID' => $this->sysOID,
            'graphObj' => '',   //Inicializar plantilla con objetos de monitores vacios  //json_encode($graphObj),
            'dialArray' => ''
        );

        $this->log("parametros de la plantilla: " . json_encode($this->paramsTemplate));

        // Si es una nueva plantilla intentamos primero crearla en el sistema con monitores vacios

        if (!$_template_edit) {

            $tryAdd = 1;

            // Revisar que el OID de la plantilla no exista en el sistema

            do {

                $checkDeviceIdentifier = ApiMain::dispatcher('checkDeviceIdentifier', array('sysOID' => $this->sysOID));


                if (isset($checkDeviceIdentifier->error)) {

                    $this->log("sysOID: ".$this->sysOID." ya existe " . (isset($checkDeviceIdentifier->error->message) ? $checkDeviceIdentifier->error->message : ''));

                    $this->sysOID .= '.' . rand(1, 9);
                } else {


                    // Si no existe entonces podemos intentar agregar la plantilla


                    $this->log(" se determino nuevo sysOID ".$this->sysOID." exitosamente");

                    $this->paramsTemplate['sysOID'] = $this->sysOID;


                    $responseTemplate = ApiMain::dispatcher('addDeviceTemplate', $this->paramsTemplate);


                    // Si a pesar de la verificacion anterior nos arroja el error de que no existe la plantila
                    // puede deberse a que otro proceso en paralelo la uso para crear una nueva al mismo tiempo


                    if (is_object($responseTemplate) && isset($responseTemplate->error)
                        && $tryAdd <= 5 && isset($responseTemplate->error->message)
                        && preg_match('/Device Template already exists/i', $responseTemplate->error->message)
                    ) {

                        // Si existe la plantilla setear un codigo de error personalizado y un mensaje de error apropiado
                        // con esto estamos forzando a que el bucle continue verificando la existencia del OID y asegurandonos
                        // de que la plantiilla se inserte


                        $checkDeviceIdentifier->error = 666;
                        $checkDeviceIdentifier->error->message = $responseTemplate->error->message;

                        $tryAdd++;

                    }

                }


            } while (isset($checkDeviceIdentifier->error));

            // Verificar si la plantilla se creo exitosamente

            if (is_object($responseTemplate) && isset($responseTemplate->error)) {

                $this->log("error al crear la plantilla");
                $this->log("error code: " . $responseTemplate->error->code . ', error message: ' . $responseTemplate->error->message);
                return false;
            }

        }

        return true;

    }

    public function saveTemplate()
    {

        if (empty($this->current_monitor_ids)) {
            $this->log("No se encontraron oids de monitores para este dispositivo");
        }

        //  Lista de graphIds para la plantilla son los ids de los monitores que agregamos + los monitores de la plantilla base
        //  actualmente $_base_graph_ids es una matriz vacia ya que no estan usando la caracteristica de plantilla base

        $graphIds = array_merge($this->current_monitor_ids, $this->_base_graph_ids);

        $this->log("graphIds a enviar: " . implode(',', $graphIds));


        // El api no es capaz de soportar una gran cantidad de monitores enviados
        // asi que dividiremos el trabajo en pequenas partes para poder realizar la actualizacion

        if (!empty($this->current_monitor_graphs)) {

            $this->log("actualizar la plantilla con los monitores");

            $chunked_monitor_graphs = array_chunk($this->current_monitor_graphs, $this->chunk_monitor_size);


            foreach ($chunked_monitor_graphs as $idxChunk => $block_graphs) {

                // Si el bloque se procesa por segunda vez debemos de consultar nuevamente la matriz de graficas
                // debido a que el bloque que enviamos anteriormente ya genero ids para los nuevos monitores
                // (custGraph_1,custGraph_2, etc)

                if ($idxChunk > 0) {


                    // Si ya hemos agregado un chunk(bloque)de monitores en un bucle anterior debemos darle un tiempo a OpManager para que
                    // termine de insertar los monitores y estos esten disponibles
                    // todo: codificar una mejor forma de esperar por los monitores agregados

                    if ($this->sleep_for_chunked_monitors) {

                        $this->log("esperando ".$this->sleep_for_chunked_monitors." (segundos) antes de consultar graficas de monitores");
                        sleep($this->sleep_for_chunked_monitors);

                    }

                    $this->log("consultando matriz de nombres de graficas");

                    $_graphName = $this->getGraphNames();





                    foreach ($graphIds as $_current_monitor_name => $_current_monitor_id) {

                        if (!is_numeric($_current_monitor_id) && isset($_graphName[$_current_monitor_name])) {


                            if ($_graphName[$_current_monitor_name]) {
                                $this->log("bloque monitorNombre-Id encontrado, monitor: ".$_current_monitor_name."  id: ".$_graphName[$_current_monitor_name]);
                                $graphIds[$_current_monitor_name] = $_graphName[$_current_monitor_name];


                            } else {
                                $this->log("advertencia no se encontro id para el monitor: ".$_current_monitor_name);
                            }
                        }

                    }

                    $this->log("chunk " . ($idxChunk + 1) . " graphIds a enviar: " . implode(',', $graphIds));
                }


                $this->paramsTemplate['graphID'] = implode(',', $graphIds);//La api espera todos los ids
                $this->paramsTemplate['graphObj'] = json_encode(array('dataArray' => $block_graphs));//Pero si podemos especificar un bloque =D exito!


                // Cuando se actualizan valores de las plantillas no hay problemas de sysOID

                $responseTemplate = ApiMain::dispatcher('updateDeviceTemplate', $this->paramsTemplate);

                $this->log("actualizando plantilla");

                if (is_null($responseTemplate) || !$responseTemplate) {
                    $this->log("sin respuesta al actualizar plantilla");
                }

                if (is_object($responseTemplate) && isset($responseTemplate->error)) {


                    $this->log("error al actualizar la plantilla");
                    $this->log("error code: " . $responseTemplate->error->code . ', error message: ' . $responseTemplate->error->message);

                    return false;
                }
            }
        } else {
            $this->log("actualizar la plantilla con los ids que disponemos");


            // Este bloque de codigo se activa cuando nose encontro ningun monitor, entonces al menos debemos
            // actualizar el dispositivo con sus monitores por defecto

            $this->paramsTemplate['graphID'] = implode(',', $graphIds);//La api espera todos los ids

            $responseTemplate = ApiMain::dispatcher('updateDeviceTemplate', $this->paramsTemplate);

            if (is_object($responseTemplate) && isset($responseTemplate->error)) {

                $this->log("error al actualizar la plantilla");
                $this->log("error code: " . $responseTemplate->error->code . ', error message: ' . $responseTemplate->error->message);

                return false;
            }

        }

        if (isset($responseTemplate->result) && isset($responseTemplate->result->message)) {
            $this->log("se termino de actualizar datos de la plantilla satisfactoriamente");
            return true;
        }

        $this->log("no se puedo determinar la respuesta al crear la plantila: " . json_decode($responseTemplate));

        return false;


    }

    /**
     * Asocia dispositivo a plantilla
     * @return bool
     */
    public function bindTemplateDevice()
    {

        $this->log("procediendo a actualizar el dispositivo");

        $paramsUpdateDeviceDetails = array(
            'vendor' => $this->deviceSummary->vendorName,
            'name' => $this->deviceName,
            'category' => $this->deviceSummary->category,
            'displayName' => $this->deviceSummary->displayName,
            'ipAddress' => $this->deviceSummary->ipAddress,
            'type' => $this->templateName
        );

        $responseUpdateDeviceDetails = ApiMain::dispatcher('UpdateDeviceDetails', $paramsUpdateDeviceDetails);

        $this->log("parametros detalles del dispositivo: " . json_encode($paramsUpdateDeviceDetails));

        if (is_object($responseUpdateDeviceDetails) && isset($responseUpdateDeviceDetails->error)) {

            $this->log("error al actualizar los detalles del dispositivo");
            $this->log("error code: " . $responseUpdateDeviceDetails->error->code . ', error message: ' . $responseUpdateDeviceDetails->error->message);
            return false;

        } else {

            if (isset($responseUpdateDeviceDetails->result) && isset($responseUpdateDeviceDetails->result->message)) {
                $this->log("Se actualizo dispositivo con exito: " . $responseUpdateDeviceDetails->result->message);
            } else {
                $this->log("no se puedo determinar la respuesta al editar el dispositivo: " . json_decode($responseUpdateDeviceDetails));
                return false;
            }
        }

        // Debemos asociar la plantilla al dispositivo para que cualquier cambio haga efecto
        $responseAssociateDeviceTemplate = ApiMain::dispatcher('associateDeviceTemplate', array(
            'selectedDevices' => $this->deviceName,
            'typeName' => $this->templateName

        ));

        if (is_object($responseAssociateDeviceTemplate) && isset($responseAssociateDeviceTemplate->error)) {

            $this->log("error al asociar la plantilla al dispositivo");
            $this->log("error code: " . $responseAssociateDeviceTemplate->error->code . ', error message: ' . $responseAssociateDeviceTemplate->error->message);
            return false;
        } else {
            if (isset($responseAssociateDeviceTemplate->result) && isset($responseAssociateDeviceTemplate->result->message)) {

                $this->log("Asociar plantilla exito: " . $responseAssociateDeviceTemplate->result->message);
                return true;

            } else {
                $this->log("no se puedo determinar la respuesta al asociar la plantilla al dispositivo: " . json_decode($responseAssociateDeviceTemplate));
                return false;
            }

        }

    }

    /**
     * obtiene las graficas de monitores
     * @return array
     */
    public function getGraphNames()
    {

        $_graphName = array();

        if(!isset($this->deviceName) || !$this->deviceName){
            throw new Exception("debes invocar al metodo setDeviceName con un valor valido antes de llamar a getGraphNames");
        }

        if(!isset($this->deviceSummary) || !isset($this->deviceSummary->type) || !isset($this->deviceSummary->category)){
            throw new Exception("debes invocar al metodo setDeviceSummary con el objeto describiendo el dispositivo antes de llamar a getGraphNames");
        }

        $performanceMonitors = ApiMain::dispatcher('getPerformanceMonitors', array(
            'deviceName' => $this->deviceName, 'type' => $this->deviceSummary->type, 'category' => $this->deviceSummary->category));


        if (is_object($performanceMonitors) && isset($performanceMonitors->error)) {
            $error="error al consultar monitores, error code: " . $performanceMonitors->error->code . ', error message: ' . $performanceMonitors->error->message;
            $this->log($error);
            throw new Exception($error);
            return false;
        }

        if (isset($performanceMonitors->{'Vendor Monitors'})) {
            if (!empty($performanceMonitors->{'Vendor Monitors'})) {
                foreach ($performanceMonitors->{'Vendor Monitors'} as $_monitor) {
                    $_graphName[$_monitor->name] = $_monitor->GRAPHID;
                }
            }
        }

        if(empty($_graphName)){
            $this->log("La consulta de graficas no ha devuelto ningun valor");
        }


        return $_graphName;
    }


}