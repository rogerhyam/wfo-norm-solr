# wfo-norm-solr

System for presenting normative lists of WFO names - based on Solr index

This is a replacement for an early MySQL based implementation

## SOLR setup

Assumes you have a running SOLR server v8+

In dev you can create a core with something like
```
./bin/solr create -c wfo
```
It assumes dynamic field generation using the appropriate field name endings apart from one addition. In the SOLR web admin UI add a copy field 
Source: *
Destination: _text_

The query URI for the SOLR server is specified in the config.php file







