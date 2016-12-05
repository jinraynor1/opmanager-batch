<?php
require_once __DIR__ . '/../../bootstrap.php';

$color = new \Colors\Color();
$cmd = new Commando\Command();

$cmd->useDefaultHelp();

$cmd->setHelp('Add devices to  OpManager, e.g

        Add by file:
                   --type file --file ../archivo.csv

        Add by file and set default options :
                     --type file --file ../archivo.csv --delete --add --update --discover --business-views

        Add  specifying row:
                    --type input --row "127.0.0.1,localhost,Windows 8,VISTA1;VISTA2,MiCategoria,Mitipo,5,255.255.255.0"

        Sample file:
                    Ip,Name,Vendor,BusinessViews,Category,Type,Monitoring,Netmask
                    127.0.0.1,localhost,Windows 8,VISTA1;VISTA2,MiCategoria,Mitipo,5,255.255.255.0
                    192.168.1.1,gestornap,Cisco,VISTA3,Server,Mitipo2,15,255.255.255.0
                    ');

$cmd->option('type')
    ->require()
    ->must(function ($type) {
        return in_array($type, array('file', 'input'));
    })
    ->describedAs("Type must be one of (file or input)")


    ->option('file')
    ->needs('type')
    ->expectsFile()
    ->describedAs("File location could be absolute or relative")


    ->option('row')
    ->needs('type')
    ->describedAs("String line to process like if it was csv")
    ->option('debug')
    ->boolean()
    ->describedAs("Shows verbose output")


    ->option('delete')
    ->boolean()
    ->describedAs("Delete the device, this is done before adding the file if needed")


    ->option('add')
    ->boolean()
    ->describedAs("Add the device")

    ->option('update')
    ->boolean()
    ->describedAs("Update the device")

    ->option('interfaces')
    ->boolean()
    ->describedAs("Discover interface of the device")

    ->option('business-views')
    ->boolean()
    ->describedAs("Process business view field")

    ->option('threads')
    ->default(1)
    ->must(function ($threads) use ($cmd){
        return ctype_digit($threads);
    })
    ->describedAs("Number of threads for this job");;

// get lines
$lines = array();

// validate required options
if ($cmd['type'] == 'file') {

    if(!$cmd['file']){
        $error = "ERROR: Required option file must be specified";
        echo $color($error)->bg('red')->bold()->white() . PHP_EOL;

        exit(1);
    }

    $lines = file($cmd['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    //remove headers
    array_shift($lines);

    if(empty($lines)){
        $error = "ERROR: No valid lines founded on file";
        echo $color($error)->bg('red')->bold()->white() . PHP_EOL;
        exit(1);
    }

} elseif ($cmd['type'] == 'input') {

    if(!$cmd['row']){
        $error = "ERROR: Required option row must be specified";
        echo $color($error)->bg('red')->bold()->white() . PHP_EOL;
        exit(1);
    }
    if (!is_array($cmd['row'])) {
        $rows = array($cmd['row']);
    }
    $lines = $rows;
}

// build options
$options = array(
    'debug' => $cmd['debug'],
    'delete' => $cmd['delete'],
    'add' => $cmd['add'],
    'interfaces' => $cmd['interfaces'],
    'update' => $cmd['update'],
    'business-views' => $cmd['business-views'],
);


try {
    $obj = new Jinraynor1\OpManager\Batch\SupplyDevice($lines, $options);
    $obj->run();
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
