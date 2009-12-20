#!/bin/bash
#
# script for updating multiple keepright databases
#


for i do	# loop all given parameter values

	case "$i" in

		# Europe
		EU)
			./updateDB.sh 1 2 3 4 5 6 7 8 9 10 11 13 14 15 18 19 >> make_EU.log 2>&1
			php export_errors.php EU >> make_EU.log 2>&1
			./updateWebDB.sh EU >> make_EU.log 2>&1
		;;

		# US states part 1
		US1)
			./updateDB.sh 21 22 23 24 25 26 27 28 29 30 31 32 33 34 35 36 37 38 39 40 >> make_US.log 2>&1
			php export_errors.php US >> make_US.log 2>&1
			./updateWebDB.sh US >> make_US.log 2>&1
		;;

		# US states part 2
		US2)
			./updateDB.sh 41 42 43 44 45 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 69 >> make_US.log 2>&1
			php export_errors.php US >> make_US.log 2>&1
			./updateWebDB.sh US >> make_US.log 2>&1
		;;

		# Canada and Central America
                CA)
               		./updateDB.sh 20 46 68 >> make_CA.log 2>&1
			php export_errors.php CA >> make_CA.log 2>&1
			php export_errors.php XG >> make_CA.log 2>&1
                         ./updateWebDB.sh CA XG >> make_CA.log 2>&1
                ;;


		# Africa, South America and Asia
                XACD)
               		./updateDB.sh 17 47 48 49 >> make_XACD.log 2>&1
			php export_errors.php XA >> make_XACD.log 2>&1
			php export_errors.php XC >> make_XACD.log 2>&1
			php export_errors.php XD >> make_XACD.log 2>&1
                        ./updateWebDB.sh XA XC XD >> make_XACD.log 2>&1
                ;;

		# Australia
                AU)
               		./updateDB.sh 50 >> make_AU.log 2>&1
			php export_errors.php AU >> make_AU.log 2>&1
                        ./updateWebDB.sh AU >> make_AU.log 2>&1
                ;;

	esac
done
