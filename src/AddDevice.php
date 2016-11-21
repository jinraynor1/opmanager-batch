<?php
namespace Jinraynor1\OpManager\Batch;

use Jinraynor1\OpManager\Api\Main as ApiMain;


// columnas del archivo a importar
define('OPIMPORT_COL_IP', 0); //ip
define('OPIMPORT_COL_DISPLAYNAME', 1); //cmts
define('OPIMPORT_COL_VENDOR', 2); //vednor
define('OPIMPORT_COL_BUSINESSVIEW', 3); //business views
define('OPIMPORT_COL_CATEGORY', 4); //device category
define('OPIMPORT_COL_TYPE', 5); //device type
define('OPIMPORT_COL_MONITORING', 6); //device monitoring
define('OPIMPORT_COL_NETMASK', 7); //device netmask

/**
 * Add devices
 */
class AddDevice extends Base
{


    /**
     * Seconds to wait if we delete a device and later we add it
     * @var int
     */
    private $secs_wait_afterdel = 5;

    /**
     * Maximum times we try to add a device if it fails
     * @var int
     */
    private $try_times = 5;


    /**
     * Default category when importing the device
     * @var null
     */
    private $default_category = null;

    /**
     * Default type when importing the device
     * @var null
     */
    private $default_type = null;

    /**
     * Default monitor interval when importing the device
     * @var null
     */
    private $default_monitoring = 5;

    /**
     * Default network mask when importing the device
     * @var null
     */
    private $default_netmask = null;


    private $credentialName = array();
    private $devicesExist = array();
    private $businessViews = array();


    private $cleared = null;
    private $failAdd = null;

    private $businessViewToDev = array();

    public function __construct($lines = array(), $syncMode = array())
    {
        // remove first heading line
        $this->lines = array_shift($lines);

        $this->syncMode = $this->syncMode;

        $this->getCurrentInventory();

    }

    /**
     *  Get information about existing devices, credentials and vendors
     */
    public function getCurrentInventory()
    {


        // get credentials in opmanager
        if ($this->syncMode['discoverInterfaces'] || $this->syncMode['add']) {
            $credentials = ApiMain::dispatcher('listCredentials');

            if (!is_object($credentials) || empty($credentials)) {
                $this->opimport_msg("Empty credentials , first add some in opmanager","error");
                exit(1);
            } else {

                foreach ($credentials as $credentialType => $credentialItems) {
                    if (!empty($credentialItems))
                        foreach ($credentialItems as $credentialItem)
                            $this->credentialName[] = $credentialItem->credentialName;
                }
            }

        }


        // read existing business view if needed
        if ($this->syncMode['addToCustomBusinessView']) {
            $_businessViews = ApiMain::dispatcher('getBusinessView');
            if (is_object($_businessViews) && isset($_businessViews->BusinessView->Details) && !empty($_businessViews->BusinessView->Details)) {

                foreach ($_businessViews->BusinessView->Details as $bv) {
                    $this->businessViews[] = $bv->displayName;
                }
            }
        }


        // read existing devices
        $devices = ApiMain::dispatcher('listDevices');

        $errorDevices = $this->checkCommonErrorResponse($devices, 'fetch-info', 'list devices');
        if ($errorDevices) {
            echo $errorDevices;
            exit(1);
        }


        if (!empty($devices) && is_array($devices)) {
            foreach ($devices as $device) {
                $this->devicesExist[$device->deviceName] = $device->ipaddress;
            }
        }


        //add more vendors if needed
        $vendorList = ApiMain::dispatcher('getVendorList');
        if (is_array($vendorList)) {
            $addVendor = array();

            foreach ($this->lines as $_line) {

                $lineVendor = str_getcsv($_line);
                if (isset($lineVendor[OPIMPORT_COL_VENDOR]) && trim($lineVendor[OPIMPORT_COL_VENDOR]) != '') {
                    if (!in_array($lineVendor[OPIMPORT_COL_VENDOR], $vendorList)) {
                        $addVendor[] = $lineVendor[OPIMPORT_COL_VENDOR];
                    }
                }
            }

            $addVendor = array_values(array_unique($addVendor));

            if (!empty($addVendor)) {
                foreach ($addVendor as $vendor) {
                    ApiMain::dispatcher('addVendor', array('vendor' => $vendor));
                }
            }

        }
    }

