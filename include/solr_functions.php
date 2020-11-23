<?php

// common functions for calling the SOLR service

function solr_add_docs($docs){
    $solr_update_uri = SOLR_QUERY_URI . '/update';
    $response = curl_post_json($solr_update_uri, json_encode($docs));
    return $response->body;
}

function solr_run_search($query){
    $solr_query_uri = SOLR_QUERY_URI . '/query';
    $response = curl_post_json($solr_query_uri, json_encode($query));
    return $response->body;
}

function solr_get_doc_by_id($id){
    // this uses the RealTime Get feature
    $solr_query_uri = SOLR_QUERY_URI . '/get?id=' . $id;
    $ch = get_curl_handle($solr_query_uri);
    $response = run_curl_request($ch);
    if(isset($response->body)){
        $body = json_decode($response->body);
        if(isset($body->doc)){
            return $body->doc;
        }
    }
    return null;
}

function solr_commit($in = 0){
    $solr_update_uri = SOLR_QUERY_URI . '/update';
    $command = new stdClass();
    $command->commit = new stdClass();
    $response = curl_post_json($solr_update_uri, json_encode($command));
    return $response->body;
}
