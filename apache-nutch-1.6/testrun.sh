cd /opt/apache-nutch-1.6
bin/nutch solrindex http://127.0.0.1:8983/solr/collection1 crawl/crawldb -linkdb crawl/linkdb crawl/segments/*
