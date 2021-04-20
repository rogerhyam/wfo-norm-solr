<?php

require_once('config.php');
require_once('include/TaxonName.php');
require_once('include/Classification.php');
require_once('include/solr_functions.php');
require_once('include/functions.php');
require_once('include/TaxonConceptStat.php');

class TaxonConcept{

    public string $id;
    public string $title;

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
    public Classification $classification;
    public TaxonName $highlightedSynonym;
    public int $partsCount = -1; 


    public function __construct($solr_doc){

        // add self to the list of created docs
        $this->id = $solr_doc->id;
        $this->title = "TaxonConcept: " . $solr_doc->id . " " . $solr_doc->scientificName_s;
        $this->guid = get_uri($this->id);
        $this->web = 'http://www.worldfloraonline.org/taxon/' . substr($this->id, 0, 14);
        $this->classification = Classification::getById($solr_doc->snapshot_version_s);
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
        
        if(!$solr_doc) return null;

        return new TaxonConcept($solr_doc);

    }

    public function getPartsCount(){

        if($this->partsCount !== -1) return $this->partsCount;

        $query = array(
            'query' => 'parentNameUsageID_s:' . substr($this->id, 0, 14),
            'filter' => 'snapshot_version_s:' . $this->solr_doc->snapshot_version_s,
            'fields' => 'id',
            'limit' => 0,
            'offset' => 0,
        );
        $response = json_decode(solr_run_search($query));

        if(isset($response->response->numFound)){
            $this->partsCount = $response->response->numFound;
        }

        return $this->partsCount;
    
    }

    public function getHasPart($limit, $offset){

        $this->hasPart = array();

        $query = array(
            'query' => 'parentNameUsageID_s:' . substr($this->id, 0, 14),
            'filter' => 'snapshot_version_s:' . $this->solr_doc->snapshot_version_s,
            'fields' => 'id',
            'offset' => $offset,
            'sort' => 'scientificName_s asc'
        );

        // -1 is unlimited but in Solr you just miss the parameter 
        if($limit >= 0){
            $query['limit'] = $limit;
        }

        $response = json_decode(solr_run_search($query));
        //error_log(print_r($response, true));
        if($response->response->numFound > 0){
            foreach ($response->response->docs as $kid) {
                $this->hasPart[] = TaxonConcept::getById($kid->id);
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

    public function getPath(&$path){
        // add ourself
        $path[] = $this;
        $parent = $this->getIsPartOf();
        if($parent !== null){
            $parent->getPath($path);
        }
        return $path;
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

    public static function getTaxonConceptSuggestion( $terms, $by_relevance = false, $limit = 30, $offset = 0 ){

        if($by_relevance){
            // just do a generic string search
            $query = array(
                'query' => "_text_:$terms",
                'filter' => 'snapshot_version_s:' . WFO_DEFAULT_VERSION,
                'sort' => 'scientificName_s asc',
                'limit' => $limit,
                'offset' => $offset
            );
        }else{

            // we are looking for a plant name, entire, and expect its children
            $name_path = trim(strtolower($terms)); // all lower
            $name_path =  preg_replace('/\s+/', '/', $name_path); // no double spaces & replace with slashes;
            $name_path = '/' . $name_path;

            $query = array(
                'query' => "name_ancestor_path:\"$name_path\" OR name_descendent_path:\"$name_path\"",
                'filter' => 'snapshot_version_s:' . WFO_DEFAULT_VERSION,
                'sort' => 'name_ancestor_path asc',
                'limit' => $limit,
                'offset' => $offset
            );
        }

        $response = json_decode(solr_run_search($query));

        error_log(print_r($response, true));

        $taxa = array();

        if(isset($response->response->docs)){

            for ($i=0; $i < count($response->response->docs); $i++) { 
            
                $doc = $response->response->docs[$i];
        
                // if it is a synonym we replace it with the accepted taxon
                if($doc->taxonomicStatus_s == 'Synonym'){
                   
                    // load the accepted name
                    $accepted_doc = solr_get_doc_by_id($doc->acceptedNameUsageID_s . '-' . $doc->snapshot_version_s);
                    $accepted = TaxonConcept::getById($accepted_doc->id);

                    // we highlight the synonym so that people know why it was returned.
                    $accepted->highlightedSynonym = TaxonName::getById($doc->taxonID_s);

                    // add the accepted name to the returned list
                    $taxa[$accepted->id] = $accepted;

                }else{
                    $taxa[$doc->id] = TaxonConcept::getById($doc->id);        
                }
        
            }
        }

        return array_values($taxa);

    }

    public function getStats(){
        
        $stats = array();

        // we only work with families and genera because of how we generate stuff
        $rank = $this->solr_doc->taxonRank_lower_s;
        if($rank != 'family' && $rank != 'genus'){
            return $stats;
        }

        $query = array(
            'query' => 'acceptedNameUsageID_s:' . $this->solr_doc->taxonID_s, 
            'filter' => 'snapshot_version_s:' . $this->solr_doc->snapshot_version_s,
            'facet' => array(
                "rank" => array(
                    "type" => "terms",
                    "field" => "taxonRank_lower_s",
                    'limit' => 1000,
                    'facet' => array(
                        "status" => array(
                            "type" => "terms",
                            "field" => "taxonomicStatus_s",
                            'limit' => 1000
                        )
                    )
                ),
                "status" => array(
                    "type" => "terms",
                    "field" => "taxonomicStatus_s",
                    'limit' => 1000
                )
            ),
            'limit' => 0
        );

        if($rank == 'family'){
            $query['query'] = 'family_s:' .  $this->solr_doc->scientificName_s;
        }elseif($rank == 'genus'){
            $query['query'] = 'genus_s:' .  $this->solr_doc->scientificName_s;
        }else{
            return $stats;
        }

        $response = json_decode(solr_run_search($query));

        if(isset($response->facets)){
            $facets = $response->facets;

            // work through the rank/status facets
            if(isset($facets->rank)){
                foreach ($facets->rank->buckets as $rank) {
                    $stats[] = new TaxonConceptStat($rank->val, "Total names with rank '$rank->val'", $rank->count);
                    if(isset($rank->status)){
                        foreach ($rank->status->buckets as $status) {
                            $stats[] = new TaxonConceptStat($rank->val . '-' . $status->val, "Total names with rank '$rank->val' and status '$status->val'", $status->count);
                        }
                    }
                }
            }

            // work through the status/rank facets
            if(isset($facets->rank)){
                foreach ($facets->status->buckets as $status) {
                    $stats[] = new TaxonConceptStat($status->val, "Total names with status '$status->val'", $status->count);
                }
            }


        }
        
        
        return $stats;
    }


    


}