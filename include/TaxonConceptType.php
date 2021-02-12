<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;

require_once('include/TypeRegister.php');

class TaxonConceptType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => "A TaxonConcept is an accepted group of organisms within a classification( a.k.a taxonomy).
                A TaxonConcept has a link to one official TaxonName and potentially multiple synonymous TaxonNames.
                TaxonConcepts also have set type relationships to other TaxonConcepts in the classification hierarchy and version type relationships with TaxonConcepts in other classifications.",
            'fields' => function() {
                return [
                    'id' => [
                        'type' => Type::string(),
                        'description' => "A locally unique identifier for the taxon in the form of a qualified WFO ID "
                    ],
                    'title' => [
                        'type' => Type::string(),
                        'description' => "A basic human readable representation of the TaxonConcept probably just good for development."
                    ],
                    'guid' => [
                        'type' => Type::string(),
                        'description' => "A globally unique identifier in the form of a URI that will resolve to data about it"
                    ],
                    'classification' => [
                        'type' => TypeRegister::classificationType(),
                        'description' => "The classification this TaxonConcept belongs to"
                    ],
                    'web' => [
                        'type' => Type::string(),
                        'description' => "A URI to the human readable web page for this resource."
                    ],
                    'editorialStatus' => [
                        'type' => Type::string(),
                        'description' => "Whether this taxon has been Accepted within the current classification or whether there is some doubt as to its status."
                    ],
                    'hasName' => [
                        'type' => TypeRegister::taxonNameType(),
                        'resolve' => function($taxon){
                            // we load the name if we need to!
                            return $taxon->getHasName();
                        },
                        'description' => "The name that should be used for this taxon according to the International Code of Botanical Nomenclature"
                    ],
                    'hasSynonym' => [
                        'type' => Type::listOf(TypeRegister::taxonNameType()),
                        'resolve' => function($taxon){
                            // we load the name if we need to!
                            return $taxon->getHasSynonym();
                        },
                        'description' => "A name associated with this TaxonConcept which should not be used.
                        This includes homotypic (nomenclatural) synonyms which share the same type specimen as the accepted name 
                        and heterotypic (taxonomic) synonyms whose type specimens are considered to fall within the circumscription of this taxon."
                    ],
                    'hasPart' => [
                        'type' => Type::listOf(TypeRegister::taxonConceptType()),
                        'resolve' => function($taxon, $args, $context, $info){
                            // we load the name if we need to!

                            $limit = -1;
                            if(isset($args['limit'])) $limit = $args['limit'];

                            $offset = 0;
                            if(isset($args['offset'])) $offset = $args['offset'];

                            return $taxon->getHasPart($limit, $offset);

                        },
                        'args' => [
                            'offset' => [
                                'type' => Type::int(),
                                'description' => 'How far through the result set to start.'
                            ],
                            'limit' => [
                                'type' => Type::int(),
                                'description' => 'Maximum number of results to return'
                            ]
                        ],
                        'description' => "A sub taxon of the current taxon within this classification"
                    ],
                    'partsCount' => [
                        'type' => Type::int(),
                        'description' => "The number of subtaxa (parts) of this taxon.",
                        'resolve' => function($taxon){
                            return $taxon->getPartsCount();
                        },
                    ],
                    'isPartOf' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($taxon){
                            // we load the name if we need to!
                            return $taxon->getIsPartOf();
                        },
                        'description' => "The parent taxon of the current taxon within this classification"
                    ],
                    'path' => [
                        'type' => Type::listOf(TypeRegister::taxonConceptType()),
                        'resolve' => function($taxon){
                            $path = array();
                            $path = array_reverse($taxon->getPath($path));
                            return $path;
                        },
                        'description' => "The path of inclusion from the root taxon to this taxon. Good for bread crumb trails."
                    ],
                    'replaces' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($taxon){
                            // we load the name if we need to!
                            return $taxon->getReplacementRelation('dc:replaces');
                        },
                        'description' => "The nearest equivalent taxon in the previously published classification. See also notes under isReplacedBy"
                    ],
                    'isReplacedBy' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($taxon){
                            // we load the name if we need to!
                            return $taxon->getReplacementRelation('dc:isReplacedBy');
                        },
                        'description' => "The nearest equivalent taxon in the next published classification.
                            If the accepted name of the current taxon has become a synonym of another taxon in the new classification
                            (i.e. it has been sunk) then this taxon is replaced by the accepted taxon in the new classification.
                            This does not necessarily mean that all specimens and observations associated with this taxon can be automatically assigned 
                            to the taxon in the new classification under a new name. Synonomizing a name is a nomenclatural act signifying the movement of 
                            a single type specimen. The original TaxonConcept may be contained in the new TaxonConcept, be split up or renamed.
                            See also the source attribute. 
                        "
                    ],
                    'source' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($taxon){
                            // we load the name if we need to!
                            return $taxon->getReplacementRelation('dc:source');
                        },
                        'description' => "The TaxonConcept in the previous classification in which this taxon's name was a synonym.
                        If the accepted TaxonName for the current TaxonConcept was a synonym of a TaxonConcept in the last classification then the 
                        TaxonConcept in the last classification is considered the source for this taxon and not to be replaced by it.
                        This taxon is not considered to replace the previous taxon as that one may have an equivalent (with the same accepted name) in this classification."
                    ]
                ];
            }
        ];
        parent::__construct($config);

    }

}