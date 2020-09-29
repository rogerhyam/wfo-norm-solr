<?php

require_once('config.php');
require_once('curl_functions.php');
require_once('solr_functions.php');

$path_parts = explode('/', $_SERVER["REQUEST_URI"]);

// path should be of the form /wfo-id/format

// FIXME: different renderings
// FIXME: work with .htaccess

array_shift($path_parts); // lose the first blank one

if(count($path_parts) < 1){
    echo "This is the welcome page\n";
    exit;
}

// first argument is always the wfo-id. It may be qualified with a date or not.
if(preg_match('/^wfo-[0-9]{10}$/', $path_parts[0])){
    $wfo_root_id = $path_parts[0];
    $wfo_qualified_id = null;
    $version_id = null;
}else if(preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $path_parts[0])){
    $wfo_qualified_id = $path_parts[0];
    $wfo_root_id = substr($wfo_qualified_id, 0, 14);
    $version_id = substr($wfo_qualified_id, -7);
}else{
    header("HTTP/1.0 400 Bad Request");
    echo "Unrecognised WFO id format: \"{$path_parts[0]}\"";
    exit;
}

// second argument is the format - or may be missing
$formats = array('json','rdf','html','csv');

if(isset($path_parts[1])){
    if(in_array($path_parts[1], $formats)){
        $format = $path_parts[1];
    }else{
        header("HTTP/1.0 400 Bad Request");
        echo "Unrecognised data format \"{$path_parts[1]}\"";
        exit;
    }
}else{

    // if the format is missing we redirect to the default format
    // always 303 redirect from the core object URIs
    $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                . "://$_SERVER[HTTP_HOST]/"
                . $path_parts[0]
                . '/'
                . $formats[0];
 
                header("Location: $redirect_url",TRUE,303);
                echo "Found: Redirecting to data";
                exit;
}

// now we have enough to make decisions about where to send people

// if there is no version then it is a name and we present 
// the useages to be chosen between.
if(!$version_id){

    $name = (Object)array(
        'uri' => get_uri($wfo_root_id),
        'usages' => array()
    );

    $query = array(
        'query' => 'taxonID_s:' . $wfo_root_id,
        'sort' => 'snapshot_version_s desc'
    );
    $response = json_decode(solr_run_search($query));

    $preferred = true; 
    foreach ($response->response->docs as $usage) {
        $usage->uri = get_uri($usage->id);
        if($usage->taxonomicStatus_s == 'Synonym'){
            $usage->synonym_of_taxon = array('uri' => get_uri($usage->acceptedNameUsageID_s . '-' . $usage->snapshot_version_s));
        }

        // tag the first one as the preferred one
        $usage->preferred = $preferred;
        $preferred = false;

        $name->usages[] = $usage;
    }

    header('Content-Type: application/json');
    echo json_encode($name);
    exit;

}else{

    // we have a versioned taxon id so check it exists then render it
    $taxon = solr_get_doc_by_id($wfo_qualified_id);

    // no taxon
    if(!$taxon){
        header("HTTP/1.0 404 Not Found");
        echo "No taxon was found with id $wfo_qualified_id";
        exit;
    }

    // this version of the taxon is actually a synonym and data for it will be provided in its accepted version's data document
    // so 303 redirect to that
    if($taxon->taxonomicStatus_s == 'Synonym'){

        $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
            . "://$_SERVER[HTTP_HOST]/"
            . $taxon->acceptedNameUsageID_s
            . '-'
            . $taxon->snapshot_version_s
            . '/'
            . $format;

            header("Location: $redirect_url",TRUE,303);
            echo "Synonymous taxon. Redirecting to accepted taxon $taxon->acceptedNameUsageID_s";
            exit;

    }

    // we got to here and have an accepted taxon.
    // lets build it into a complete object then call a renderer.

    // add the full uri
    $taxon->uri = get_uri($taxon->id);

    // Link out to other taxonomies
    // get a list in descending order
    $query = array(
        'query' => 'taxonID_s:' . $wfo_root_id,
        'sort' => 'snapshot_version_s desc'
    );
    $response = json_decode(solr_run_search($query));
    if($response->response->numFound > 1){

        // work through the list to find self
        for ($i=0; $i < count($response->response->docs) ; $i++) {
            $v = $response->response->docs[$i];
            if($v->snapshot_version_s == $taxon->snapshot_version_s){
                $my_index = $i;
            }
        }

        // is there a newer version?
        if($my_index-1 >= 0){
            // it is replaced by something. But what?
            // if a taxon has been sunk into synonymy then it isn't replaced by the 
            // synonym it is replaced by the accepted taxon
            $replacement = $response->response->docs[$my_index-1];
            if($replacement->taxonomicStatus_s == 'Synonym'){
                $taxon->isReplacedBy = array( 'uri' => get_uri($replacement->acceptedNameUsageID_s . '-'. $replacement->snapshot_version_s));
            }else{
                $taxon->isReplacedBy = array( 'uri' => get_uri($replacement->id));
            }
        }

        // is there an older version
        if($my_index+1 < count($response->response->docs)){

            // it replaces something. But what?
            // if a NAME has been raised from synonym to being a full TAXON
            // then it doesn't replace the synyonyms NAME in the previous version
            $theReplaced = $response->response->docs[$my_index+1];
            
            if($theReplaced->taxonomicStatus_s == 'Synonym'){
                // tricky situation. Accepted taxon is errected from previous synonym
                // this taxon is proparte synonym of whatever the accepted taxon was but it is a taxon-taxon relationship
                // Could be "errected from" - split from 
                $taxon->derivedFrom = array( 'uri' => get_uri($theReplaced->acceptedNameUsageID_s . '-'. $theReplaced->snapshot_version_s));
            }else{                    
                // easy case. Accepted replaces old version of accepted.
                // this also covers other possible taxonomic statuses like Unknown status.
                $taxon->replaces = array( 'uri' => get_uri($theReplaced->id)) ;
            }
            
        }

    }

    // add parent taxon
    $parent = solr_get_doc_by_id($taxon->parentNameUsageID_s . '-' . $taxon->snapshot_version_s);
    if($parent){
        $taxon->parent_taxon = array(
            'uri' => get_uri($parent->id)
        );
    }
    
    // add child taxa
    $query = array(
        'query' => 'parentNameUsageID_s:' . $wfo_root_id,
        'filter' => 'snapshot_version_s:' . $taxon->snapshot_version_s,
        'fields' => 'id',
        'limit' => 1000000,
        'sort' => 'id asc'
    );
    $response = json_decode(solr_run_search($query));
    if($response->response->numFound > 0){
        foreach ($response->response->docs as $kid) {
            $taxon->child_taxa[] = array( 'uri' => get_uri($kid->id));
        }
    }else{
        $taxon->child_taxa = array();
    }

    // add synonymous taxa

    // FIXME: these are actually names and should have links to names.

    $query = array(
        'query' => 'acceptedNameUsageID_s:' . $wfo_root_id,
        'filter' => 'snapshot_version_s:' . $taxon->snapshot_version_s,
        'limit' => 1000000,
        'sort' => 'id asc'
    );
    $response = json_decode(solr_run_search($query));
    $taxon->synonyms = array();
    if($response->response->numFound > 0){
        foreach ($response->response->docs as $syn){
            $syn->uri = get_uri($syn->id);
            $taxon->synonyms[] = $syn;
        }   
    }

    header('Content-Type: application/json');
    echo json_encode($taxon);
    exit;
    //print_r($taxon);

}


function get_uri($taxon_id){
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $taxon_id;
}


?>
