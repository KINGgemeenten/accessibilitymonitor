#/bin/bash
mkdir /tmp/quaildata
echo $1 > /tmp/quaildata/data
/opt/phantomjs-1.9.2-linux-x86_64/bin/phantomjs --ignore-ssl-errors=yes phantomquail.js $1 >> /tmp/quaildata/data
/usr/bin/php -f parseData.php /tmp/quaildata/data
