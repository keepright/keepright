-- add SRID into spatial_ref_sys table
DELETE FROM spatial_ref_sys WHERE srid = 900913;
INSERT INTO spatial_ref_sys (srid, auth_name, auth_srid, srtext, proj4text) VALUES 
(900913,'EPSG',900913,'PROJCS["WGS84 / Simple Mercator",GEOGCS["WGS 84",
DATUM["WGS_1984",SPHEROID["WGS_1984", 6378137.0, 298.257223563]],PRIMEM["Greenwich", 0.0],
UNIT["degree", 0.017453292519943295],AXIS["Longitude", EAST],AXIS["Latitude", NORTH]],
PROJECTION["Mercator_1SP_Google"],PARAMETER["latitude_of_origin", 0.0],
PARAMETER["central_meridian", 0.0],PARAMETER["scale_factor", 1.0],PARAMETER["false_easting", 0.0],
PARAMETER["false_northing", 0.0],UNIT["m", 1.0],AXIS["x", EAST],
AXIS["y", NORTH],AUTHORITY["EPSG","900913"]]',
'+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +no_defs');




-- Import the table data from the data files using the fast COPY method.
\copy nodes FROM 'nodes_sorted.txt' WITH NULL AS 'NULL'
\copy node_tags FROM 'node_tags.txt' WITH NULL AS 'NULL'
\copy ways FROM 'ways.txt' WITH NULL AS 'NULL'
\copy way_tags FROM 'way_tags.txt' WITH NULL AS 'NULL'
\copy way_nodes FROM 'way_nodes2.txt' WITH NULL AS 'NULL'
\copy relations FROM 'relations.txt' WITH NULL AS 'NULL'
\copy relation_tags FROM 'relation_tags.txt' WITH NULL AS 'NULL'
\copy relation_members FROM 'relation_members.txt' WITH NULL AS 'NULL'