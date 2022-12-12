<?php

/**
 * 
 * 
 * @param taxon is a solr doc
 */
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
        'query' => 'accepted_id_s:' . $taxon->id,
        'limit' => 1000000,
        'sort' => 'id asc'
    );
    $response = json_decode(solr_run_search($query));

    if($response->response->numFound > 0){
        foreach ($response->response->docs as $syn){
            fputcsv($file, get_csv_row($syn));
        }   
    }

    // write children to file
    $query = array(
        'query' => 'parent_id_s:' . $taxon->id,
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
    $vals[] = isset($taxon->wfo_id_s) ? get_uri($taxon->wfo_id_s) : "";

    // taxonomicStatus
    $vals[] = isset($taxon->role_s) ? strtolower($taxon->role_s) : "";

    // parent_id_s
    $vals[] = isset($taxon->parent_id_s) ? get_uri($taxon->parent_id_s) : "";

    // acceptedNameUsageID
    $vals[] = isset($taxon->accepted_id_s) ? get_uri($taxon->accepted_id_s) : "";

    // taxonRank_s
    $vals[] = isset($taxon->rank_s) ? $taxon->rank_s : "";

    // scientificName
    $vals[] = isset($taxon->full_name_string_plain_s) ? $taxon->full_name_string_plain_s : "";

    // scientificNameAuthorship
    $vals[] = isset($taxon->authors_string_s) ? $taxon->authors_string_s : "";

    // namePublishedIn_s
    $vals[] = isset($taxon->citation_micro_s) ? $taxon->citation_micro_s : "";

    // nameAccordingToID_s - N.B. not using value from WFO but going straight to source
    // FIXME - the meaning of this is ambiguous so excluding for now
    $vals[] = ""; // isset($taxon->references_s) ? $taxon->references_s : "";

    // specificEpithet
    if($taxon->rank_s == 'species'){
        $vals[] = isset($taxon->name_string_s) ? $taxon->name_string_s : "";
    }else{
        $vals[] = isset($taxon->species_string_s) ? $taxon->species_string_s : "";
    }
    
    if(isset($taxon->species_string_s)){
        // we are below species
        $vals[] = isset($taxon->name_string_s) ? $taxon->name_string_s : "";
    }

    $vals[] = isset($taxon->placed_in_kingdom_s) ? $taxon->placed_in_kingdom_s : "Plantae"; // don't think this will ever be set
    $vals[] = isset($taxon->placed_in_phylum_s) ? $taxon->placed_in_phylum_s : "";
    $vals[] = isset($taxon->placed_in_class_s) ? $taxon->placed_in_class_s : "";
    $vals[] = isset($taxon->placed_in_order_s) ? $taxon->placed_in_order_s : "";
    $vals[] = isset($taxon->placed_in_family_s) ? $taxon->placed_in_family_s : "";
    $vals[] = isset($taxon->placed_in_genus_s) ? $taxon->placed_in_genus_s : "";
    $vals[] = isset($taxon->placed_in_subgenus_s) ? $taxon->placed_in_subgenus_s : "";

    return $vals;
}

function get_cache_path($taxon){

    $cache_dir = 'cache/' . substr($taxon->id, -7);
       
    if(!file_exists($cache_dir)){
        mkdir($cache_dir, 0777, true);
    }

    return  $cache_dir . '/' . $taxon->id ;

}

