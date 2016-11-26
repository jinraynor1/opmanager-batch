<?php
namespace Jinraynor1\OpManager\Batch\Monitors;

/**
 * Trace historic values of snmp
 * Class MonitorTracker
 * @package Jinraynor1\OpManager\Batch\Monitors
 */
class MonitorTracker
{

    protected $db;
    private $snmp_historic_from;


    public function __construct($config)
    {


        //inicializar db
        $this->db = new \SQLite3($config['database'], SQLITE3_OPEN_READWRITE) or die('No se pudo abrir la base de datos sqlite');

        //concurrencia db
        $this->db->busyTimeout($config['busyTimeout']);
        $this->db->exec('PRAGMA journal_mode = wal;');// WAL mode: https://www.sqlite.org/wal.html

        //dias a mantenar historico de poleo
        //snmp_historic_from
        $this->snmp_hour = $config['snmp_hour'];
        $this->snmp_historic_from = $config['snmp_historic_from'];

    }


    public function refresh($deviceName)
    {

        return $this->db->exec("DELETE FROM device_snmp_historic WHERE date < '{$this->snmp_historic_from}' AND device='{$deviceName}'");
    }

    public function save($deviceName, $current_monitor_oid, $snmp_integer)
    {
        $query_historic = "INSERT INTO device_snmp_historic(device,date,oid,value) VALUES ( '$deviceName', '$this->snmp_hour','$current_monitor_oid','$snmp_integer' )";
        return $this->db->exec($query_historic);

    }

    public function get($deviceName, $current_monitor_oid)
    {
        // consultar ultimo historico de valores snmp para determinar si estuvo activo
        $query_check_historic = "SELECT  value FROM device_snmp_historic WHERE device='$deviceName'
        AND oid='$current_monitor_oid' AND date >'$this->snmp_historic_from'   AND VALUE>0 ORDER BY date DESC limit 1 ";

        return $this->db->querySingle($query_check_historic);
    }

    public function getError()
    {
        return $this->db->lastErrorMsg();
    }


}