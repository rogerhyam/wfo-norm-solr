<?php

require_once('vendor/autoload.php');
require_once('../wfo_secrets.php'); // outside the github root

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Location of the solr server
define('SOLR_QUERY_URI','http://localhost:8983/solr/wfo');

// used for lookups and other services that don't want to 
// trouble themselves with many versions of backbone
// will normally be set to the most recent.
define('WFO_DEFAULT_VERSION','2019-05');

define('SOLR_USER', $solr_user); // from wfo_secrets.php
define('SOLR_PASSWORD', $solr_password); // from wfo_secrets.php

// add namespaces to easyrdf
\EasyRdf\RdfNamespace::set('dwc', 'http://rs.tdwg.org/dwc/terms/');

// the namespace we use for the terms is local to the distro
// only appropriate when we are not called from cli
if(php_sapi_name() !== 'cli'){
    $ns_uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/terms/";
    \EasyRdf\RdfNamespace::set('wfo', $ns_uri);
}

// unregister a few formats we don't have serialisers for
\EasyRdf\Format::unregister('rdfa');
\EasyRdf\Format::unregister('json-triples');
\EasyRdf\Format::unregister('json-triples');
\EasyRdf\Format::unregister('sparql-xml');
\EasyRdf\Format::unregister('sparql-json');


// used all over to generate guids
function get_uri($taxon_id){
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $taxon_id;
}





