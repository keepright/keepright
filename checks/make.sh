#!/bin/bash
#
# script for updating multiple keepright databases
#

#./updateDB.sh AT XT >> make.log 2>&1


for i do	# loop all given parameter values

	case "$i" in

		EU)
			./updateDB.sh AT CZ DE DK FI NL PL SE XH XJ XK XL XM XN XO XP XQ XR XS XT >> make_EU.log 2>&1
			php export_errors.php EU >> make_EU.log 2>&1
			./updateWebDB.sh EU >> make_EU.log 2>&1
		;;

		US)
			./updateDB.sh USAL USAK USAZ USAR USCA USCO USFL USGA USGU USHI USID USIL USIN USIA USKS USKY USLA USMA USMI USMN USMS USMO USMT USNE USNV USNJ USNM USNY USNC USND USOH USOK USOR USPA USSC USSD USTN USTX USUT USVA USWA USWV USWI USWY XE XF >> make_US.log 2>&1
			php export_errors.php US >> make_US.log 2>&1
			./updateWebDB.sh US >> make_US.log 2>&1
		;;

                CA)
               		./updateDB.sh CA XG >> make.log 2>&1
			php export_errors.php CA XG >> make.log 2>&1
                        ./updateWebDB.sh CA XG >> make.log 2>&1
                ;;
	esac
done
