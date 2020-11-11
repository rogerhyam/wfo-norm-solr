<?php

//$foaf = new \EasyRdf\Graph("http://njh.me/foaf.rdf");
//$foaf->load();
//$me = $foaf->primaryTopic();


$graph = new \EasyRdf\Graph();
$me = $graph->resource('http://www.example.com/joe#me', 'foaf:Person');
$me->set('foaf:name', 'Joseph Bloggs');
$me->set('foaf:title', 'Mr');
$me->set('foaf:nick', 'Joe');
$me->add('foaf:homepage', $graph->resource('http://example.com/joe/'));


$format = \EasyRdf\Format::getFormat('jsonld');
$data = $graph->serialise($format);



print_r($data);


//echo "My name is: ".$me->get('foaf:name')."\n";

