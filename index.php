<?php

// for dev environment we do the job of .htaccess 
if(preg_match('/^\/gql.php/', $_SERVER["REQUEST_URI"])) return false;
if(preg_match('/^\/gql/', $_SERVER["REQUEST_URI"])){
    include('gql.php');
    exit;
};

require_once('config.php');
require_once('include/curl_functions.php');
require_once('include/solr_functions.php');
require_once('include/functions.php');


// path should be of the form /wfo-id/format or /terms/
$path_parts = explode('/', $_SERVER["REQUEST_URI"]);
array_shift($path_parts); // lose the first blank one

// do the welcome page if there is not id
if(strlen($path_parts[0]) == 0){
    include('welcome.php');
    exit;
}

$format = get_format($path_parts);

// first argument is blank or the wfo-id.
// It may be qualified with a date or not.
if(preg_match('/^wfo-[0-9]{10}$/', $path_parts[0])){
    // were were passed a name id
    $wfo_root_id = $path_parts[0];
    $wfo_qualified_id = null;
    $version_id = null;
}else if(preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $path_parts[0])){
    // we were passed a taxon id
    $wfo_qualified_id = $path_parts[0];
    $wfo_root_id = substr($wfo_qualified_id, 0, 14);
    $version_id = substr($wfo_qualified_id, -7);
}else if(preg_match('/^terms$/', $path_parts[0])){
    // they are looking for a term in the vocabulary
    include('terms.php');
    exit;
}else{
    header("HTTP/1.0 400 Bad Request");
    echo "Unrecognised WFO ID format: \"{$path_parts[0]}\"";
    exit;
}


// now we have enough to make decisions about where to send people

// if there is no version then it is a name and we present 
// the useages to be chosen between.
if(!$version_id){

    $graph = new \EasyRdf\Graph();
    $name = getTaxonNameResource($graph, $wfo_root_id);
    output($graph, $format);

}else{

    // we have a versioned taxon id so check it exists then render it
    $taxon_solr = solr_get_doc_by_id($wfo_qualified_id);

    // no taxon
    if(!$taxon_solr){
        header("HTTP/1.0 404 Not Found");
        echo "No taxon was found with id $wfo_qualified_id";
        exit;
    }

    // It should be impossible to request a versioned synonym
    // if a wfo "taxon" is sunk it isn't a taxon it is a name.
    // we wouldn't publish these links or expect people to arrive here
    // but just in case.
    if($taxon_solr->taxonomicStatus_s == 'Synonym'){

        $redirect_url = get_uri($taxon_solr->acceptedNameUsageID_s . '-' . $taxon_solr->snapshot_version_s);
        header("Location: $redirect_url",TRUE,303);
        echo "See Other: The accepted taxon for this name";
        exit;
    }
    
    // Let's start building.
    $taxon_uri = get_uri($taxon_solr->id);

    $graph = new \EasyRdf\Graph();
    $taxon_rdf = $graph->resource($taxon_uri, 'wfo:TaxonConcept');

    // nice link the human readable web page

  
    // taxonomic status mixes synonymy and editorial so make it pure!
    if(!preg_match('/Synonym/', $taxon_solr->taxonomicStatus_s)){
        $taxon_rdf->set('wfo:editorialStatus', $taxon_solr->taxonomicStatus_s);
    }


    
    // Insert the name
    $taxon_rdf->add('wfo:hasName', getTaxonNameResource($graph, $wfo_root_id));

    // Link out to other taxonomies

    $replaces_replaced = get_replaces_replaced($wfo_root_id, $taxon_solr->snapshot_version_s);
    foreach($replaces_replaced as $property => $uri){
        $taxon_rdf->add($property, $graph->resource($uri));
    }
    

    // add parent taxon
    if(isset($taxon_solr->parentNameUsageID_s)){
        $parent = solr_get_doc_by_id($taxon_solr->parentNameUsageID_s . '-' . $taxon_solr->snapshot_version_s);
        if($parent){
            //$taxon_rdf->add('dwc:parentNameUsageID',$graph->resource(get_uri($parent->id)) );
            $taxon_rdf->add('dc:isPartOf',$graph->resource(get_uri($parent->id)) );
        }
    }

    // add child taxa
    $query = array(
        'query' => 'parentNameUsageID_s:' . $wfo_root_id,
        'filter' => 'snapshot_version_s:' . $taxon_solr->snapshot_version_s,
        'fields' => 'id',
        'limit' => 1000000,
        'sort' => 'id asc'
    );
    $response = json_decode(solr_run_search($query));
    if($response->response->numFound > 0){
        foreach ($response->response->docs as $kid) {
            $taxon_rdf->add('dc:hasPart',$graph->resource(get_uri($kid->id)) );
        }
    }

    // add synonymous taxa
    $query = array(
        'query' => 'acceptedNameUsageID_s:' . $wfo_root_id,
        'filter' => 'snapshot_version_s:' . $taxon_solr->snapshot_version_s,
        'limit' => 1000000,
        'sort' => 'id asc'
    );
    $response = json_decode(solr_run_search($query));

    if($response->response->numFound > 0){
        foreach ($response->response->docs as $syn){
//            $syn_uri = get_uri($syn->taxonID_s);
            $taxon_rdf->add('wfo:hasSynonym',  getTaxonNameResource($graph, $syn->taxonID_s) );
        }   
    }

    output($graph, $format);
    //print_r($taxon);
}

