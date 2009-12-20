#!/bin/bash
#
# script for updating a local keepright database from a previously
# created error_view.txt dump file in the results directory
#
# written by Harald Kleiner, March 2009
#

# import config file
USERCONFIG=$HOME/keepright.config
. $USERCONFIG

FILE="0"

for i do	# loop all given parameter values

	#choose the right parameters as specified in config file
	eval 'URL=${URL_'"${i}"'}'
	eval 'FILE=${FILE_'"${i}"'}'
	eval 'MAIN_DB_NAME=${MAIN_DB_NAME_'"${i}"'}'
	eval 'CAT=${CAT_'"${i}"'}'

	if [ "$FILE" != "0" ]; then

		echo "`date` * creating/swapping error_view table"
		# ensure all tables exist and empty old error_view

		echo "
			CREATE TABLE IF NOT EXISTS comments_""$MAIN_DB_NAME"" (
			\`schema\` varchar(10) NOT NULL DEFAULT '',
			error_id int(11) NOT NULL,
			state enum('ignore_temporarily','ignore') default NULL,
			\`comment\` text,
			\`timestamp\` timestamp NOT NULL default CURRENT_TIMESTAMP,
			ip varchar(255) default NULL,
			user_agent varchar(255) default NULL,
			KEY \`schema\` (\`schema\`),
			KEY error_id (error_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			CREATE TABLE IF NOT EXISTS comments_historic_""$MAIN_DB_NAME"" (
			\`schema\` varchar(10) NOT NULL DEFAULT '',
			error_id int(11) NOT NULL,
			state enum('ignore_temporarily','ignore') default NULL,
			\`comment\` text,
			\`timestamp\` timestamp NOT NULL default CURRENT_TIMESTAMP,
			ip varchar(255) default NULL,
			user_agent varchar(255) default NULL,
			KEY \`schema\` (\`schema\`),
			KEY error_id (error_id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			CREATE TABLE IF NOT EXISTS error_types_""$MAIN_DB_NAME"" (
			error_type int(11) NOT NULL,
			error_name varchar(100) NOT NULL,
			error_description text NOT NULL,
			PRIMARY KEY  (error_type)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			CREATE TABLE IF NOT EXISTS error_view_""$MAIN_DB_NAME""_old (
			\`schema\` varchar(10) NOT NULL DEFAULT '',
			error_id int(11) NOT NULL,
			db_name varchar(50) NOT NULL,
			error_type int(11) NOT NULL,
			error_name varchar(100) NOT NULL,
			object_type enum('node','way','relation') NOT NULL,
			object_id bigint(64) NOT NULL,
			state enum('new','cleared','ignored','reopened') NOT NULL,
			description text NOT NULL,
			first_occurrence datetime NOT NULL,
			last_checked datetime NOT NULL,
			object_timestamp datetime NOT NULL,
			lat int(11) NOT NULL,
			lon int(11) NOT NULL,
			KEY \`schema\` (\`schema\`),
			KEY error_id (error_id),
			KEY lat (lat),
			KEY lon (lon),
			KEY error_type (error_type),
			KEY state (state)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			CREATE TABLE IF NOT EXISTS error_view_""$MAIN_DB_NAME"" (
			\`schema\` varchar(10) NOT NULL DEFAULT '',
			error_id int(11) NOT NULL,
			db_name varchar(50) NOT NULL,
			error_type int(11) NOT NULL,
			error_name varchar(100) NOT NULL,
			object_type enum('node','way','relation') NOT NULL,
			object_id bigint(64) NOT NULL,
			state enum('new','cleared','ignored','reopened') NOT NULL,
			description text NOT NULL,
			first_occurrence datetime NOT NULL,
			last_checked datetime NOT NULL,
			object_timestamp datetime NOT NULL,
			lat int(11) NOT NULL,
			lon int(11) NOT NULL,
			KEY \`schema\` (\`schema\`),
			KEY error_id (error_id),
			KEY lat (lat),
			KEY lon (lon),
			KEY error_type (error_type),
			KEY state (state)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			RENAME TABLE error_view_""$MAIN_DB_NAME""_old TO error_view_""$MAIN_DB_NAME""_shadow;
			DELETE FROM error_view_""$MAIN_DB_NAME""_shadow WHERE db_name='""$MAIN_DB_NAME""';

			ALTER TABLE error_view_""$MAIN_DB_NAME""_shadow DISABLE KEYS;

		" | mysql --user="$WEB_DB_USER" --password="$WEB_DB_PASS" "$WEB_DB_NAME"

		# load new error view
		cd ../web
		echo "`date` * loading error_view data"
		php updateTables.php "$ERROR_VIEW_FILE"_"$MAIN_DB_NAME".txt "$MAIN_DB_NAME" error_view

		# toggle tables and empty error_types
		echo "
			ALTER TABLE error_view_""$MAIN_DB_NAME""_shadow ENABLE KEYS;

			RENAME TABLE error_view_""$MAIN_DB_NAME"" TO error_view_""$MAIN_DB_NAME""_old;
			RENAME TABLE error_view_""$MAIN_DB_NAME""_shadow TO error_view_""$MAIN_DB_NAME"";

			TRUNCATE TABLE error_types_""$MAIN_DB_NAME"";
		" | mysql --user="$WEB_DB_USER" --password="$WEB_DB_PASS" "$WEB_DB_NAME"

		php updateTables.php "$ERROR_TYPES_FILE"_"$MAIN_DB_NAME".txt "$MAIN_DB_NAME" error_types
		cd "$CHECKSDIR"

		if [ -f postWebDB.sh ]; then
			echo "Start user post script"
			./postWebDB.sh "$MAIN_DB_NAME" "$WEB_DB_NAME" "$WEB_DB_USER" "$WEB_DB_PASS"
			# In case custom script not did not put back
			# the correct directory
			cd "$CHECKSDIR"
		fi

		echo "`date` * ready."
	fi
done

if [ "$FILE" = "0" ]; then
	echo "unknown country code"
	echo "usage: \"./updateWebDB.sh [--full] AT\""
	echo "will update a local keepright database from a previously "
	echo "created error_view.txt dump file in the results directory."
	echo "you have to configure new country codes in the config file "
	echo "if you want to add new ones except the existing codes AT, DE, EU "
	echo "with --full as the first option this script will create "
	echo "a splitted and compressed version of error_view for upload "
	echo "to your web space provider."
fi