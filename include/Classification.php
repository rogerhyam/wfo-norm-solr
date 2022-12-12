<?php

require_once('config.php');
require_once('include/solr_functions.php');
require_once('include/functions.php');

class Classification{


    protected static $loaded = array();
    protected static $default_classification_id = null;

    public string $id;
    public string $title;
    public int $taxonCount;
    public int $year;
    public int $month; // as int


    public function __construct($classification_id, $accepted_taxon_count){

        // add self to the list of created docs
        $this->id = $classification_id;
        $this->taxonCount = $accepted_taxon_count;
        $this->title = "WFO Classification " . $classification_id . " (" .  number_format($this->taxonCount) . " taxa)";
        $parts = explode('-', $this->id);
        $this->year = (int)$parts[0];
        $this->month = (int)$parts[1];

        // add myself to the list of loaded classifications so I'm not loaded again.
        self::$loaded[$this->id] = $this;

    }


    public static function getById($classification_id){

        // we just load all the classifications the first time we are asked as it is a single query.
        if(!self::$loaded || count(self::$loaded) == 0){

            $query = array(
                'query' => '*:*',
                'facet' => array(
                    'classification_id_s' => array(
                        'type' => "terms",
                        'limit' => -1,
                        'mincount' => 1,
                        'missing' => false,
                        'sort' => 'index',
                        'field' => 'classification_id_s'
                    )
                ),
                'filter' => array(
                    "role_s:accepted" // restrict count to accepted taxa
                ),
                'limit' => 0
            );
            $response = json_decode(solr_run_search($query));
            //error_log(print_r($response->facets->classification_id_s->buckets, true));

            // get out of here if there are no classifications!
            if(!isset($response->facets->classification_id_s->buckets)){
                error_log('No classifications found!');
                return array();
            }

            foreach ($response->facets->classification_id_s->buckets as $bucket) {
                $c = new Classification($bucket->val, $bucket->count);
                self::$default_classification_id = $c->id; //the last one will be the default
            }

            // we always list in desc order
            self::$loaded = array_reverse(self::$loaded);

        }

        if($classification_id == 'ALL'){
           return array_values(self::$loaded);
        }

        if($classification_id == 'DEFAULT'){
            $default_id = self::$default_classification_id;
            return self::$loaded[$default_id];
        }

        if(array_key_exists($classification_id, self::$loaded)){
            return self::$loaded[$classification_id];
        }else{
            return null;
        }

    }


    public function getMonthName($locale = 'en_GB.UTF-8'){
        //return $locale;

        setlocale(LC_TIME, $locale);
        $month_name = strftime("%B", mktime(0, 0, 0, $this->month, 10));
        // back to default locale
        setlocale(LC_TIME, "");
        return $month_name;
    }

    public function getReplaces(){

    }

    public function getReplacedBy(){


    }

    public function getPhyla(){

        $query = array(
            'query' => '*:*',
            'filter' => array(
                "role_s:accepted",
                "classification_id_s:{$this->id}",
                "rank_s:phylum"
            ),
            'fields' => array('id'),
            'limit' => 100
        );
        $response = json_decode(solr_run_search($query));
        

        $phyla = array();

        // error_log(print_r($response->response->docs, true));
        if(isset($response->response->docs)){
            
            foreach ($response->response->docs as $doc) {
                $phyla[] = TaxonConcept::getById($doc->id);
            }
        }

        return $phyla;

    }

}




?>