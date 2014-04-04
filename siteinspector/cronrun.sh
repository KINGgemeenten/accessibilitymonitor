#!/bin/bash
cd /opt/siteinspector

#START BATCH
#GET UNPROCESSED URLS FROM NUTCH
export ITEMS=`/usr/bin/php -f nutch2quail.php`

#ITERATE OVER UNPROCESSED NUTCH URLS
for i in $ITEMS
do
  #CHECK SITE AND WRITE TESTRESULTS TO SOLR (PHANTOMCORE)
  echo $i
  /opt/siteinspector/checksite.sh $i

  #UPDATE TEST FIELD IN SOLR NUTCH RECORD (nutch)
  export UPDATE=`/usr/bin/php -f updatejson.php $i`
  /usr/bin/curl 'http://vps38899.public.cloudvps.com:8080/solr/nutch/update?commit=true' -H 'Content-type:application/json' -d $UPDATE
  echo $UPDATE

done
#BATCH READY
