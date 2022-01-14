<?php

header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ignore_user_abort(true);
set_time_limit(0);

ini_set('display_errors','On');
ini_set('memory_limit','128M');
ini_set('max_execution_time','0');
ini_set('max_input_time','0');

require_once 'classes/TMPPro.php';

$TMPPro = new \TMPPro\TMPPro(
    'username',
    'password',
    'https://tmppro.com/logmein',
    'https://tmppro.com/build_and_compare/download_xml',
    'Download Master XML product file',
    'TMPProDownloaded',
    'TMPProCookie',
    'cookie.txt'
);

try {
    $TMPPro->removeOldZipAndXml();
    $TMPPro->downloadXml();
}
catch (Exception $e) {
    echo 'TMPPro exception: ' . $e->getMessage();
}

?>