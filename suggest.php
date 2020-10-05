<?php

require_once('config.php');
require_once('curl_functions.php');
require_once('solr_functions.php');

// if we don't have a query string we just render the test form
if(!isset($_GET['q'])){
    include('suggest_test_form.php');
    exit;
}

if(!$_GET['q']){
    header('Content-Type: application/json');
    echo json_encode(array());
}


$parts = explode(' ', $_GET['q']);
$parts = array_map(function($pat) { return trim($pat) . '*' ; }, $parts);
$q = implode(' ', $parts);

// build a query
$query = array(
    'query' => "scientificName_s:$q OR genus_s:$q^10 OR specificEpithet_s:$q",
    'filter' => 'snapshot_version_s:' . WFO_DEFAULT_VERSION,
    'limit' => 30
);

$response = json_decode(solr_run_search($query));

$results = array();

// for highlighting
$patterns = explode(' ', $_GET['q']);
$highlighted = array_map(function($pat) { return '{$1}'; }, $patterns);
$patterns = array_map(function($pat) { return "/($pat)/i"; }, $patterns);


if(isset($response->response->docs)){

    for ($i=0; $i < count($response->response->docs); $i++) { 
    
        $doc = $response->response->docs[$i];

        // if it is a synonym we replace it with the accepted taxon
        if($doc->taxonomicStatus_s == 'Synonym'){
            $syn = $doc;
            $doc = solr_get_doc_by_id($syn->acceptedNameUsageID_s . '-' . $syn->snapshot_version_s);
            $doc->synonym_found = $syn;

            // each search result has a unique id
            // we can't use the id of the accepted taxon because it might be returned 
            // multiple times if it has multiple specimens
            $doc->suggest_result_id = $syn->id; 

            $display = $doc->scientificName_s
                . ' '
                . (isset($doc->scientificNameAuthorship_s) ? $doc->scientificNameAuthorship_s : '') 
                . ' [syn: ' . $syn->scientificName_s .  ' '
                . (isset($syn->scientificNameAuthorship_s) ? $syn->scientificNameAuthorship_s : '') 
                . ']';
        }else{

            $display = $doc->scientificName_s  . ' '. (isset($doc->scientificNameAuthorship_s) ? $doc->scientificNameAuthorship_s : '');

            if($doc->taxonomicStatus_s != 'Accepted'){
                $display .= " [{$doc->taxonomicStatus_s}]";
            }

            // accepted taxa are only returned once
            $doc->suggest_result_id = $doc->id; 

        }

//$doc->patterns = $patterns;
//$doc->highlighted = $highlighted;
//$doc->search_display_no_highlight = $display;
        $doc->search_display = preg_replace($patterns, $highlighted, $display);
        $doc->search_display = str_replace('{', '<strong>', $doc->search_display);
        $doc->search_display = str_replace('}', '</strong>', $doc->search_display);

        
        $results[] = $doc;

    }

}




header('Content-Type: application/json');
echo json_encode($results);

?>