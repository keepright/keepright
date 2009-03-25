<?php


/*
common nodes between highways and railways (aka level crossings)
have to be tagged in a special way:
- need the railway=level_crossing tag to be set on the junction node
- this tag is allowed only if highway and railway are on the same layer
- it is unlikely that this tag is appropriate if the railway or the
  highway are tagged as bridge or as tunnel
*/

$result=query("
	SELECT DISTINCT nt.node_id
	FROM node_tags nt
	WHERE nt.k='railway' AND nt.v='level_crossing'
", $db1);


while ($row=pg_fetch_assoc($result)) {

	$way_ids=get_way_ids($row['node_id'], $db2);

	if (!same_layer($way_ids, $db2)) 
                query("
                        INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked)
                        VALUES ($error_type, 'node', {$row['node_id']}, 'There are ways in different layers coming together in this railway crossing', NOW())
                ", $db1, false);

	if (tagged('bridge', $way_ids, $db2) || tagged('tunnel', $way_ids, $db2))	
	        query("
	                INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked)
                        VALUES ($error_type, 'node', {$row['node_id']}, 'There are ways tagged as tunnel or bridge coming  together in this railway crossing', NOW()) 
                ", $db1, false);
	                                            
	                                            
	
}
pg_free_result($result);




// find all way_ids that are connected to given node
function get_way_ids($node_id, $db1) {
	$way_ids=array();
	
	$result=query("
        	SELECT DISTINCT way_id
                FROM way_nodes
                WHERE node_id=$node_id
	", $db1, false);

	while ($row=pg_fetch_array($result)) {
		$way_ids[] = $row[0];        
	}
	pg_free_result($result);
        return $way_ids;                
}


// get a list of way_ids and lookup the existence of layer-tags
// return true if each way is tagged with the same layer number
// missing layer-tags are treated as layer 0
function same_layer($way_ids, $db) {

        $layer=null;

        foreach ($way_ids as $way_id) {
                $temp = get_tag('layer', $way_id, $db);
      
                // remember new layer value if none is known up to now
                if ($layer == null && $temp != null) $layer=$temp;
      
                // return false if another value is found
                if ($layer != $temp) return false;
        
        }
        return true;
}


// return true if ANY of given way_ids is tagged with $key
function tagged($key, $way_ids, $db) {
        foreach ($way_ids as $way_id) 
                if (get_tag($key, $way_id, $db)!==null) return true;
  
        return false;
}


// will query for $key in the tags of given way and return $value 
// or null if the key is not found
function get_tag($key, $way_id, $db) {
        return query_firstval("SELECT v FROM way_tags WHERE way_id=$way_id AND k='$key'", $db, false);
}

?>