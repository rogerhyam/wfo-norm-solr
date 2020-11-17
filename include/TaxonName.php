<?php

require_once('TaxonConcept.php');

class TaxonName{

    protected string $id;
    protected static $loaded = array();

    // simple strings that are returned as is
    public string $guid;
    public ?string $name;
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
        isset($name_data->scientificNameAuthorship_s) ? $this->authorship = $name_data->scientificNameAuthorship_s: $this->authorship = null;
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
    

}