#!/bin/bash
#
# script for updating multiple keepright databases
#

#
# call ./make.sh [--loop] [ AllButUSAndAU | US | AU ] [17]
# to make keepright check one or more databases and upload the results
# use the parameter --loop to make make.sh run until a file called
# /tmp/stop_keepright is found.
# optionally provide a schema name [17] where to start. This is useful
# for restarting at a given position in the loop
#
# call this script out of the checks directory


# read keepright config files
USERCONFIG=$HOME/keepright.config

# first: import config file shipped with keepright
. ../config/config

# second: import user's config file
. $USERCONFIG


PGHOST="$MAIN_DB_HOST"
export PGHOST

PGUSER="$MAIN_DB_USER"
export PGUSER

PGPASSWORD="$MAIN_DB_PASS"
export PGPASSWORD


FIRSTRUN=1
STARTSCHEMA="$3"


# updates one single schema depending on the (global) variable
# schema containing the name of the schema
process_schema() {

	if [ $FIRSTRUN -ne 1 -o "$schema" = "$STARTSCHEMA" ] ; then

		echo "updating schema $schema";
		FIRSTRUN=0

		svn up ../
		./updateDB.sh $schema >> make_$schema.log 2>&1
		php export_errors.php $schema >> make_$schema.log 2>&1
		php webUpdateClient.php --remote $schema >> make_$schema.log 2>&1 &
	fi


	if [ -f /tmp/stop_keepright ]; then
		exit;
	fi
}




while { [ "$1" != "--loop" ] && [ $FIRSTRUN -eq 1 ] ; } || { [ "$1" = "--loop" ] && [ ! -f /tmp/stop_keepright ] ; } ; do

	for i do	# loop all given parameter values

		case "$i" in

			AllButUSAndAU)

				for schema in 86 87 2 90 91 92 93 72 4 5 6 7 76 77 78 79 80 81 82 83 84 85 88 89 15 94 95 96 97 98 99 18 19 46 68 17 47 48 73 74 75
				do
					process_schema
				done

				pg_dump --table=errors osm_EU > "$RESULTSDIR/osm_EU.errors.sql"
				pg_dump --table=errors osm_XA > "$RESULTSDIR/osm_XA.errors.sql"
				pg_dump --table=errors osm_XC > "$RESULTSDIR/osm_XC.errors.sql"
				pg_dump --table=errors osm_XD > "$RESULTSDIR/osm_XD.errors.sql"
				pg_dump --table=errors osm_XG > "$RESULTSDIR/osm_XG.errors.sql"

			;;
			US)

				for schema in 20 21 22 23 24 25 26 27 28 29 30 31 32 33 34 35 36 37 38 39 40 41 42 43 44 45 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 69
				do
					process_schema
				done

				pg_dump --table=errors osm_US > "$RESULTSDIR/osm_US.errors.sql"

			;;
			AU)

				for schema in 50
				do
					process_schema
				done

				pg_dump --table=errors osm_AU > "$RESULTSDIR/osm_AU.errors.sql"

			;;
		esac
	done
	FIRSTRUN=0
done