<?php
require_once('config.php');

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;

require_once('include/TypeRegister.php');
require_once('include/solr_functions.php');
require_once('include/TaxonConcept.php');
require_once('include/Classification.php');


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
            'classifications' => [
                'type' => Type::listOf(TypeRegister::classificationType()),
                'args' => [
                    'classificationId' => [
                        'type' => Type::string(),
                        'description' => "The ID of the classification to load.
                            'DEFAULT' will return the default (most recent) classification.
                            'ALL' will return all the classifications ordered by date desc",
                        'required' => true
                    ]
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    $b = Classification::getById( $args['classificationId'] );
                    if(!is_array($b)) $b = array($b);
                    return $b;
                }
            ],
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
            ],
            'taxonNameMatch' => [
                'type' => Type::listOf(TypeRegister::taxonNameType()),
                'description' => 'Returns Taxon Names matching the supplied string(s)',
                'args' => [
                    'name' => [
                        'type' => Type::string(),
                        'description' => "
                            The name of the taxon without authority string.
                            Could be one word - names of genus and above.
                            Could be two words - species.
                            Could be three words - subspecific taxa without rank abbreviation.
                            Could be four words - subspecific taxa, third word is assumed the rank and will be ignored for matching.                       
                        "
                    ],
                    'authors' => [
                        'type' => Type::string(),
                        'description' => 'The complete author string using standard author abbreviations where available.
                        The author string is a further filter on the name string. You can not look for names just based on author string.'
                    ],
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    $name_string = isset($args['name']) ? $args['name'] : "";
                    $authors_string = isset($args['authors']) ? $args['authors'] : "";
                    return TaxonName::getByMatching( $name_string, $authors_string );
                }
            ],
            'taxonConceptSuggestion' => [
                'type' => Type::listOf(TypeRegister::taxonConceptType()),
                'description' => 'Suggests a taxon from the preferred (most recent) taxonomy when given a partial name string.
                    Designed to be useful in providing suggestions when identifying specimens.',
                'args' => [
                    'termsString' => [
                        'type' => Type::string(),
                        'description' => 'String representing all or part of a taxon name.
                        Search is case sensitive to differentiate genera from epithets.
                        An effective search strategy is to just type the first four letters of a 
                        genus and species name, capitalizing the genus.
                        '
                    ]
                ],
                'resolve' => function($rootValue, $args, $context, $info) {
                    return  TaxonConcept::getTaxonConceptSuggestion( $args['termsString']  );
                }
            ]
        ]
            ]
            )
            ]);

$rawInput = file_get_contents('php://input');

if(!trim($rawInput)){
    echo "<h1>WFO Plant List GraphQL Endpoint</h1>";
    echo "<p>You don't seem to have given us a query to work with. Please use a GraphQL client to pass query info.</p>";
    exit;
}

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


