<?php

require_once('TaxonConcept.php');

class TaxonName{

    public string $id;
    protected static $loaded = array();

    // simple strings that are returned as is
    public string $guid;
    public ?string $name;
    public ?string $fullNameString;
    public ?string $title;
    public ?string $authorship;
    public ?string $familyName;
    public ?string $genusName;
    public ?string $specificEpithet;
    public ?string $publicationCitation;
    public ?string $publicationID;
    public ?string $nomenclatorID;
    public bool $currentPreferredUsageIsSynonym;

    // fixme; rank enumeration
    public ?string $rank;
    public string $web;

    public function __construct($name_data){
        // add self to the list of created docs
        $this->id =  $name_data->id;
        $this->name_data = $name_data;
        $this->web = 'http://www.worldfloraonline.org/taxon/' . $name_data->wfo_id_s;
        
        // keep tags on the fact we exist so we don't get created again
        self::$loaded[$this->id] = $this;

        // set those string fields
        $this->guid = get_uri($this->id);
        $this->name = isset($name_data->full_name_string_no_authors_plain_s) ? $name_data->full_name_string_no_authors_plain_s : null;
        $this->fullNameString = isset($name_data->full_name_string_html_s) ? $name_data->full_name_string_html_s : null;
        $this->title = "TaxonName: " . $this->id . " " . $this->name; // some libraries need a title for every object.
        $this->authorship = isset($name_data->authors_string_s) ?  $name_data->authors_string_s : "";
        $this->authorshipHtml = isset($name_data->authors_string_html_s) ? $name_data->authors_string_html_s : "";
       
        $this->familyName = isset($name_data->placed_in_family_s) ? $name_data->placed_in_family_s : "";
        
        if($name_data->rank_s == 'genus'){
            // it is a genus so we set the name
            $this->genusName = isset($name_data->name_string_s) ? $name_data->name_string_s : "";
        }else{
            // it is not a genus so we set the genus part if there is one
            $this->genusName = isset($name_data->genus_string_s) ? $name_data->genus_string_s : $this->genusName = "";
        }
        
        if($name_data->rank_s == 'species'){
            // it is a species so we set the name
            $this->specificEpithet = isset($name_data->name_string_s) ? $name_data->name_string_s : "";
        }else{
            // it is maybe below species so we specific epithet
            $this->specificEpithet = isset($name_data->species_string_s) ? $name_data->species_string_s : "";
        }

        $this->publicationCitation = isset($name_data->citation_micro_s) ? $name_data->citation_micro_s : "";

        $this->publicationID = ""; // FIXME: Version 2

        $this->nomenclatorID = ""; 
        if(isset($name_data->identifiers_other_kind_ss)){
            for ($i=0; $i < count($name_data->identifiers_other_kind_ss); $i++) { 
              $kind = $name_data->identifiers_other_kind_ss[$i];
              if($kind == 'ipni' || $kind == 'tropicos'){
                $this->nomenclatorID = $name_data->identifiers_other_value_ss[$i];
                break;
              }
            }
        }

        $this->rank = isset($name_data->rank_s) ?  $name_data->rank_s : "";

        // and bool
        $this->currentPreferredUsageIsSynonym = $name_data->role_s == 'synonym';

    }

    public static function getById($wfo_id){
        if(isset(self::$loaded[$wfo_id])){
            return self::$loaded[$wfo_id];
        }
        $name_data = solr_get_doc_by_id($wfo_id . '-' . WFO_DEFAULT_VERSION);
        return new TaxonName($name_data);
    }

    public static function getByMatching($name_string, $authors_string ){

        $matching_names = array();

        $words = preg_split('/[\s,.]+/', $name_string);

        $filters = array();
        $filters[] = 'classification_id_s:' . WFO_DEFAULT_VERSION;

        switch (count($words)) {

            // One word - names of genus and above.
            case 1:
                $filters[] = 'full_name_string_alpha_s:' . $words[0];
                break;
            
            // Two words is species
            case 2:
                $filters[] = 'full_name_string_alpha_s:' . implode(' ', $words);
                $filters[] = 'genus_string_s:' . $words[0];
                $filters[] = '-infraspecificEpithet_s:["" TO *]';// empty infraspecific
                break;

            // Three words is subspecific
            case 3:
                $filters[] = 'genus_string_s:' . $words[0];
                $filters[] = 'species_string_s:' . $words[1];
                $filters[] = 'name_string_s:' . $words[2];
                break;

            // Four words is subspecific with rank between species 
            // and subspecific part
            case 2:
                $filters[] = 'genus_string_s:' . $words[0];
                $filters[] = 'species_string_s:' . $words[1];
                $filters[] = 'name_string_s:' . $words[3];
                break;

            // abject failure
            default:
                error_log('No name match for :' . $name_string);
                return $matching_names;
                break;
        }

        if($authors_string){
            $filters[] = 'scientificNameAuthorship_s:' . $authors_string;
        }

        $query = array(
            'query' => '*:*',
            'filter' => $filters,
            'sort' => 'scientificName_s asc'
        );
        $response = json_decode(solr_run_search($query));

        foreach ($response->response->docs as $usage) {
            $matching_names[] = TaxonName::getById($usage->wfo_id_s);
        }

        // FIXME - add author filter.

        return $matching_names;

    }

    /*
        Fields returning objects
    */
    public function getAcceptedNamesFor(){
        $taxa = array();
        foreach($this->name_data->acceptedNamesFor as $taxon_id){
           $taxa[] = TaxonConcept::getById($taxon_id);
        }
        return $taxa;
    }

    public function getCurrentPreferredUsage(){
        if($this->name_data->role_s == 'accepted'){
            return TaxonConcept::getById($this->name_data->id);
        }elseif($this->name_data->role_s == 'synonym' && isset($this->name_data->accepted_id_s)){
            return TaxonConcept::getById($this->name_data->accepted_id_s);
        }else{
            return TaxonConcept::getById($this->name_data->id); // debug
//            return null;
        }
    }

    public static function getTaxonNameSuggestion( $terms, $by_relevance = false, $limit = 30, $offset = 0 ){


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

        $names = array();

        if(isset($response->response->docs)){

            for ($i=0; $i < count($response->response->docs); $i++) { 
            
                $doc = $response->response->docs[$i];
                $names[$doc->wfo_id_s] = TaxonName::getById($doc->wfo_id_s);

            }
        }

//        error_log(print_r($names, true));
        return array_values($names);

    }
    

}