    /**
     * Add current device
     * @return bool
     */
    public function addDevice()
    {
        if (!in_array($this->deviceIP, $this->devicesExist)) {

            if ($this->debug) {
                $this->opimport_dump('add device', $this->paramsDevice);
            }
            do {

                if ($this->cleared || $this->failAdd) {

                    $this->opimport_msg("$this->deviceDisplayName | waiting $this->secs_wait_afterdel secs before trying to add the device");
                    sleep($this->secs_wait_afterdel);
                }


                $responseDeviceAdd = ApiMain::dispatcher('addDevice', $this->paramsDevice);


                if (is_object($responseDeviceAdd) &&
                    isset($responseDeviceAdd->device) && isset($responseDeviceAdd->device->deviceName)
                ) {

                    $devicesExist[$responseDeviceAdd->device->deviceName] = $this->deviceIp;
                }

                $resultDeviceAdd = $this->handleCommonResponse($responseDeviceAdd, $this->deviceDisplayName, 'agregar');

                $this->opimport_msg($resultDeviceAdd['message']);

                if (!$resultDeviceAdd['result']) {
                    $this->opimport_msg("$this->deviceDisplayName | error adding the device", "error");
                    $this->failAdd = true;
                    $this->try_times -= 1;
                }
            } while (preg_match('/error code/i', $resultDeviceAdd['message']) && $this->try_times);

            if (!$resultDeviceAdd['result']) {
                $this->opimport_msg("$this->deviceDisplayName | cannot be added", "error");
                return false;
            }
        } else {
            $this->opimport_msg("$this->deviceDisplayName | this device already exists");
        }

        return true;
    }

    /**
     * Delete current device
     */
    public function deleteDevice()
    {
        if (in_array($this->deviceIp, $this->devicesExist)) {

            $deviceNameDel = array_search($this->deviceIp, $this->devicesExist);


            $paramsDel = array(
                'deviceName' => $deviceNameDel, //required
            );
            if ($this->debug) {
                $this->opimport_dump('delete device', $paramsDel);
            }
            $responseDeviceDel = ApiMain::dispatcher('deleteDevice', $paramsDel);


            unset($this->devicesExist[$deviceNameDel]);

            $resultDeviceDel = $this->handleCommonResponse($responseDeviceDel, $this->deviceDisplayName, 'deleting');

            $this->opimport_msg($resultDeviceDel['message']);

            if ($resultDeviceDel['result']) {
                $this->cleared = true;
            }
        } else {
            $this->opimport_msg("$this->deviceDisplayName | cannot be deleted because it doesn't exists");
        }
    }

    /**
     * Update current device
     */
    public function editDevice()
    {

        if ($this->debug) {
            $this->opimport_dump('update device', $this->paramsDevice);
        }
        $responseDeviceEdit = ApiMain::dispatcher('UpdateDeviceDetails', $this->paramsDevice);
        $resultDeviceEdit = $this->handleCommonResponse($responseDeviceEdit, $this->deviceDisplayName, 'update');
        $this->opimport_msg($resultDeviceEdit['message']);
    }

    /**
     * Discover interfaces for current device
     */
    public function discoverInterfaces()
    {
        $paramsDI = array(
            'adminStates' => '$SelectAll$',
            'devicesList' => $this->deviceName,
            'intftypes' => '$SelectAll$',
            'operStates' => '$SelectAll$'
        );

        if ($this->debug) {
            $this->opimport_dump('discover interfaces', $paramsDI);
        }
        $responseDiscoverInterface = ApiMain::dispatcher('discoverInterface', $paramsDI);

        $resultDiscoverInterface = $this->handleCommonResponse($responseDiscoverInterface, $this->deviceDisplayName, 'discover interfaces');

        $this->opimport_msg($resultDiscoverInterface['message']);
    }

