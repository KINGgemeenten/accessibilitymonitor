#!/bin/bash

#START BATCH
#GET UNPROCESSED URLS FROM NUTCH
export ITEMS=`/usr/bin/php -f nutch2quail.php`

#ITERATE OVER UNPROCESSED NUTCH URLS
for i in $ITEMS
do

  #CHECK SITE AND WRITE TESTRESULTS TO SOLR (PHANTOMCORE)
  /opt/siteinspector/checksite.sh $i

  #UPDATE TEST FIELD IN SOLR NUTCH RECORD (COLLECTION1)
  export UPDATE='[{"url":"$i","id":"$i","tested":{"set":1}}]'
  curl 'localhost:8983/solr/update?commit=true' -H 'Content-type:application/json' -d $UPDATE
  echo $UPDATE
done
#BATCH READY
