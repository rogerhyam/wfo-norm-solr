<?php

require_once('TaxonConcept.php');

class TaxonName{

    public string $id;
    protected static $loaded = array();

    // simple strings that are returned as is
    public string $guid;
    public ?string $name;
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
        isset($name_data->scientificName_s) ? $this->name = $name_data->scientificName_s : $this->name = null;
        $this->title = "TaxonName: " . $this->id . " " . $this->name; // some libraries need a title for every object.
        
        isset($name_data->scientificNameAuthorship_s) ? $this->authorship = $name_data->scientificNameAuthorship_s: $this->authorship = null;
        
        isset($name_data->scientificNameAuthorship_html_s) ? $this->authorshipHtml = $name_data->scientificNameAuthorship_html_s: $this->authorshipHtml = null;
        
        isset($name_data->family_s) ? $this->familyName = $name_data->family_s : $this->familyName = null;
        isset($name_data->genus_s) ? $this->genusName = $name_data->genus_s : $this->genusName = null;
        isset($name_data->specificEpithet_s) ? $this->specificEpithet = $name_data->specificEpithet_s : $this->specificEpithet = null;
        isset($name_data->namePublishedIn_s) ? $this->publicationCitation = $name_data->namePublishedIn_s : $this->publicationCitation = null;
        isset($name_data->namePublishedInID_s) ? $this->publicationID = $name_data->namePublishedInID_s : $this->publicationID = null;
        isset($name_data->scientificNameID_s) ? $this->nomenclatorID = $name_data->scientificNameID_s : $this->nomenclatorID = null;

        isset($name_data->taxonRank_s) ? $this->rank = ucwords(strtolower($name_data->taxonRank_s)) : $this->taxonRank_s = null;

        // and bool
        $this->currentPreferredUsageIsSynonym = $name_data->currentPreferredUsageIsSynonym;


    }

    public static function getById($name_id){
        
        if(isset(self::$loaded[$name_id])){
            return self::$loaded[$name_id];
        }

        // get the different versions of this taxon
        $query = array(
            'query' => 'taxonID_s:' . $name_id,
            'sort' => 'snapshot_version_s asc'
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

        if($latest_usage->taxonomicStatus_s == 'Synonym'){
            // if it is a not accepted link to the accepted taxon
            if(isset($latest_usage->acceptedNameUsageID_s)){
                $accepted_taxon_id = $latest_usage->acceptedNameUsageID_s . '-' . $latest_usage->snapshot_version_s;
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
        $filters[] = 'snapshot_version_s:' . WFO_DEFAULT_VERSION;

        switch (count($words)) {

            // One word - names of genus and above.
            case 1:
                $filters[] = 'scientificName_s:' . $words[0];
                break;
            
            // Two words is species
            case 2:
                $filters[] = 'scientificName_s:' . implode(' ', $words);
                $filters[] = 'genus_s:' . $words[0];
                $filters[] = '-infraspecificEpithet_s:["" TO *]';// empty infraspecific
                break;

            // Three words is subspecific
            case 3:
                $filters[] = 'genus_s:' . $words[0];
                $filters[] = 'specificEpithet_s:' . $words[1];
                $filters[] = 'infraspecificEpithet_s:' . $words[2];
                break;

            // Four words is subspecific with rank between species 
            // and subspecific part
            case 2:
                $filters[] = 'genus_s:' . $words[0];
                $filters[] = 'specificEpithet_s:' . $words[1];
                $filters[] = 'infraspecificEpithet_s:' . $words[3];
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
            $matching_names[] = TaxonName::getById($usage->taxonID_s);
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
                'filter' => 'snapshot_version_s:' . WFO_DEFAULT_VERSION,
                'sort' => 'scientificName_s asc',
                'limit' => $limit,
                'offset' => $offset
            );
        }else{

            $name = trim(strtolower($terms));
            $name = ucfirst($name); // all names start with an upper case letter
            $name = str_replace(' ', '\ ', $name);
            $name = $name . "*";

            $query = array(
                'query' => "scientificName_s:$name",
                'filter' => 'snapshot_version_s:' . WFO_DEFAULT_VERSION,
                'sort' => 'scientificName_s asc',
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
                $names[$doc->taxonID_s] = TaxonName::getById($doc->taxonID_s);

            }
        }

//        error_log(print_r($names, true));
        return array_values($names);

    }
    

}