    /**
     * Add a business view for current device
     * @param $bv_name
     * @return bool
     */
    public function addBusinessView($bv_name)
    {
        // if view doesn't exist we add it
        if (!in_array($bv_name, $this->businessViews)) {
            $paramasAddBV = array('bvName' => $bv_name);
            if ($this->debug) {
                $this->opimport_dump('add new business view', $paramasAddBV);
            }
            $responseAddBusinessView = ApiMain::dispatcher('addBusinessView', $paramasAddBV);


            if (!$responseAddBusinessView || is_null($responseAddBusinessView)) {
                $this->opimport_msg("$this->deviceDisplayName | no response when adding business view $bv_name ", "error");
                return false;
            }
            if (is_object($responseAddBusinessView) && isset($responseAddBusinessView->error)) {

                if (isset($responseAddBusinessView->error->code) && isset($responseAddBusinessView->error->message)) {
                    // business view already exists
                    if ($responseAddBusinessView->error->code == 5156 || preg_match('/already exists/i', $responseAddBusinessView->error->message)) {
                        $this->businessViews[] = $bv_name;
                    } else {
                        $this->opimport_msg("$this->deviceDisplayName | cannot create business view $bv_name", "error");
                        $this->opimport_msg("$this->deviceDisplayName | error code: " . $responseAddBusinessView->error->code . ', error message: ' . $responseAddBusinessView->error->message, "error");
                        return false;
                    }
                } else {
                    $this->opimport_msg("$this->deviceDisplayName | empty response when creating the business view $bv_name", "error");
                    return false;
                }

            } else {
                $this->businessViews[] = $bv_name;

            }

        }

        $this->businessViewToDev[$bv_name][] = $this->deviceName;

        return true;

    }

    /**
     * Associate business view to devices
     */
    public function associateBusinessViewsToDevices()
    {

        if (!empty($this->businessViewToDev)) {
            foreach ($this->businessViewToDev as $bv_name => $devices) {

                $businessDetailsViews = ApiMain::dispatcher('getBusinessDetailsView', array('bvName' => $bv_name));

                $ipBV = array();

                if (!empty($businessDetailsViews->BusinessDetailsView->Details)) {
                    foreach ($businessDetailsViews->BusinessDetailsView->Details as $devBv) {
                        $ipBV[] = $devBv->name;
                    }
                }


                $devices = array_diff($devices, $ipBV);


                if (!empty($devices)) {
                    $paramsAddDeviceToBV = array('deviceName' => implode(',', $devices), 'bvName' => $bv_name);
                    if ($this->debug) {
                        $this->opimport_dump('associate business view to device', $paramsAddDeviceToBV);
                    }
                    $responseAddDeviceToBV = ApiMain::dispatcher('addDeviceToBV', $paramsAddDeviceToBV);
                    $resultAddDeviceToBV = $this->handleCommonResponse($responseAddDeviceToBV, "group $bv_name", "add to business view $bv_name");


                    if ($resultAddDeviceToBV['error_code'] == '5132' || preg_match('/already exists/i', $resultAddDeviceToBV['error_message'])) {
                        $this->opimport_msg("group $bv_name | �device already exists in business view $bv_name ");
                    } else {
                        $this->opimport_msg($resultAddDeviceToBV['message']);
                    }
                } else {
                    $this->opimport_msg("group $bv_name | all devices already exists in the view $bv_name");
                }

            }
        }
        return true;
    }

