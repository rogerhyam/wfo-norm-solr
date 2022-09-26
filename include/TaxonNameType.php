<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\EnumType;



require_once('include/TypeRegister.php');

class TaxonNameType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => "A TaxonName a string of characters used to name a taxon under as governed by the 
                International Code of Botanical Nomenclature (ICBN) https://www.iapt-taxon.org/nomen/main.php.
                TaxonNames may appear as the single correct name for a taxon or as one of the synonyms for that taxon.",
            'fields' => function(){
                return [
                    'id' => [
                        'type' => Type::string(),
                        'description' => "A locally identifier for this Name (actually the WFO ID)"
                    ],
                    'guid' => [
                        'type' => Type::string(),
                        'description' => "A globally unique identifier in the form of a URI that will resolve to data about it"
                    ],
                    'web' => [
                        'type' => Type::string(),
                        'description' => "A URI to the human readable web page for this resource."
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Scientific name, with rank abbreviations for trinomials, but not with author citation.'
                    ],
                    'fullNameString' => [
                        'type' => Type::string(),
                        'description' => 'Scientific name, with rank abbreviations for trinomials and authors and italics marked where possible.'
                    ],
                    'title' => [
                        'type' => Type::string(),
                        'description' => 'Needed by some GraphQL libraries this is a string rendering of he object probably only useful in development'
                    ],
                    'authorship' => [
                        'type' => Type::string(),
                        'description' => 'Author citation of the names following IPNI standard author abbreviations'
                    ],
                    'authorshipHtml' => [
                        'type' => Type::string(),
                        'description' => 'The same as the authorship property but authors are marked up in HTML with titles and links to their Wikidata entries'
                    ],
                    'rank' => [
                        'type' => new EnumType([
                            'name' => 'Rank',
                            'description' => 'Used for the relative position of the taxon with this name in the taxonomic hierarchy at time of publication.
                                For suprageneric names published on or after 1 January 1887, the rank is indicated by the termination of the name.
                                For names published on or after 1 January 1953, a clear indication of the rank is required for valid publication.',
                            'values' => ['Class',
                                        'Subclass',
                                        'Phylum',
                                        'Order',
                                        'Superorder',
                                        'Family',
                                        'Subfamily',
                                        'Tribe',
                                        'Subtribe',
                                        'Genus',
                                        'Subgenus',
                                        'Section',
                                        'Subsection',
                                        'Series',
                                        'Subseries',
                                        'Species',
                                        'Subspecies',
                                        'Variety',
                                        'Subvariety',
                                        'Form',
                                        'Forma',
                                        'Subforma',
                                        'Subform',
                                        'Nothospecies',
                                        'Nothosubsp',
                                        'Nothovar',
                                        'Infraspecificname',
                                        'Unranked',
                                        'Unrecognised']
                        ]),
                        "description" => "The name of the level within the classification at which this name was published."
                    ],
                    'familyName' => [
                        'type' => Type::string(),
                        'description' => 'Scientific name of the family to which the taxon of this name is assigned, generally according to APG 4. TaxonConcept->isPartOf has precidence over this.'
                    ],
                    'genusName' => [
                        'type' => Type::string(),
                        'description' => 'The generic name (for a genus or as part of a species or infraspecies name) w/o authors.'
                    ],
                    'specificEpithet' => [
                        'type' => Type::string(),
                        'description' => 'The species epithet (as part of a species or infraspecies name) w/o authors.'
                    ],
                    'publicationCitation' => [
                        'type' => Type::string(),
                        'description' => 'Abbreviated reference to place of publication of the name(nomenclatural citation)including details such as the page number.  Citations should follow community standards, for example IPNI. References for which the year of publication is not evident or is questionable, please consult TL2.'
                    ],
                    'nomenclatorID' => [
                        'type' => Type::string(),
                        'description' => 'Nomenclator ID (IPNI, etc.)'
                    ],

                    'acceptedNameFor' => [
                        'type' => Type::listOf(TypeRegister::taxonConceptType()),
                        'resolve' => function($name){
                            return $name->getAcceptedNamesFor();
                        },
                        'description' => 'The TaxonConcept for which this is the accepted name. A value of null means this name is currently considered a synonym and the 
                        currentPreferredUsage will return the TaxonConcept in which this is considered a'

                    ],
                    'currentPreferredUsage' => [
                        'type' => TypeRegister::taxonConceptType(),
                        'resolve' => function($name){
                            return $name->getCurrentPreferredUsage();
                        },
                        'description' => 'The TaxonConcept to which this TaxonName is assigned 
                        (either as the accepted name or a synonym) in the currently preferred (most recent) vesion of the WFO classification.'
                    ],

                    'currentPreferredUsageIsSynonym' =>[
                        'type' => Type::boolean(),
                        'description' => "Whether this name is a synonym in the currently preferred (most recent) WFO classification.
                            This is a convenience flag. It is the equivalent of currentPreferredUsage>hasName>guid != guid. That is, the negation of does the preferred placement in
                            this classification have this name as its accepted name.
                        "
                    ]

                    
                    ];
            }
        ];
        parent::__construct($config);

    }



}