<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;

require_once('include/TypeRegister.php');
require_once('solr_functions.php');
require_once('include/TaxonConcept.php');


$typeReg = new TypeRegister();

$schema = new Schema([
    'query' => new ObjectType([
        'name' => 'Query',
        'description' => 
            "This interface allows the querying of snapshots of the World Flora Online (WFO) taxonomic back bone.
            It is intended to provide a stable, authoritative inteface to all non-fungal botanical species and their names.
            Each time the taxonomy of the WFO is updated a copy of the classification is taken and added to this collection.
            It is therefore possible to query individual classifications or crawl the relationships between these classifications.
            In order to facilitate representation of multiple classifications within single system a taxon concept based approach has been adopted.
            New users should familiarise themselves with the documentation of TaxonConcept and TaxonName objects presented here.
            A geospatial analogy is to think of TaxonConcepts as a set of nested polygons, TaxonNames as the names that are applied to those
            polygons and each classification as a different version of the map.
            ",
        'fields' => [
            'taxonConceptById' => [
                'type' => TypeRegister::taxonConceptType(),
                'description' => 'Returns a TaxonConcept by its ID',
                'args' => [
                    'taxonId' => [
                        'type' => Type::string(),
                        'description' => 'The qualified id of the TaxonConcept'
                    ]
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    return TaxonConcept::getById( $args['taxonId'] );
                }
            ],
            'taxonNameById' => [
                'type' => TypeRegister::taxonNameType(),
                'description' => 'Returns a TaxonName by its ID',
                'args' => [
                    'nameId' => [
                        'type' => Type::string(),
                        'description' => 'The id of the TaxonName as appears in WFO'
                    ]
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    return TaxonName::getById( $args['nameId'] );
                }
            ]
        ]
            ]
            )
            ]);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$query = $input['query'];
$variableValues = isset($input['variables']) ? $input['variables'] : null;

$debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;

try {
    $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
    $output = $result->toArray($debug);
} catch (\Exception $e) {
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage()
            ]
        ]
    ];
}
header('Content-Type: application/json');
echo json_encode($output);


