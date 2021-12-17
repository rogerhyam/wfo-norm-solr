<?php
require_once('config.php');
require_once('include/curl_functions.php');
require_once('include/solr_functions.php');
require_once('include/functions.php');
require_once('include/download_functions.php');

/*
    Will generate a cache of all the orders, families and genera in a version
*/

if(php_sapi_name() !== 'cli'){
    echo "Command line only!\n";
    exit;
}

$ops = getopt('v:');

// have we got a version
if(isset($ops['v']) && $ops['v']){
    $version = $ops['v']; 
}else{
    echo "You must set a version with the -v option. e.g. 2019-05 \n";
    exit;
}

generate_archives_for('ORDER', $version);
generate_archives_for('FAMILY', $version);
generate_archives_for('GENUS', $version);

function generate_archives_for($rank, $version){

    // go for orders
    $query = array(
        'query' => 'taxonRank_s:' . $rank, 
        'filter' => array(
            'snapshot_version_s:' . $version,
            'taxonomicStatus_s:Accepted'
        ),
        'limit' => 1000000,
        'sort' => 'scientificName_s asc'
    );
    print_r($query);

    $response = json_decode(solr_run_search($query));


    if($response->response->numFound > 0){
        foreach ($response->response->docs as $taxon){
            echo $taxon->scientificName_s;
            echo "\n";
            process_taxon($taxon);
        }   
    }

}


