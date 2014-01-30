#!/bin/bash
cd /opt/drupal_feed_to_nutch_seed

#get feed from drupal site
/usr/bin/wget http://dev.wrl.org/zoekindex.xml

#filter all urls
/bin/cat zoekindex.xml | sed -n 's/.*<link>\(.*\)<\/link>.*/\1/p' |sort > /opt/apache-nutch-1.6/urls/seed.txt

#cleanup
rm -r -f /opt/drupal_feed_to_nutch_seed/zoekindex.xm*
