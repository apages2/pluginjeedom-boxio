#!/bin/bash
data=""
for var in "$@"
do
	data="${data}${var}&"
done	
curl -G -k -s "#ip_master#/plugins/boxio/core/php/jeeboxio.php" -d "apikey=#apikey#&${data}"
exit 0