    /**
     * Executes the import
     * @return bool
     */
    public function run()
    {

        foreach ($this->lines as $_line) {
            // parser line
            $line = str_getcsv($_line);

            // flags
            $this->cleared = false;
            $this->failAdd = false;


            $this->deviceDisplayName = isset($line[OPIMPORT_COL_DISPLAYNAME]) ? trim($line[OPIMPORT_COL_DISPLAYNAME]) : '';
            $this->deviceIp = isset($line[OPIMPORT_COL_IP]) ? trim($line[OPIMPORT_COL_IP]) : '';


            // validate
            if (!$this->validate_ip($this->deviceIp)) {
                $this->opimport_msg("the ip: $this->deviceIp from device $this->deviceDisplayName is not valid","error");
                continue;
            }


            // if invalid name we relay on ip
            if (!$this->deviceDisplayName) {
                $this->deviceDisplayName = $this->deviceIp;
            }

            // get  business view for this device
            $deviceBusinessView = array();
            if (isset($line[OPIMPORT_COL_BUSINESSVIEW])) {
                $deviceBusinessView = array_filter(explode(';', trim($line[OPIMPORT_COL_BUSINESSVIEW])));
            }
            // get category for this device
            $deviceCategory = OPIMPORT_DEFAULT_CATEGORY;
            if (isset($line[OPIMPORT_COL_CATEGORY]) && trim($line[OPIMPORT_COL_CATEGORY]) != "") {
                $deviceCategory = $line[OPIMPORT_COL_CATEGORY];
            }

            // get type for this device
            $deviceType = OPIMPORT_DEFAULT_TYPE;
            if (isset($line[OPIMPORT_COL_TYPE]) && trim($line[OPIMPORT_COL_TYPE]) != "") {
                $deviceType = $line[OPIMPORT_COL_TYPE];
            }

            // get network mask for this device
            $deviceNetmask = OPIMPORT_DEFAULT_NETMASK;
            if (isset($line[OPIMPORT_COL_NETMASK]) && trim($line[OPIMPORT_COL_NETMASK]) != "") {
                $deviceNetmask = $line[OPIMPORT_COL_NETMASK];
            }

            // get the monitoring interval for this device
            $deviceMonitoring = OPIMPORT_DEFAULT_MONITORING;
            if (isset($line[OPIMPORT_COL_MONITORING]) && trim($line[OPIMPORT_COL_MONITORING]) != "") {
                $deviceMonitoring = $line[OPIMPORT_COL_MONITORING];
            }

            // declare the name of the device on opmanager
            $this->deviceName = null;

            // parameters for the device
            $this->paramsDevice = array();

            $this->paramsDevice['ipAddress'] = $this->deviceIp; //required on edit
            $this->paramsDevice['deviceName'] = $this->deviceIp; //required on add
            $this->paramsDevice['displayName'] = $this->deviceDisplayName; //required on both

            // set network mask
            if ($deviceNetmask) {
                //processed on add
                //processed on edit
                //required if ipv6
                $this->paramsDevice['netmask'] = $deviceNetmask;
            }

            // set device monitoring
            if ($deviceMonitoring) {
                //processed on edit
                $this->paramsDevice['Monitoring'] = $deviceMonitoring;
            }

            // set device category
            if ($deviceCategory) {
                //processed on edit
                $this->paramsDevice['category'] = $deviceCategory;
            }

            // set device type
            if ($deviceType) {
                //processed on add
                //processed on edit
                $this->paramsDevice['type'] = $deviceType;
            }

            // if discovering or adding then we needed to specify credentials
            if ($this->syncMode['discoverInterfaces'] || $this->syncMode['add']) {
                $this->paramsDevice['credentialName'] = implode(',', $this->credentialName);
            }




            // if the device must be deleted
            if ($this->syncMode['clear']) {
                $this->deleteDevice();
            }


            // if the devices must be added and the attempt failed then go to next item
            if ($this->syncMode['add'] && $this->addDevice() === false) {
                continue;
            }


            // if any of these operations is needed then we must get the device name from opmanager
            if ($this->syncMode['discoverInterfaces'] || $this->syncMode['edit'] || $this->syncMode['addToCustomBusinessView']) {


                if (in_array($this->deviceIP, $this->devicesExist)) { // if the device is in the list of existing devices
                    $this->deviceName = array_search($this->deviceIP, $this->devicesExist);
                } else { // search the device

                    $responseSearch = ApiMain::dispatcher('doSearch', array('type' => 'DEVICE', 'searchString' => $this->deviceIp));

                    if (is_array($responseSearch) && !empty($responseSearch)) { // we have a valid search response
                        foreach ($responseSearch as $deviceSearch) {
                            if ($deviceSearch->IpAddress == $this->deviceIp) {
                                // now we are sure that the device name is correct
                                $this->deviceName = $deviceSearch->deviceName;
                                break;

                            }
                        }
                    }

                    if(!$this->deviceName){
                        $this->opimport_msg(sprintf("%s cannot resolve the device name",$this->deviceIP),"error");
                        continue;
                    }
                }


            }

            // do discover if needed
            if ($this->syncMode['discoverInterfaces']) {
                $this->discoverInterfaces();
            }

            // do edit if needed
            if ($this->syncMode['edit']) {

                // set the name as in opmanager
                $this->paramsDevice['name'] = $this->deviceName;

                // set always the vendor
                if (isset($line[OPIMPORT_COL_VENDOR]) && trim($line[OPIMPORT_COL_VENDOR]) != '') {
                    $this->paramsDevice['vendor'] = ucfirst($line[OPIMPORT_COL_VENDOR]);
                } else {
                    $this->paramsDevice['vendor'] = 'Unknown';//OpManager >= 12 needs this property always set
                }

                // set always the type
                if (!isset($this->paramsDevice['type']) || is_null($this->paramsDevice['type'])) {
                    $this->paramsDevice['type'] = '';//OpManager >=12 needs this property always set
                }

                // now edit it
                $this->editDevice();

            }

            // do add business view if needed
            if ($this->syncMode['addToCustomBusinessView'] && !empty($deviceBusinessView) && is_array($deviceBusinessView)) {
                foreach ($deviceBusinessView as $bv_name) {
                    // add the view
                    $this->addBusinessView($bv_name);
                }
            }

        } // end process each line


        // finally process the views with devices
        $this->associateBusinessViewsToDevices();

        return true;

    }

