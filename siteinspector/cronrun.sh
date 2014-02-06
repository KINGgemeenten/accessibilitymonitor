#!/bin/bash

#START BATCH
#GET UNPROCESSED URLS FROM NUTCH
export ITEMS=`/usr/bin/php -f nutch2quail.php`

#ITERATE OVER UNPROCESSED NUTCH URLS
for i in $ITEMS
do

  #CHECK SITE
  /opt/siteinspector/checksite.sh $i

  #UPDATE TEST FIELD IN SOLR NUTCH RECORD
  export UPDATE='[{"url":"$1","id":"$1","tested":{"set":1}}]'
  curl 'localhost:8983/solr/update?commit=true' -H 'Content-type:application/json' -d $UPDATE

done
#BATCH READY
