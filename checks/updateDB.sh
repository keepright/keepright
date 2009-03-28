#!/bin/bash
#
# script for updating a local open streetmap database
#
# this tool uses osmosis (http://wiki.openstreetmap.org/index.php/Osmosis)
# osmosis can be downloaded from http://gweb.bretth.com/osmosis-latest.tar.gz
# a schema definition for the database is part of the osmosis source distribution
#
# this script will download a dump file, insert it into the database, assemble some
# helper tables used for error checks and finally run the error checks on the database
#
# written by Harald Kleiner, May 2008
#

# import config file
. config

FILE="0"
SORTOPTIONS="--temporary-directory=$TMPDIR"

for i do	# loop all given parameter values


	#choose the right parameters as specified in config file
	eval 'URL=${URL_'"${i}"'}'
	eval 'FILE=${FILE_'"${i}"'}'
	eval 'MAIN_DB_NAME=${MAIN_DB_NAME_'"${i}"'}'
	eval 'CAT=${CAT_'"${i}"'}'


	if [ "$FILE" != "0" ]; then

		echo "--------------------"
		echo "processing file $FILE"
		echo "--------------------"

                if [ ! -n "KEEP_OSM" ]; then
                    echo "`date` * downloading osm file"
                    wget --progress=dot:mega --output-document "$TMPDIR/$FILE" "$URL"
                else
                    echo "Using previous downloaded $TMPDIR/$FILE"
                    echo "--------------------"
                fi

                if [ ! -f "$TMPDIR/$FILE" ]; then
                    echo "The download file $TMPDIR/$FILE is not present"
                    exit 1
                fi

		echo "`date` * truncating database"
		cd "$TMPDIR"
		java -jar osmosis.jar --truncate-pgsql host="$MAIN_DB_HOST" database="$MAIN_DB_NAME" user="$MAIN_DB_USER" password="$MAIN_DB_PASS"


		echo "`date` * preparing table structures"
		cd "$CHECKSDIR"
		php prepare_tablestructure.php "$i"

		echo "`date` * converting osm file into database dumps"
		cd "$TMPDIR"

		"$CAT" "$TMPDIR/$FILE" | java -jar osmosis.jar -p pl --read-xml file=/dev/stdin --pl

		echo "`date` * joining way_nodes and node coordinates"
		sort "$SORTOPTIONS" -n -k 2 pgimport/way_nodes.txt > pgimport/way_nodes_sorted.txt
		rm pgimport/way_nodes.txt
		sort "$SORTOPTIONS" -n -k 1 pgimport/nodes.txt > pgimport/nodes_sorted.txt
		rm pgimport/nodes.txt
		join -t "	" -e NULL -a 1 -1 2 -o 1.1,0,1.3,2.5,2.6,2.7,2.8 pgimport/way_nodes_sorted.txt pgimport/nodes_sorted.txt > pgimport/way_nodes2.txt
		rm pgimport/way_nodes_sorted.txt

		echo "`date` * joining ways with coordinates of first and last node"
		sort "$SORTOPTIONS" -t "	" -n -k 4 pgimport/ways.txt > pgimport/ways_sorted.txt
		rm pgimport/ways.txt
		join -t "	" -e NULL -a 1 -1 4 -o 1.1,1.2,1.3,0,1.5,2.5,2.6,2.7,2.8,1.6 pgimport/ways_sorted.txt pgimport/nodes_sorted.txt > pgimport/ways2.txt
		sort "$SORTOPTIONS" -t "	" -n -k 5 pgimport/ways2.txt > pgimport/ways_sorted.txt
		rm pgimport/ways2.txt
		join -t "	" -e NULL -1 5 -o 1.1,1.2,1.3,1.4,0,1.6,1.7,1.8,1.9,2.5,2.6,2.7,2.8,1.10 pgimport/ways_sorted.txt pgimport/nodes_sorted.txt > pgimport/ways.txt
		rm pgimport/ways_sorted.txt

		echo "`date` * loading database dumps"
		PGPASSWORD="$MAIN_DB_PASSWORD"
		psql -f "$PSQL_LOAD_SCRIPT" "$MAIN_DB_NAME" "$MAIN_DB_USER"

		cd "$CHECKSDIR"
		echo "`date` * preparing helper tables and columns"
		php prepare_helpertables.php "$i"

		echo "`date` * running the checks"
		php run-checks.php "$i"

		./updateWebDB.sh "${i}"

		cd "$CHECKSDIR"
		echo "`date` * ready."
	fi

done

if [ "$FILE" = "0" ]; then
	echo "unknown country code"
	echo "usage: \"./updateDB.sh AT DE\""
	echo "will download and install Austrian and German planet dump "
	echo "you have to configure new country codes in the config file "
	echo "if you want to add new ones except the existing codes AT, DE, EU "
fi

