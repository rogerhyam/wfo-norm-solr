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

## Importing a new DwC Dump file

There is an importer script (import.php) that will import a new DwC dump produced from the central WFO database. The script is run something like this

```
php import.php -f seed/2021-09.txt -v 2021-09
```

Where the taxonomy file from the Darwin Core dump is seed/2021-09.txt and the -v option is the label for this version of the snapshot.

The importer isn't clever enough to read the meta.xml file and so expects the first line of the file to be the field names. You can add this in by manually creating the list from the meta.xml file as a tab delimited list then using cat to put them together. You can't open that taxonomy file with and editor as it is too big. Something like:

```
cat header_fields.txt taxonomy.txt > 2021-09.txt
```

## The author name cache

There is a MySQL cache table of author names linked to Wikidata. To save visiting Wikidata every time an API query is run. We build/update this cache by running the lookup_authors.php script against the seed taxonomy file like this:

```
php lookup_authors.php -f seed/2021-09.txt
```
This only need be done once after each import.

## Generate download files

To generate download files for the different sub taxa this script needs to be run after each DwC dump is added.

```
php -d memory_limit=1G download_generate_cache.php -v 2021-09
```
This only needs to be run once after each import 