    function opimport_msg($str, $level = 'info')
    {
        switch ($level) {
            case 'info':
                $preffix = "\033[32m[INFO]\033[0m";
                break;
            case 'error':
                $preffix = "\033[31m[ERROR]\033[0m";
                break;
            default:
                $preffix = "";
                break;

        }
        //remove new lines
        $str = str_replace("\n", "", $str);
        echo "$preffix $str\n";
    }

    function opimport_dump($type, $vars)
    {
        $jsonvars = json_encode($vars);
        echo "\033[33m[DEBUG]\033[0m $type variables: $jsonvars\n";
    }

    function opimport_usage()
    {
        echo "\n";
        echo "Descripcion:\nEsta herramientra agrega nodos(dispositivos)al OpManager.\n\n";
        echo "Lista de parametros: \n";
        echo "\t--type    Tipo de entrada acepta los valores 'file' o 'input'\n";
        echo "\t--file    Ubicacion del archivo que desea procesar\n";
        echo "\t--row    Fila a procesar, puede especificar este parametro mas de 1 vez\n";
        echo "\t--debug  Mostrar los parametros al procesar un dispositivo\n";
        echo "Opciones para el flujo de la importacion, si no especifica una se preguntara por ella: \n";
        echo "\t--del    Desea eliminar el dispositivo existente antes de agregar\n";
        echo "\t--add    Desea agregar el dispositivo\n";
        echo "\t--upd    Desea actualizar propiedades del dispositivo\n";
        echo "\t--dis    Especifica si se debe descubir interfaces del disposirvo\n";
        echo "\t--bv    Especifica si se debe agregar cada dispositivo a  business view\n";


        echo "\nEstructura del archivo:\n";
        echo "\tIp,Nombre,Marca,Vista(s),Categoria,Tipo,Monitoring,Netmask \n";
        echo "\t127.0.0.1,localhost,Windows 8,VISTA1;VISTA2,MiCategoria,Mitipo,5,255.255.255.0\n";
        echo "\t192.168.1.1,gestornap,Cisco,VISTA3,Server,Mitipo2,15,255.255.255.0\n";


        echo "\nEjemplos:\n";
        echo "\t\n\nAgregar mediante archivo:\n\n";
        echo "\t" . 'php ' . basename(__FILE__) . ' --type=file --file=../archivo.csv' . "\n";

        echo "\t\n\nAgregar mediante archivo y especificando opciones:\n\n";
        echo "\t" . 'php ' . basename(__FILE__) . ' --type=file --file=../archivo.csv --del=no --add=si --upd=si --dis=si --bv=no' . "\n";


        echo "\n\nAgregar especificando fila:\n\n";
        echo "\t" . 'php ' . basename(__FILE__) . ' --type=input --row="127.0.0.1,localhost,Windows 8,VISTA1;VISTA2,MiCategoria,Mitipo,5,255.255.255.0" --row="192.168.1.1,gestornap,Cisco,VISTA3,Server,Mitipo2,15,255.255.255.0"' . "\n";
    }

}