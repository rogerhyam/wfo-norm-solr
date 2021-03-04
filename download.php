<?php

require_once('config.php');
require_once('include/curl_functions.php');
require_once('include/solr_functions.php');
require_once('include/functions.php');
require_once('include/download_functions.php');

/*

Allows download of a Darwin Core Archive of taxa at Genus and above.

- with caching to enable performance for bigger genera.

// work through all the accepted genera and generate CSV files for them

*/

// do we have a taxon id
if(php_sapi_name() !== 'cli'){
    $taxon_id = @$_GET['taxon_id'];
}else{
    $ops = getopt('t:');
    if(isset($ops['t']) && $ops['t']){
        $taxon_id = $ops['t']; 
    }else{
        echo "You must set a taxon id with the -t option. \n";
        exit;
    }
}

// good taxon id?
if(!preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $taxon_id)){
    http_response_code(400);
    echo "Bad request. Unrecognised taxon id format: $taxon_id";
    exit;
}

$taxon = solr_get_doc_by_id($taxon_id);

// do we have a taxon
if(!$taxon){
    http_response_code(404);
    echo "Not taxon found with id: $taxon_id";
    exit;
}

// is the taxon an accepted one?
if(strtolower($taxon->taxonomicStatus_s) != 'accepted'){
    http_response_code(400);
    echo "Bad request. Downloads only available for accepted taxa.";
    exit;
}

// is it of the correct rank?
$accepted_ranks = array("genus", "family", "order");
if( !in_array(strtolower($taxon->taxonRank_s), $accepted_ranks) ){
    http_response_code(400);
    echo "Bad request. Downloads only available for ranks: " . implode(', ', $accepted_ranks);
    exit;
}

// Looking good 
process_taxon($taxon);
