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
if(strlen($path_parts[0]) == 'index.php'){
    include('welcome.php');
    exit;
}

//echo $_SERVER["REQUEST_URI"]; /
//print_r($path_parts);
//exit;

$format = get_format($path_parts); // this redirects humans

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
}else if(preg_match('/^[0-9]{4}-[0-9]{2}$/', $path_parts[0])){
    $wfo_qualified_id = null;
    $wfo_root_id = null;
    $version_id = $path_parts[0];
}else if(preg_match('/^terms$/', $path_parts[0])){
    // they are looking for a term in the vocabulary
    include('terms.php');
    exit;
}else{
    header("HTTP/1.0 400 Bad Request");
    echo "Unrecognized WFO ID format: \"{$path_parts[0]}\"";
    exit;
}


// now we have enough to make decisions about where to send people

if($version_id && !$wfo_root_id && !$wfo_qualified_id){

    // we only have a version id so we are presenting a
    // classification object
    $graph = new \EasyRdf\Graph();
    $classification = $graph->resource(get_uri($version_id), 'wfo:Classification');
    $parts = explode('-', $version_id);
    $classification->set('wfo:month', $parts[0]);
    $classification->set('wfo:year', $parts[1]);
    output($graph, $format);

}else if(!$version_id && $wfo_root_id && !$wfo_qualified_id){

    // we have a root taxon id but no version so we 
    // are presenting just a name    
    $graph = new \EasyRdf\Graph();
    $name = getTaxonNameResource($graph, $wfo_root_id);
    output($graph, $format);

}else if($wfo_qualified_id){

    // we have a versioned taxon id so check it exists then render it
    $taxon_solr = solr_get_doc_by_id($wfo_qualified_id);

    // no taxon
    if(!$taxon_solr){
        header("HTTP/1.0 404 Not Found");
        echo "No taxon was found with id $wfo_qualified_id";
        exit;
    }

    // Let's start building.
    $taxon_uri = get_uri($taxon_solr->id);

    $graph = new \EasyRdf\Graph();
    $taxon_rdf = $graph->resource($taxon_uri, 'wfo:TaxonConcept');

    // nice link the human readable web page

    // taxonomic status mixes synonymy and editorial so make it pure!
    if(!preg_match('/Synonym/', $taxon_solr->role_s)){
        $taxon_rdf->set('wfo:editorialStatus', $taxon_solr->role_s);
    }

    // Insert the name
    $taxon_rdf->add('wfo:hasName', getTaxonNameResource($graph, $wfo_root_id));

    // link it to the classification
    $taxon_rdf->add('wfo:classification', $graph->resource(get_uri($version_id)) );

    // Link out to other taxonomies
    $replaces_replaced = get_replaces_replaced($taxon_solr->wfo_id_s, $version_id);
    foreach($replaces_replaced as $property => $uri){
        $taxon_rdf->add($property, $graph->resource($uri));
    }
    
    // add parent taxon
    if(isset($taxon_solr->parent_id_s)){
        $parent = solr_get_doc_by_id($taxon_solr->parent_id_s);
        if($parent){
            //$taxon_rdf->add('dwc:parentNameUsageID',$graph->resource(get_uri($parent->id)) );
            $taxon_rdf->add('dc:isPartOf',$graph->resource(get_uri($parent->id)) );
        }
    }

    // add child taxa
    $query = array(
        'query' => 'parent_id_s:' . $taxon_solr->id,
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
        'query' => 'acceptedNameUsageID_s:' . $taxon_solr->id,
        'limit' => 1000000,
        'sort' => 'classification_id_s asc'
    );
    $response = json_decode(solr_run_search($query));

    if($response->response->numFound > 0){
        foreach ($response->response->docs as $syn){
            $taxon_rdf->add('wfo:hasSynonym',  getTaxonNameResource($graph, $syn->id) );
        }   
    }

    output($graph, $format);
    //print_r($taxon);

}else{
    http_response_code(400);
    echo "Error: invalid combination of values";
    exit;
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

function getTaxonNameResource($graph, $wfo_id){

    $name = $graph->resource(get_uri($wfo_id), 'wfo:TaxonName');

    // the name is always the latest version we have in the index.
    $solr_doc = solr_get_doc_by_id($wfo_id . '-' . WFO_DEFAULT_VERSION);

    // fields we will use
    $nom_fields = array(
        'rank_s' => 'wfo:rank',
        'full_name_string_plain_s' => 'wfo:fullName',
        'authors_string_s' => 'wfo:authorship',
        'authors_string_s' => 'dc:creator',
        'placed_in_family_s' => 'wfo:familyName',
        'placed_in_genus_s' => 'wfo:genusName',
        'species_string_s' => 'wfo:specificEpithet',
        'citation_micro_s' => 'wfo:publicationCitation',
        'wfo_id_s' => 'wfo:nameID',
        'basionym_id_s' => 'wfo:hasBasionym'
    );

    $nom_values = array();
    foreach($nom_fields as  $solr_field => $rdf_prop){
        if(isset($solr_doc->{$solr_field})){
            $nom_values[$rdf_prop] = $solr_doc->{$solr_field};
        }
    }

    // add the mapped values to the name graph doc
    foreach($nom_values as $rdf_prop => $value){

        if($rdf_prop == 'wfo:hasBasionym'){
            $name->add($rdf_prop, $graph->resource(get_uri($value)));
        }elseif($rdf_prop == 'wfo:rank'){
            $rank_name = strtolower($value);
            $name->add($rdf_prop, $graph->resource('wfo:' . $rank_name));
        }else{
            if(is_array($value)){
                foreach ($value as $v) {
                    if(filter_var($v, FILTER_VALIDATE_URL)){
                        $name->add($rdf_prop, $graph->resource($v));
                    }else{
                        $name->add($rdf_prop, $v);
                    }
                }
            }else{
                $name->set($rdf_prop, $value);
            }
            
        }
    }

    // add a list of usages of this name
    $query = array(
        'query' => 'wfo_id_s:' . $wfo_id,
        'sort' => 'classification_id_s asc'
    );
    $response = json_decode(solr_run_search($query));

    // create a map of the latest values
    $latest_usage = null;
    $usages = array();
    foreach ($response->response->docs as $usage) {
        $usages[] = $usage; 
        $latest_usage = $usage;
    }

    foreach($usages as $usage){

        // a usage is in a taxon, either as
        if($usage->role_s == 'synonym'){
            if(isset($usage->accepted_id_s)){
                $name->add('wfo:isSynonymOf', $graph->resource(get_uri($usage->accepted_id_s)));
            }
        }else{
            $name->add('wfo:acceptedNameFor', $graph->resource(get_uri($usage->id)));
        }
        
    }

    // the last usage (they are in snapshot order) is the preferred usage
    if($usages){
        $usage = end($usages);
        if($usage->role_s == 'synonym'){
            if(isset($usage->acceptedNameUsageID_s)){
                $name->add('wfo:currentPreferredUsage', $graph->resource(get_uri($usage->accepted_id_s)));
            }
        }else{
            $name->add('wfo:currentPreferredUsage', $graph->resource(get_uri($usage->id)));
        }
    }   

    return $name;

}

function get_format($path_parts){

    $format_string = null;
    $formats = \EasyRdf\Format::getFormats();

    // get the format if we have one 
    $format_string = null;
    if(count($path_parts) > 1 && strlen($path_parts[1]) > 1){
        // from the path
        $format_string = $path_parts[1];
    }else{
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
    }

    // throw wobbly if we don't recognize it
    if($format_string && !in_array($format_string, $formats)){
        header("HTTP/1.0 400 Bad Request");
        echo "Unrecognised data format \"{$path_parts[1]}\"";
        exit;
    }

    // if no format or it is html
    if(!$format_string){

        if(preg_match('/^[0-9]{4}-[0-9]{2}$/', $path_parts[0])){

            // this is just the classification as a whole
            $redirect_url = "https://wfoplantlist.org/plant-list/" . $path_parts[0];

        }else{
            // they are after a name or a taxon so we need to work things out a bit

            if(preg_match('/^wfo-[0-9]{10}$/', $path_parts[0])){
                // we have an unqualified wfo id we need to convert it to the latest classification
                $wfo_qualified_id = $path_parts[0] . '-' . WFO_DEFAULT_VERSION;
            }else{
                // wfo is already qualified
                $wfo_qualified_id = $path_parts[0];
            }

            // is it a synonym or not?
            $taxon_solr = solr_get_doc_by_id($wfo_qualified_id);

            if(!$taxon_solr){
                header("HTTP/1.1 404 Not Found");
                echo "Not found: {$path_parts[0]}";
                exit;
            }

            switch ($taxon_solr->role_s) {
                case 'accepted':
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$taxon_solr->id}";
                    break;
                case 'synonym':
                    $syn_wfo = substr($taxon_solr->id, 0, 14);
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$taxon_solr->accepted_id_s}?matched_id={$syn_wfo}";
                    break;
                case 'unplaced':
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$taxon_solr->id}";
                    break;
                case 'deprecated':
                    $redirect_url = "https://wfoplantlist.org/plant-list/taxon/{$taxon_solr->id}";
                    break;
                default:
                    echo "Unknown role type: {$taxon_solr->role_s}";
                    exit;
                    break;
            }

        }
                   
    }else{

        // they are asking for machine readable stuff

        // are they asking for a name but with classification qualifier?
        if(preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $path_parts[0])){
            $wfo_qualified_id = $path_parts[0];

            $taxon_solr = solr_get_doc_by_id($wfo_qualified_id);

            if(!$taxon_solr){
                header("HTTP/1.1 404 Not Found");
                echo "Not found: {$path_parts[0]}";
                exit;
            }

            if(in_array($taxon_solr->role_s, array('synonym', 'unplaced', 'deprecated'))){

                // they are asking for a name but have included a classification version - implying a taxon
                // simply redirect to the name.
                $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                    . "://$_SERVER[HTTP_HOST]/"
                    . substr($path_parts[0], 0,14);
                header("Location: $redirect_url",TRUE,301);
                echo "Moved: Name not taxon";
                exit;

            }
        }

        // are they actually asking for the metadata uri?
        if(count($path_parts) > 1 && strlen($path_parts[1]) > 1){
            return $format_string;
        }else{

            // format string was in header so redirect to metadata url
            // 303 redirect to the format version
            $redirect_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                . "://$_SERVER[HTTP_HOST]/"
                . $path_parts[0]
                . '/'
                . $format_string;

        }

    } // has format string

    // redirect them
    // always 303 redirect from the core object URIs
    header("Location: $redirect_url",TRUE,303);
    echo "Found: Redirecting to data";
    exit;
    
}



?>
