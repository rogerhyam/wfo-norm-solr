<?php


function process_taxon($taxon){

    $cache_path = get_cache_path($taxon);

    // if we haven't got it create it
    if(!file_exists($cache_path . ".zip")){

        if(php_sapi_name() === 'cli'){
            echo "Creating $cache_path.zip \n";
        }
        
        $file = fopen($cache_path . '.csv', 'w');

        // put the headers in
        fputcsv($file, 
            // CHANGES - don't forget the includes/meta.xml file needs to match this
            array(
                "taxonID",
                "scientificNameID",
                "taxonomicStatus",
                "parentNameUsageID",
                "acceptedNameUsageID",
                "taxonRank",
                "scientificName",
                "scientificNameAuthorship",
                "namePublishedIn",
                "nameAccordingToID",
                "specificEpithet",
                "infraspecificEpithet",
                "higherClassification",
                "kingdom",
                "phylum",
                "class",
                "order",
                "family",
                "genus",
                "subgenus"
            )
        );
        create_csv_file($taxon, $file);
        fclose($file);

        // now build a zip file
        $zip = new ZipArchive();
        $zip->open($cache_path . '.zip', ZipArchive::CREATE);
        $zip->addFile($cache_path . '.csv', 'taxa.csv');
        $zip->addFile('include/meta.xml', 'meta.xml');
        $zip->close();

        // remove the csv file
        unlink($cache_path . '.csv');


    }

    // send the contents to the user
    if(php_sapi_name() !== 'cli'){
        header("Content-type: application/octet-stream");
        header('Content-Disposition: attachment; filename="' . $taxon->id . '.zip' . '"');
        readfile($cache_path . '.zip');
        exit;
    }else{
        // called on the commandline just report success.
        if(file_exists($cache_path . '.zip')){
            echo "File $cache_path.zip exists \n";
        }else{
            echo "Failed to created file $cache_path.zip \n";
            exit;
        }
    }

}

function create_csv_file($taxon, $file){

    // write self to file
    fputcsv($file, get_csv_row($taxon));

    // write synonyms to file
    $query = array(
        'query' => 'acceptedNameUsageID_s:' . $taxon->taxonID_s, 
        'filter' => 'snapshot_version_s:' . $taxon->snapshot_version_s,
        'limit' => 1000000,
        'sort' => 'id asc'
    );
    $response = json_decode(solr_run_search($query));

    if($response->response->numFound > 0){
        foreach ($response->response->docs as $syn){
            add_higher_classification($syn, $taxon);
            fputcsv($file, get_csv_row($syn));
        }   
    }

    // write children to file
    $query = array(
        'query' => 'parentNameUsageID_s:' . $taxon->taxonID_s,
        'filter' => 'snapshot_version_s:' . $taxon->snapshot_version_s,
        'limit' => 1000000,
        'sort' => 'id asc'
    );
    $response = json_decode(solr_run_search($query));
    if($response->response->numFound > 0){
        foreach ($response->response->docs as $kid) {
            // fputcsv($file, get_csv_row($kid));

            // when we load a kid we give it the full ancestry
            add_higher_classification($kid, $taxon);

            create_csv_file($kid, $file);
        }
    }

}

function get_csv_row($taxon){

    $vals = array();

    // taxon ID - using the versioned taxon ids
    $vals[] = get_uri($taxon->id);

    // scientificNameID
    $vals[] = isset($taxon->taxonID_s) ? get_uri($taxon->taxonID_s) : "";

    // taxonomicStatus
    $vals[] = isset($taxon->taxonomicStatus_s) ? strtolower($taxon->taxonomicStatus_s) : "";

    // parentNameUsageID_s
    $vals[] = isset($taxon->parentNameUsageID_s) ? get_uri($taxon->parentNameUsageID_s . '-' . $taxon->snapshot_version_s) : "";

    // acceptedNameUsageID
    $vals[] = isset($taxon->acceptedNameUsageID_s) ? get_uri($taxon->acceptedNameUsageID_s . '-' . $taxon->snapshot_version_s) : "";

    // taxonRank_s
    $vals[] = isset($taxon->taxonRank_s) ? strtolower($taxon->taxonRank_s ) : "";

    // scientificName
    $vals[] = isset($taxon->scientificName_s) ? $taxon->scientificName_s : "";

    // scientificNameAuthorship
    $vals[] = isset($taxon->scientificNameAuthorship_s) ? $taxon->scientificNameAuthorship_s : "";

    // namePublishedIn_s
    $vals[] = isset($taxon->namePublishedIn_s) ? $taxon->namePublishedIn_s : "";

    // nameAccordingToID_s - N.B. not using value from WFO but going straight to source
    $vals[] = isset($taxon->references_s) ? $taxon->references_s : "";

    // specificEpithet
    $vals[] = isset($taxon->specificEpithet_s) ? $taxon->specificEpithet_s : "";

    // infraspecificEpithet
    $vals[] = isset($taxon->infraspecificEpithet_s) ? $taxon->infraspecificEpithet_s : "";

    // higherClassification
    $lineage = array();
    if(isset($taxon->higherClassification)){
        foreach ($taxon->higherClassification as $place) {
            $lineage[$place['rank']] = $place['name'];
        }
        $vals[] = implode(';', array_values($lineage));
    }else{
        $vals[] = ""; // higherClassification
    }

    $vals[] = isset($lineage['kingdom']) ? $lineage['kingdom'] : "Plantae";
    $vals[] = isset($lineage['phylum']) ? $lineage['phylum'] : "";
    $vals[] = isset($lineage['class']) ? $lineage['class'] : "";
    $vals[] = isset($lineage['order']) ? $lineage['order'] : "";
    $vals[] = isset($lineage['family']) ? $lineage['family'] : "";
    $vals[] = isset($lineage['genus']) ? $lineage['genus'] : "";
    $vals[] = isset($lineage['subgenus']) ? $lineage['subgenus'] : "";


    return $vals;
}

function add_higher_classification($kid, $parent){

    // if we don't have the tail to the top yet
    // we have to build it
    if(!isset($parent->higherClassification)){
        $parent->higherClassification = build_higher_classification($parent, array());
    }

    $kid->higherClassification = $parent->higherClassification;
    $kid->higherClassification[] = array(
            "rank" => strtolower($parent->taxonRank_s),
            "name" => $parent->scientificName_s
    );


}

function build_higher_classification($taxon, $lineage){

    // have we reached the top of the pile?
    if(!isset($taxon->parentNameUsageID_s)) return $lineage;

    // get the parent and add it on the beginning
    $parent = solr_get_doc_by_id($taxon->parentNameUsageID_s . '-' . $taxon->snapshot_version_s);
    if(!$parent) return $lineage;

    $place = array(
            "rank" => strtolower($parent->taxonRank_s),
            "name" => $parent->scientificName_s
    );

    array_unshift($lineage, $place);

    return build_higher_classification($parent, $lineage);
 
    // repeat for the next parent

}

function get_cache_path($taxon){

    $cache_dir = 'cache/' . substr($taxon->id, -5);
       
    if(!file_exists($cache_dir)){
        mkdir($cache_dir, 0777, true);
    }

    return  $cache_dir . '/' . $taxon->id ;

}

