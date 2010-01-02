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
# start this script out of the checks directory
# a copy of the config file will be created in your home directory
# to save it from svn updates
#
# exit status is 0 for success, 1 on error or when there is nothing to do
# the script will exit on the first database where there is nothing to do
#
# written by Harald Kleiner, May 2008
#

USERCONFIG=$HOME/keepright.config
###########################
# Copy the default config to the users home directory
# which should be edited to match enviornment
###########################
if [ ! -f "$USERCONFIG" ]; then
    if [ ! -f ../config/config ]; then
        echo ""
        echo "The default config file is not in current directory"
        echo "Was updateDB.sh started from checks directory?"
        echo ""
        exit 1
    fi
    cp ../config/keepright.config.template $USERCONFIG
    echo ""
    echo "This is the first time you have run updateDB.sh"
    echo "Edit the file $USERCONFIG as required then run this again"
    echo ""
    exit 1
fi

# first: import config file shipped with keepright
. ../config/config

# second: import user's config file
. $USERCONFIG
###########################
# Check config settings match the system
###########################
if [ ! -f "$CHECKSDIR/updateDB.sh" ]; then
    echo ""
    echo "Cannot find file $CHECKSDIR/updateDB.sh - is PREFIX in config correct"
    echo ""
    exit 1
fi

FILE="0"
SORTOPTIONS="--temporary-directory=$TMPDIR"

for i do	# loop all given parameter values


	#choose the right parameters as specified in config file
	eval 'URL=${URL_'"${i}"'}'
	eval 'FILE=${FILE_'"${i}"'}'
	eval 'MAIN_DB_NAME=${MAIN_DB_NAME_'"${i}"'}'
	eval 'MIN_SIZE=${MIN_SIZE_'"${i}"'}'
	SCHEMA="schema$i"

	eval 'CAT=${CAT_'"${i}"'}'		# use plain cat as default (dump files not compressed)
	if [ !"$CAT" ]; then
		CAT="cat"
	fi

	if [ !"$FILE" ]; then			# default file name is "[schema].osm"
		FILE="$i.osm"
	fi

	echo "--------------------"
	echo "processing file $FILE for database $MAIN_DB_NAME.$SCHEMA"
	echo "--------------------"

	PGHOST="$MAIN_DB_HOST"
	export PGHOST

	PGDATABASE="$MAIN_DB_NAME"
	export PGDATABASE

	PGUSER="$MAIN_DB_USER"
	export PGUSER

	PGPASSWORD="$MAIN_DB_PASS"
	export PGPASSWORD

	# change the search_path to given schema
	# psql will read and use content of the environment variable PGOPTIONS
	PGOPTIONS="--search_path=$SCHEMA"
	export PGOPTIONS

	# check if connect to database is possible
	psql -c "SELECT error_id FROM public.errors LIMIT 1" > /dev/null 2>&1
	if [ $? != 0 ]; then
		# there was an error, so create the db

		echo "`date` * creating the database $MAIN_DB_NAME"
		# create fresh database and activate PL/PGSQL
		createdb -e -E UTF8 "$MAIN_DB_NAME"
		createlang -e plpgsql "$MAIN_DB_NAME"


	fi

	psql -c "DROP SCHEMA IF EXISTS $SCHEMA CASCADE"
	psql -c "CREATE SCHEMA $SCHEMA"

	# Activate GIS; try for postGIS 8.4 and fallback to postGIS 8.3
	PGINITFILE="/usr/share/postgresql-8.4-postgis/postgis.sql"
	if [ -f "$PGINITFILE" ]; then
		psql -f $PGINITFILE > /dev/null
	else
		PGINITFILE="/usr/share/postgresql-8.3-postgis/lwpostgis.sql"
		if [ -f "$PGINITFILE" ]; then
			psql -f $PGINITFILE > /dev/null
		else
			echo "postGIS init file lwpostgis.sql not found. cannot initianlze DB structures"
			exit 1
		fi
	fi

	psql -c "ALTER TABLE geometry_columns OWNER TO $MAIN_DB_USER"
	psql -c "ALTER TABLE spatial_ref_sys OWNER TO $MAIN_DB_USER;"

	# create tables
	psql -f "$PREFIX/planet/spatial_ref_sys.sql"
	# create tables
	psql -f "$PREFIX/planet/pgsql_simple_schema.sql"


	if [ "$KEEP_OSM" != "0" ]; then

		echo "Using previous downloaded $TMPDIR/$FILE"
		echo "--------------------"

	else

		if [ "$URL" ]; then
			# dowload URL_XY
			echo "`date` * downloading osm file"
			wget --progress=dot:mega --output-document "$TMPDIR/$FILE" "$URL"

		else
			# use planet cutter script to update the dump
			if [ ! -f "$TMPDIR/$FILE" ]; then
				echo "Cutting dump file $TMPDIR/$FILE out of planet file"
				php planet.php --cut "$PLANETFILE" "$i"
			else
				echo "updating dump file $TMPDIR/$FILE"
				php planet.php --update "$i"
			fi
		fi

	fi


	if [ ! -f "$TMPDIR/$FILE" ]; then
		echo "The planet file $TMPDIR/$FILE is not present"
		exit 1
	fi
	# Verify the size of file > MIN_SIZE kilobytes
	SIZE=`ls -alk $TMPDIR/$FILE | awk '{print $5}'`
	if [  $SIZE -lt $MIN_SIZE ]; then
		echo "The planet file $TMPDIR/$FILE is too small (filesize $SIZE less than $MIN_SIZE)"
		exit 1
	fi

	# check if the planet file has changed.
	# if not, we can exit at this point

	# If the sum file does not exist then first time
	# and then can be processed
	if [ ! -f "$TMPDIR/sum-last_${i}" ]; then
		echo "XXXX" > "$TMPDIR/sum-last_${i}"
	fi

	# sum the current file
	cksum "$TMPDIR/$FILE" > "$TMPDIR/sum-current_${i}"

	# see if they are the same
	cmp --silent "$TMPDIR/sum-current_${i}" "$TMPDIR/sum-last_${i}"

	if [ $? != 0 ]; then
		echo File "$TMPDIR/$FILE" is changed
		FILE_CHANGED="1"
	else
		FILE_CHANGED="0"
	fi

	if [ "$KEEP_OSM" != "0" -o "$FILE_CHANGED" != "0" ]; then
		# this file will be the last file next time

		cksum "$TMPDIR/$FILE" > "$TMPDIR/sum-last_${i}"

		echo "`date` * converting osm file into database dumps"
		cd "$TMPDIR"

		"$CAT" "$TMPDIR/$FILE" | "$OSMOSIS_BIN" --read-xml file=/dev/stdin --pl

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
		psql -f $TMPDIR"/pgsql_simple_load.sql"
		cd "$CHECKSDIR"

		PGPASSWORD="shhh!"
		export PGPASSWORD


		cd "$CHECKSDIR"
		echo "`date` * preparing helper tables and columns"
		php prepare_helpertables.php "$i"

		echo "`date` * preparing country helper table"
		php prepare_countries.php "$i"

		echo "`date` * running the checks"
		php run-checks.php "$i"

		cd "$CHECKSDIR"
		echo "`date` * ready."

	else
		echo "File $TMPDIR/$FILE unchanged, nothing to do."
		exit 1
	fi
done

if [ "$FILE" = "0" ]; then
	echo "unknown country code"
	echo "usage: \"./updateDB.sh 1 2 3\""
	echo "will download and install planet dump files "
	echo "you have to configure new country codes in the config file "
	echo "if you want to add new ones except the existing codes"
	exit 1
fi

exit 0