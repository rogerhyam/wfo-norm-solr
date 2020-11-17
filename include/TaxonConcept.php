<?php

require_once('include/TaxonName.php');
require_once('solr_functions.php');
require_once('include/functions.php');

class TaxonConcept{

    protected string $id;

    protected static $loaded = array();
    private ?TaxonName $hasName;
    private ?Array $hasPart;
    private ?TaxonConcept $isPartOf;
    private ?Array $replacement_relations;
    private ?Array $synonyms;
    
    // public properties called by GraphQL
    public string $editorialStatus;
    public string $guid;
    public string $web;


    public function __construct($solr_doc){

        // add self to the list of created docs
        $this->id = $solr_doc->id;
        $this->guid = get_uri($this->id);
        $this->web = 'http://www.worldfloraonline.org/taxon/' . substr($this->id, 0, 14);
        $this->solr_doc = $solr_doc;

        self::$loaded[$this->id] = $this;

        $this->editorialStatus = $solr_doc->taxonomicStatus_s;

        $this->hasName = null;
        $this->hasPart = null;
        $this->isPartOf = null;
        $this->replacement_relations = array();

        // replacement relationships are populated as strings
        // initially
        $this->replacement_relations = get_replaces_replaced($solr_doc->taxonID_s, $solr_doc->snapshot_version_s);

    }

    public function getHasName(){
        if(!$this->hasName){
            $name_id = substr($this->id, 0, 14);
            $this->hasName = TaxonName::getById($name_id);
        }
        return $this->hasName;
    }

    public static function getById($taxon_id){
        
        if(isset(self::$loaded[$taxon_id])){
            return self::$loaded[$taxon_id];
        }

        $solr_doc = solr_get_doc_by_id($taxon_id);
        
        return new TaxonConcept($solr_doc);

    }

    public function getHasPart(){

        if($this->hasPart === null){

            $this->hasPart = array();

             // add child taxa
            $query = array(
                'query' => 'parentNameUsageID_s:' . substr($this->id, 0, 14),
                'filter' => 'snapshot_version_s:' . $this->solr_doc->snapshot_version_s,
                'fields' => 'id',
                'limit' => 1000000,
                'sort' => 'id asc'
            );
            $response = json_decode(solr_run_search($query));
            if($response->response->numFound > 0){
                foreach ($response->response->docs as $kid) {
                    $this->hasPart[] = TaxonConcept::getById($kid->id);
                }
            }
        }
        return $this->hasPart;
    }

    public function getIsPartOf(){

        if($this->isPartOf === null){
            if(isset($this->solr_doc->parentNameUsageID_s)){
                $parent = solr_get_doc_by_id($this->solr_doc->parentNameUsageID_s . '-' . $this->solr_doc->snapshot_version_s);
                if($parent){
                   $this->isPartOf = TaxonConcept::getById($parent->id);
                }
            }
        }

        return $this->isPartOf;

    }

    public function getReplacementRelation($relation_type){

        if(isset($this->replacement_relations[$relation_type])){
            // we replaced something
            if(!is_object($this->replacement_relations[$relation_type])){
                // it isn't an object - will be string of GUID
                $id = substr($this->replacement_relations[$relation_type], -22);
                $this->replacement_relations[$relation_type] = TaxonConcept::getById($id);
            }

            return $this->replacement_relations[$relation_type];

        }else{
            // we didn't replace anything
            return null;
        }
    }

    public function getHasSynonym(){

        // we only build this once when asked for it
        if(isset($this->synonyms)) return $this->synonyms;

        // not built it yet so lets do it.
        $this->synonyms = array();

        $query = array(
            'query' => 'acceptedNameUsageID_s:' . $this->solr_doc->taxonID_s, 
            'filter' => 'snapshot_version_s:' . $this->solr_doc->snapshot_version_s,
            'limit' => 1000000,
            'sort' => 'id asc'
        );
        $response = json_decode(solr_run_search($query));
    
        if($response->response->numFound > 0){
            foreach ($response->response->docs as $syn){
                $this->synonyms[] = TaxonName::getById($syn->taxonID_s);
            }   
        }

        return $this->synonyms;
    }



    


}