function output($graph, $format_string){

    $format = \EasyRdf\Format::getFormat($format_string);

    $serialiserClass  = $format->getSerialiserClass();
    $serialiser = new $serialiserClass();
    
    // if we are using GraphViz then we add some parameters 
    // to make the images nicer
    if(preg_match('/GraphViz/', $serialiserClass)){
        $serialiser->setAttribute('rankdir', 'LR');
    }
    
    $data = $serialiser->serialise($graph, $format_string);

//    $data = $graph->serialise($format);
    
    header('Content-Type: ' . $format->getDefaultMimeType());

    print_r($data);

//    echo $data;
    exit;

}

function getTaxonNameResource($graph, $wfo_root_id){

    $name = $graph->resource(get_uri($wfo_root_id), 'wfo:TaxonName');

    // get the different versions of this taxon
    $query = array(
        'query' => 'taxonID_s:' . $wfo_root_id,
        'sort' => 'snapshot_version_s asc'
    );
    $response = json_decode(solr_run_search($query));


    // FROM HERE - build nomenclatural data from all the records, latest overriding older 
    // data - to handle them dropping info between TENs sources

    $nom_fields = array(
        'taxonRank_s' => 'wfo:rank',
        'scientificName_s' => 'wfo:fullName',
        'scientificNameAuthorship_s' => 'wfo:authorship',
        'family_s' => 'wfo:familyName',
        'genus_s' => 'wfo:genusName',
        'specificEpithet_s' => 'wfo:specificEpithet',
        'namePublishedIn_s' => 'wfo:publicationCitation',
        'namePublishedInID_s' => 'wfo:publicationID',
        'scientificNameID_s' => 'wfo:nameID',
        'originalNameUsageID_s' => 'wfo:hasBasionym'
    );

    $nom_values = array();

    $usages = array();

    $latest_usage = null;
    foreach ($response->response->docs as $usage) {
        
        foreach($nom_fields as $solr_field => $rdf_prop){
            if(isset($usage->{$solr_field})){
                $nom_values[$rdf_prop] = $usage->{$solr_field};
            }
        }

        $usages[] = $usage; 
        $latest_usage = $usage;

    }

    foreach($nom_values as $rdf_prop => $value){

        if($rdf_prop == 'wfo:hasBasionym'){
            $name->add($rdf_prop, $graph->resource(get_uri($value)));
        }elseif($rdf_prop == 'wfo:rank'){
            $rank_name = strtolower($value);
            $name->add($rdf_prop, $graph->resource('wfo:' . $rank_name));
        }else{
            $name->set($rdf_prop, $value);
        }

        
    }

    foreach($usages as $usage){

        // a useage is in a taxon, either as
        if($usage->taxonomicStatus_s == 'Synonym'){
            if(isset($usage->acceptedNameUsageID_s)){
                $name->add('wfo:isSynonymOf', $graph->resource(get_uri($usage->acceptedNameUsageID_s . '-' . $usage->snapshot_version_s )));
            }
        }else{
            $name->add('wfo:acceptedNameFor', $graph->resource(get_uri($usage->id)));
        }

        
    }
    
    // if the most recent usage is accepted we add a link to it as the accepted
    // taxon - breaks pure nomenclator
    if($latest_usage->taxonomicStatus_s == 'Accepted'){
        $name->add('wfo:currentPreferredUsage', $graph->resource(get_uri($usage->id)));
    }

    return $name;

}

function get_format($path_parts){
        
    $format_string = null;
    $formats = \EasyRdf\Format::getFormats();

    // if we don't have any format in URL
    if(count($path_parts) < 2 || strlen($path_parts[1]) < 1){

        // if the format isn't in the path then they will be redirected somewhere

        // try and get it from the http header
        $headers = getallheaders();
        if(isset($headers['Accept'])){
            $mimes = explode(',', $headers['Accept']);
       
            foreach($mimes as $mime){
                foreach($formats as $format){
                    $accepted_mimes = $format->getMimeTypes();
                    foreach($accepted_mimes as $a_mime => $weight){
                        if($a_mime == $mime){
                            $format_string = $format->getName();
                            break;
                        }
                    }
                    if($format_string) break;
                }
                if($format_string) break;
            }
        }

        if(!$format_string){
            // not got a format string so assume HUMAN and send to main website
            $redirect_url = "http://www.worldfloraonline.org/taxon/" . substr($path_parts[0], 0, 14);       
        }else{
            // got a format string so send them to that format
            $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
            . "://$_SERVER[HTTP_HOST]/"
            . $path_parts[0]
            . '/'
            . $format_string;
        }

        // redirect them
        // always 303 redirect from the core object URIs
        header("Location: $redirect_url",TRUE,303);
        echo "Found: Redirecting to data";
        exit;


    }else{

        // we have a format in the url string
        if(in_array($path_parts[1], $formats)){
            $format_string = $path_parts[1];
        }else{
            header("HTTP/1.0 400 Bad Request");
            echo "Unrecognised data format \"{$path_parts[1]}\"";
            exit;
        }

    }

    return $format_string;
    
}



?>
