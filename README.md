# wfo-norm-solr

System for presenting normative lists of WFO names - based on Solr index

This is a replacement for an early MySQL based implementation

## SOLR setup

Assumes you have a running SOLR server v8+

In dev you can create a core with something like
```
./bin/solr create -c wfo
```
It assumes dynamic field generation using the appropriate field name endings apart from one addition.

In the SOLR web admin UI add a copy field 

Source: *
Destination: _text_

In dev start it with a gig of memory ./bin/solr start -m 1g

http://localhost:8983/solr/#/

## PHP in Dev

In dev mode the index.php will act as the .htaccess file is you want to run it using the built in server like this

```
php -S localhost:4000 index.php
```

The query URI for the SOLR server is specified in the config.php file







