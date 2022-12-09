<?php

// after branch of pre-rhakhis

require_once('vendor/autoload.php');
require_once('../wfo_secrets.php'); // outside the github root

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL & ~E_NOTICE);
error_reporting(E_ALL);
session_start();

// Location of the solr server
define('SOLR_QUERY_URI','http://localhost:8983/solr/wfo');

// used for lookups and other services that don't want to 
// trouble themselves with many versions of backbone
// will normally be set to the most recent.
define('WFO_DEFAULT_VERSION','2021-12');

define('SOLR_USER', $solr_user); // from wfo_secrets.php
define('SOLR_PASSWORD', $solr_password); // from wfo_secrets.php

// add namespaces to easyrdf
// \EasyRdf\RdfNamespace::set('dwc', 'http://rs.tdwg.org/dwc/terms/');

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

// create and initialize the database connection - defined in ../wfo_secrets.php
$mysqli = new mysqli($db_host, $db_user, $db_password, $db_database);

// connect to the database
if ($mysqli->connect_error) {
  echo $mysqli->connect_error;
}

if (!$mysqli->set_charset("utf8")) {
  echo printf("Error loading character set utf8: %s\n", $mysqli->error);
}


// used all over to generate guids
function get_uri($taxon_id){
  if(php_sapi_name() === 'cli'){
    return "https://list.worldfloraonline.org/" . $taxon_id;
  }else{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $taxon_id;
  }
}
  
    





