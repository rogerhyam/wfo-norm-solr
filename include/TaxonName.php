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
        $this->id = $name_data->id;
        $this->name_data = $name_data;
        $this->web = 'http://www.worldfloraonline.org/taxon/' . $this->id;
        
        // keep tags on the fact we exist so we don't get created again
        self::$loaded[$this->id] = $this;

        // set those string fields
        $this->guid = get_uri($this->id);
        $this->name = isset($name_data->full_name_string_no_authors_plain_s) ? $name_data->full_name_string_no_authors_plain_s : null;
        $this->fullNameString = isset($name_data->full_name_string_html_s) ? $name_data->full_name_string_html_s : null;
        $this->title = "TaxonName: " . $this->id . " " . $this->name; // some libraries need a title for every object.
        $this->authorship = isset($name_data->authors_string_s) ?  $name_data->authors_string_s : null;
        $this->authorshipHtml = isset($name_data->authors_string_html_s) ? $name_data->authors_string_html_s: null;
       
        $this->familyName = isset($name_data->placed_in_family_s) ? $name_data->placed_in_family_s : null;
        
        if($name_data->rank_s == 'genus'){
            // it is a genus so we set the name
            $this->genusName = isset($name_data->name_string_s) ? $name_data->name_string_s : null;
        }else{
            // it is not a genus so we set the genus part if there is one
            $this->genusName = isset($name_data->genus_string_s) ? $name_data->genus_string_s : $this->genusName = null;
        }
        
        if($name_data->rank_s == 'species'){
            // it is a species so we set the name
            $this->specificEpithet = isset($name_data->name_string_s) ? $name_data->name_string_s : null;
        }else{
            // it is maybe below species so we specific epithet
            $this->specificEpithet = isset($name_data->species_string_s) ? $name_data->species_string_s : null;
        }

        $this->publicationCitation = isset($name_data->citation_micro_s) ? $name_data->citation_micro_s : null;

        $this->publicationID = null; // FIXME: Version 2

        $this->nomenclatorID = null; 
        if(isset($name_data->identifiers_other_kind_ss)){
            for ($i=0; $i < count($name_data->identifiers_other_kind_ss); $i++) { 
              $kind = $name_data->identifiers_other_kind_ss[$i];
              if($kind == 'ipni' || $kind == 'tropicos'){
                $this->nomenclatorID = $name_data->identifiers_other_value_ss[$i];
                break;
              }
            }
        }

        $this->rank = isset($name_data->rank_s) ?  $name_data->rank_s : null;

        // and bool
        $this->currentPreferredUsageIsSynonym = $name_data->role_s == 'synonym';


    }

    public static function getById($name_id){
        
        if(isset(self::$loaded[$name_id])){
            return self::$loaded[$name_id];
        }

        // get the different versions of this taxon
        $query = array(
            'query' => 'wfo_id_s:' . $name_id,
            'sort' => 'classification_id_s asc'
        );
        $response = json_decode(solr_run_search($query));

        // produce a combined version. 
        // new values overwrite old
        // we are assuming things are getting better but not counting on it
        $name_data = array();
        $name_data['acceptedNamesFor'] = array();
        $latest_usage = null;
        foreach ($response->response->docs as $usage) {

            foreach($usage   as $key => $value){
                $name_data[$key] = $value;
            }

            // also keep a handle on the taxon for later use
            $name_data['acceptedNamesFor'][] = $usage->id;

            // get a handle on the latest taxon useage.
            $latest_usage = $usage;
            
        }

        if($latest_usage->role_s == 'synonym'){
            // if it is a not accepted link to the accepted taxon
            if(isset($latest_usage->acceptedNameUsageID_s)){
                $accepted_taxon_id = $latest_usage->acceptedNameUsageID_s . '-' . $latest_usage->classification_id_s;
                $name_data['currentPreferredUsage'] = $accepted_taxon_id;
                $name_data['currentPreferredUsageIsSynonym'] = true;
            }
        }else{
            $name_data['currentPreferredUsage'] = $latest_usage->id;
            $name_data['currentPreferredUsageIsSynonym'] = false;
        }
        

        // override the id as this is a name
        $name_data['id'] = $name_id;

        return new TaxonName((object)$name_data);

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
        if(isset($this->name_data->currentPreferredUsage)){
            return TaxonConcept::getById($this->name_data->currentPreferredUsage);
        }else{
            return null;
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