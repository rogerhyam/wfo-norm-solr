<?php

function get_replaces_replaced($wfo_root_id, $classification_id_s){

    $out = array();

    // get a list in descending order
    $query = array(
        'query' => 'wfo_id_s:' . $wfo_root_id,
        'sort' => 'classification_id_s desc'
    );
    $response = json_decode(solr_run_search($query));
    
    if($response->response->numFound > 1){

        // work through the list to find self
        for ($i=0; $i < count($response->response->docs) ; $i++) {
            $v = $response->response->docs[$i];
            if($v->classification_id_s == $classification_id_s){
                $my_index = $i;
            }
        }

        // is there a newer version?
        if($my_index-1 >= 0){
            // it is replaced by something. But what?
            // if a taxon has been sunk into synonymy then it isn't replaced by the 
            // synonym it is replaced by the accepted taxon
            $replacement = $response->response->docs[$my_index-1];
            if(property_exists($replacement, "role_s") && $replacement->role_s == 'synonym'){
                $out['dc:isReplacedBy'] = get_uri($replacement->accepted_id_s);
            }else{
                $out['dc:isReplacedBy'] = get_uri($replacement->id);
            }
        }

        // is there an older version
        if($my_index+1 < count($response->response->docs)){

            // it replaces something. But what?
            // if a NAME has been raised from synonym to being a full TAXON
            // then it doesn't replace the synyonyms NAME in the previous version
            $theReplaced = $response->response->docs[$my_index+1];
            
            error_log($theReplaced->id);

            if(property_exists($theReplaced, "role_s") && $theReplaced->role_s == 'synonym'){
                // tricky situation. Accepted taxon is errected from previous synonym
                // this taxon is proparte synonym of whatever the accepted taxon was but it is a taxon-taxon relationship
                // Could be "errected from" - split from 
                // $taxon->derivedFrom = array( 'uri' => get_uri($theReplaced->acceptedNameUsageID_s . '-'. $theReplaced->classification_id_s));
                $out['dc:source'] = get_uri($theReplaced->accepted_id_s);
            }else{                    
                // easy case. Accepted replaces old version of accepted.
                // this also covers other possible taxonomic statuses like Unknown status.
                // $taxon->replaces = array( 'uri' => get_uri($theReplaced->id)) ;
                $out['dc:replaces'] = get_uri($theReplaced->id);
            }
        }
            
    }
        
    return $out;

}
