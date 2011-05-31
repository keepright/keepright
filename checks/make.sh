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

			cetest)

				for schema in 39 24 27 54 53 60 52 57 51 42 26 46 35 55 96 38 59 33 40 61 41 58 22 100 34 62 97 21 43 44 63 101 102 45 65 98 56 99 37 95 32 18 31 25 64 94 2 73 17 69 15 86 30 90 76
				do
					process_schema
				done

				pg_dump --table=errors osm > "$RESULTSDIR/errors_cetest.sql"

			;;
			serverce)

				for schema in 87 92 89 68 83 79 47 88 93 28 91 29 81 82 36 77 80 85 4 72 78 74 48 19 7 84 75 20 23
				do
					process_schema
				done

				pg_dump --table=errors osm > "$RESULTSDIR/errors_serverce.sql"

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