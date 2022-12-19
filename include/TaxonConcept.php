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

    public int $partsCount = -1; 


    public function __construct($solr_doc){

        // add self to the list of created docs
        $this->id = $solr_doc->id;
        $this->title = "TaxonConcept: " . $solr_doc->id . " " . $solr_doc->full_name_string_plain_s;
        $this->guid = get_uri($this->id);
        $this->web = 'http://www.worldfloraonline.org/taxon/' . substr($this->id, 0, 14);
        $this->classification = Classification::getById($solr_doc->classification_id_s);
        $this->solr_doc = $solr_doc;

        self::$loaded[$this->id] = $this;

        if(property_exists($solr_doc, "role_s")){
            if($solr_doc->role_s == 'unplaced'){
                $this->editorialStatus =  "unchecked";
            }else{
                $this->editorialStatus = $solr_doc->role_s;
            }
        }else{
            $this->editorialStatus =  "unchecked";
        }

        $this->hasName = null;
        $this->hasPart = null;
        $this->isPartOf = null;
        $this->replacement_relations = array();

        // replacement relationships are populated as strings
        // initially
        $this->replacement_relations = get_replaces_replaced($solr_doc->wfo_id_s, $solr_doc->classification_id_s);

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
            'query' => 'parent_id_s:' . $this->solr_doc->id,
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
            'query' => 'parent_id_s:' . $this->solr_doc->id,
            'fields' => 'id',
            'offset' => $offset,
            'sort' => 'full_name_string_alpha_t_sort asc'
        );

        // -1 is unlimited but in Solr you just miss the parameter 
        if($limit >= 0){
            $query['limit'] = $limit;
        }

        $response = json_decode(solr_run_search($query));
        //error_log(print_r($response, true));

        if(isset($response->response->numFound)){
            foreach ($response->response->docs as $doc) {
                $this->hasPart[] = TaxonConcept::getById($doc->id);
            }
        }

        // Where are the unplaced names!
        /*  
            if we are a genus then it is simply the ones
            with the same genus name as us who are unplaced
        */

        if($this->solr_doc->rank_s == 'genus'){

            $query = array(
                'query' => 'genus_string_s:' . $this->solr_doc->name_string_s,
                'filter' => 'role_s:unplaced',
                'fields' => 'id',
                'offset' => $offset,
                'sort' => 'full_name_string_alpha_t_sort asc'
            );


            // -1 is unlimited but in Solr you just miss the parameter 
            if($limit >= 0){
                $query['limit'] = $limit;
            }

            $response = json_decode(solr_run_search($query));
            //error_log(print_r($response, true));

            if(isset($response->response->numFound)){
                foreach ($response->response->docs as $doc) {
                    $this->hasPart[] = TaxonConcept::getById($doc->id);
                }
            }

        }

        // ditto subspecific names
        if($this->solr_doc->rank_s == 'species'){

            $query = array(
                'query' => 'species_string_s:' . $this->solr_doc->name_string_s,
                'filter' => 'role_s:unplaced',
                'fields' => 'id',
                'offset' => $offset,
                'sort' => 'full_name_string_alpha_t_sort asc'
            );


            // -1 is unlimited but in Solr you just miss the parameter 
            if($limit >= 0){
                $query['limit'] = $limit;
            }

            $response = json_decode(solr_run_search($query));
            //error_log(print_r($response, true));

            if(isset($response->response->numFound)){
                foreach ($response->response->docs as $doc) {
                    $this->hasPart[] = TaxonConcept::getById($doc->id);
                }
            }

        }

        /*
            if we are a family then we are interested in genera alone
            - but how to find them?

            // get all the species that are synonyms in the family
            // get their genus names
            // any of them that are unplaced?

            // FIXME - or maybe not
        */

        return $this->hasPart;
        
    }

    public function getIsPartOf(){

        if($this->isPartOf === null){
            if(isset($this->solr_doc->parent_id_s)){
                $parent = solr_get_doc_by_id($this->solr_doc->parent_id_s);
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
            'query' => 'accepted_id_s:' . $this->solr_doc->id, 
            'limit' => 1000000,
            'sort' => 'full_name_string_alpha_t_sort asc'
        );
        $response = json_decode(solr_run_search($query));
    
        if($response->response->numFound > 0){
            foreach ($response->response->docs as $syn){
                $this->synonyms[] = TaxonName::getById($syn->wfo_id_s);
            }   
        }

        return $this->synonyms;
    }

    public static function getTaxonConceptSuggestion( $terms, $by_relevance = false, $limit = 30, $offset = 0 ){

        // we search by relevance if we don't have a whole word (there is a space in the string)
        // or the relevance box is ticked.
        if($by_relevance){
            // just do a generic string search
            $query = array(
                'query' => "_text_:$terms",
                'filter' => 'classification_id_s:' . WFO_DEFAULT_VERSION,
                'sort' => 'full_name_string_alpha_t_sort asc',
                'limit' => $limit,
                'offset' => $offset
            );
        }else{

            $name = trim(strtolower($terms));
            $name = ucfirst($name); // all names start with an upper case letter
            $name = str_replace(' ', '\ ', $name);
            $name = $name . "*";

            $query = array(
                'query' => "full_name_string_alpha_s:$name",
                'filter' => 'classification_id_s:' . WFO_DEFAULT_VERSION,
                'sort' => 'full_name_string_alpha_t_sort asc',
                'limit' => $limit,
                'offset' => $offset
            );

        }

       //error_log(print_r($query, true));

        $response = json_decode(solr_run_search($query));

        //error_log(print_r($response, true));

        $taxa = array();

        if(isset($response->response->docs)){

            for ($i=0; $i < count($response->response->docs); $i++) { 
                $doc = $response->response->docs[$i];
                $taxa[$doc->id] = TaxonConcept::getById($doc->id);
            }
        }

        error_log(print_r($taxa, true));
        return array_values($taxa);

    }

    public function getStats(){
        
        $stats = array();

        // we only work with families and genera because of how we generate stuff
        $rank = $this->solr_doc->rank_s;
        if($rank != 'family' && $rank != 'genus'){
            return $stats;
        }

        $query = array(
            'query' => 'acceptedNameUsageID_s:' . $this->solr_doc->wfo_id_s, 
            'filter' => 'classification_id_s:' . $this->solr_doc->classification_id_s,
            'facet' => array(
                "rank" => array(
                    "type" => "terms",
                    "field" => "rank_s",
                    'limit' => 1000,
                    'facet' => array(
                        "status" => array(
                            "type" => "terms",
                            "field" => "role_s",
                            'limit' => 1000
                        )
                    )
                ),
                "status" => array(
                    "type" => "terms",
                    "field" => "role_s",
                    'limit' => 1000
                )
            ),
            'limit' => 0
        );

        if($rank == 'family'){
            $query['query'] = 'family_s:' .  $this->solr_doc->full_name_string_alpha_s;
        }elseif($rank == 'genus'){
            $query['query'] = 'genus_string_s:' .  $this->solr_doc->full_name_string_alpha_s;
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
                            $status_name = getNewStatusName($status->val);
                            $stats[] = new TaxonConceptStat($rank->val . '-' . $status_name, "Total names with rank '$rank->val' and status '$status_name'", $status->count);
                        }
                    }
                }
            }

            // work through the status/rank facets
            if(isset($facets->rank)){
                foreach ($facets->status->buckets as $status) {
                    $status_name = getNewStatusName($status->val);
                    $stats[] = new TaxonConceptStat($status_name, "Total names with status '$status_name'", $status->count);
                }
            }


        }
        
        
        return $stats;
    }


    private function getNewStatusName($status_name){

        switch ($status_name) {
            case 'accepted':
                $status_name = 'Accepted';
                break;
            case 'synonym':
                $status_name = 'Synonym';
                break;
            case 'unplaced':
                $status_name = 'Unchecked';
                break;
            default:
                $status_name = 'Dubious';
                break;
        }

        return $status_name;
    }


}