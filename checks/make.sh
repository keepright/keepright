#!/bin/bash
#
# script for updating multiple keepright databases
#

#
# call ./make.sh [--loop] EU US1 US2
# to make keepright check one or more databases and upload the results
# use the parameter --loop to make make.sh run until a file called
# /tmp/stop_keepright is found.
#
# call this script out of the checks directory

FIRSTRUN=1

while { [ "$1" != "--loop" ] && [ $FIRSTRUN -eq 1 ] ; } || { [ "$1" = "--loop" ] && [ ! -f /tmp/stop_keepright ] ; } ; do

	FIRSTRUN=0
	for i do	# loop all given parameter values

		svn up ../

		case "$i" in

			# Europe
			EU)
				./updateDB.sh 1 2 3 4 5 6 7 8 9 10 11 13 14 15 18 19 >> make_EU.log 2>&1
				php export_errors.php --db EU >> make_EU.log 2>&1
				php webUpdateClient.php --remote --db EU >> make_EU.log 2>&1
			;;

			# US states part 1
			US1)
				./updateDB.sh 21 22 23 24 25 26 27 28 29 30 31 32 33 34 35 36 37 38 39 40 >> make_US1.log 2>&1
				php export_errors.php --db US >> make_US1.log 2>&1
				php webUpdateClient.php --remote --db US >> make_US1.log 2>&1
			;;

			# US states part 2
			US2)
				./updateDB.sh 41 42 43 44 45 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 69 >> make_US2.log 2>&1
				php export_errors.php --db US >> make_US2.log 2>&1
				php webUpdateClient.php --remote --db US >> make_US2.log 2>&1
			;;

			# Canada and Central America
			CA)
				./updateDB.sh 20 46 68 >> make_CA.log 2>&1
				php export_errors.php --db CA >> make_CA.log 2>&1
				php export_errors.php --db XG >> make_CA.log 2>&1
				php webUpdateClient.php --remote --db CA >> make_CA.log 2>&1
				php webUpdateClient.php --remote --db XG >> make_CA.log 2>&1
			;;


			# Africa, South America and Asia
			XACD)
				./updateDB.sh 17 47 48 49 >> make_XACD.log 2>&1
				php export_errors.php --db XA >> make_XACD.log 2>&1
				php export_errors.php --db XC >> make_XACD.log 2>&1
				php export_errors.php --db XD >> make_XACD.log 2>&1
				php webUpdateClient.php --remote --db XA >> make_XACD.log 2>&1
				php webUpdateClient.php --remote --db XC >> make_XACD.log 2>&1
				php webUpdateClient.php --remote --db XD >> make_XACD.log 2>&1
			;;

			# Australia
			AU)
				./updateDB.sh 50 >> make_AU.log 2>&1
				php export_errors.php --db AU >> make_AU.log 2>&1
				php webUpdateClient.php --remote --db AU >> make_AU.log 2>&1
			;;

		esac
	done
done