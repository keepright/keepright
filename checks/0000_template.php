<?php
/*
Template for script-part running one specific error-check on OSM Databases

you have to use the database links $db1, $db2, $db3 ... $db6 that already point to the OSM database. Don't establish database connections on your own!
Remember that the predefined function query() makes use of pg_query(). So you _must_not_ send another query to a database link if the preceding query's result set has not yet been freed. This would yield in 'funny' results. This is a decision made for performance reasons.

refer to the global variable $error_type to retrieve the current number of error (derived from script filename)

Do whatever is needed to find errors here and leave any error records in already existing
table _tmp_errors.

Look inside helpers.inc.php for useful php- and postgres-functions you may use.

Don't forget to clean up after yourself ("Your mom doesn't work here!"): if you use temporary tables, please drop them afterwards.

After adding a new check, update config and add appropriate configuration parameters there

*/


// just an example insert-statement:
if (true==false) {

	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked) 
		VALUES($error_type, 'node', 0, 'dont worry about this dummy test error entry!', NOW());
	", $db1);

}


?>