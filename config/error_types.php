<?php

$error_types[10]['name']='deleted items';
$error_types[10]['enabled']=false;
$error_types[10]['source']='0010_deleted_items.php';
$error_types[10]['description']='Deleted items should not be used in conjunction with not deleted items. This can be ways that contain deleted nodes or deleted ways/nodes that are member of relations.';

$error_types[20]['name']='multiple nodes on the same spot';
$error_types[20]['enabled']=true;
$error_types[20]['source']='0020_multiple_nodes_on_same_spot.php';
$error_types[20]['class']='warning';
$error_types[20]['description']='Try to find nodes that are (almost) on the same spot. Distances less than +/-0.5 meters are considered zero';

$error_types[30]['name']='non-closed areas';
$error_types[30]['enabled']=true;
$error_types[30]['source']='0030_non-closed_areas.php';
$error_types[30]['description']='By convention ways tagged with special combinations of key and value pairs are considered to be areas and are drawn as such in the map. Therefore it is necessary that they form closed loops. Non-closed areas are invisible in the map! The standard.xml file is used to determine which key-value-combinations are drawn as areas; any instance of these has to be closed-loop.';

$error_types[40]['name']='dead-ended one-ways';
$error_types[40]['enabled']=true;
$error_types[40]['source']='0040_dead-ended_one-ways.php';
$error_types[40]['description']='Streets tagged as one-way must not be dead-ended (where should all the cars pile at the end of the road?). Nodes where just one-way streets begin (or end) cannot be reached (escaped from). Please note that motorways and motorway_links are considered one-way implicitly, so this check applies.';

$error_types[50]['name']='almost-junctions';
$error_types[50]['enabled']=true;
$error_types[50]['source']='0050_almost-junctions.php';
$error_types[50]['description']='Streets that have (at least) one unconnected end are examined here. If such an end-node is very close to any other way an error is reported. Unconnected end-nodes should probably be connected to adjacent ways.';

$error_types[60]['name']='deprecated tags';
$error_types[60]['enabled']=true;
$error_types[60]['source']='0060_deprecated_tags.php';
$error_types[60]['description']='As shown in http://wiki.openstreetmap.org/index.php/Deprecated_features some tags get replaced by others as new tagging conventions evolve. Consider this as a notice, not as an error. The term \'deprecated\' is deprecated; you\'re free to tag anything the way you like. This is just a hint for new tagging conventions that have evolved in the meantime.';

$error_types[70]['name']='missing tags';
$error_types[70]['enabled']=true;
$error_types[70]['source']='0070_missing_tags.php';
$error_types[70]['description']='Ways, nodes and relations should in most cases have at least one tag (besides created_by)';
$error_types[70]['subtype'][71]='way without tags';
$error_types[70]['subtype'][72]='node without tags';
$error_types[70]['subtype'][73]='tag combinations';
$error_types[70]['subtype'][74]='Empty tags';
$error_types[70]['subtype'][75]='name but no other tag';

$error_types[80]['name']='bridges or tunnels without layer';
$error_types[80]['enabled']=false;
$error_types[80]['source']='0080_bridges_or_tunnels_without_layer.php';
$error_types[80]['description']='Bridges and tunnels need a layer tag as a hint for map drawing processes for achieving correct visibility of elements. THIS CHECK IS OBSOLETE.';

$error_types[90]['name']='motorways without ref';
$error_types[90]['enabled']=true;
$error_types[90]['source']='0090_motorways_without_ref.php';
$error_types[90]['description']='The ref-tag documents the reference (e.g. \'A 10\') for motorways. These are mandatory as they are very important information';

$error_types[100]['name']='places of worship without religion';
$error_types[100]['enabled']=true;
$error_types[100]['source']='0100_places_of_worship_without_religion.php';
$error_types[100]['description']='Churches, mosques and synagogues etc. need an extra religion tag giving info about the religion';

$error_types[110]['name']='point of interest without name';
$error_types[110]['enabled']=true;
$error_types[110]['source']='0110_point_of_interest_without_name.php';
$error_types[110]['description']='The name tag should be specified for every point of interest as its content gets rendered in the map next to the POI symbol';

$error_types[120]['name']='ways without nodes';
$error_types[120]['enabled']=true;
$error_types[120]['source']='0120_ways_without_nodes.php';
$error_types[120]['description']='Ways that don\'t consist of at least two nodes don\'t make much sense as they won\'t get rendered.';
$error_types[120]['subtype'][121]='Ways that don\'t consist of at least two nodes don\'t make much sense as they won\'t get rendered.';

$error_types[130]['name']='floating islands';
$error_types[130]['enabled']=true;
$error_types[130]['source']='0130_islands.php';
$error_types[130]['description']='Any highways drawn on the map should be accessible by car starting anywhere in the world. Ferries and highways (even railway platforms) are included in this check, so almost any island in the sea should be reachable starting from the mainland.';

$error_types[140]['name']='ways with name tag, without frequently used tag';
$error_types[140]['enabled']=false;
$error_types[140]['source']='0140_ways_with_name_without_highway.php';
$error_types[140]['description']='Ways tagged with a name-tag are supposed to be highways, buildings, amenities etc. So they need more tags defining the type of object. Objects having a name but none of frequently used type tags are reported in this check';

$error_types[150]['name']='railway crossings without tag';
$error_types[150]['enabled']=true;
$error_types[150]['source']='0150_level_crossing_without_tag.php';
$error_types[150]['description']='(Level)-Crossings of railways and highways should have a common node as junction that is tagged with \'railway=level_crossing\' if it is a crossing where larger vehicles can cross or with \'railway=crossing\' if it is a crossing just for pedestrians';

$error_types[160]['name']='wrongly used railway crossing tag';
$error_types[160]['enabled']=true;
$error_types[160]['source']='0160_wrong_use_of_level_crossing_tag.php';
$error_types[160]['description']='Ways that take part in level-crossings of railways and highways have to be on the same layer and should in the normal case be not tagged as bridge or tunnel';

$error_types[170]['name']='fixme-tagged items';
$error_types[170]['enabled']=true;
$error_types[170]['source']='0170_fixme.php';
$error_types[170]['description']='Nodes, ways or relations that are tagged with FIXME should be reviewed...';

$error_types[180]['name']='relations without type';
$error_types[180]['enabled']=true;
$error_types[180]['source']='0180_relations_without_type.php';
$error_types[180]['description']='Find any relation that has no type tag, wich is mandatory for relations.';

$error_types[190]['name']='intersections without junctions';
$error_types[190]['enabled']=true;
$error_types[190]['source']='0190_intersections_without_junctions.php';
$error_types[190]['description']='Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels).';

$error_types[190]['subtype'][191]='highway-highway';
$error_types[190]['subtype'][192]='highway-waterway';
$error_types[190]['subtype'][193]='highway-riverbank';
$error_types[190]['subtype'][194]='waterway-waterway';
$error_types[190]['subtype'][195]='cyclew/footp-cyclew/footp';
$error_types[190]['subtype'][196]='highway-cyclew/footp';
$error_types[190]['subtype'][197]='cyclew/footp-waterway';
$error_types[190]['subtype'][198]='cyclew/footp-riverbank';


$error_types[200]['name']='overlapping ways';
$error_types[200]['enabled']=true;
$error_types[200]['source']='';
$error_types[200]['description']='Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.';

$error_types[200]['subtype'][201]='highway-highway';
$error_types[200]['subtype'][202]='highway-waterway';
$error_types[200]['subtype'][203]='highway-riverbank';
$error_types[200]['subtype'][204]='waterway-waterway';
$error_types[200]['subtype'][205]='cycleway-cycleway';
$error_types[200]['subtype'][206]='highway-cycleway';
$error_types[200]['subtype'][207]='cycleway-waterway';
$error_types[200]['subtype'][208]='cycleway-riverbank';


$error_types[210]['name']='loopings';
$error_types[210]['enabled']=true;
$error_types[210]['source']='0210_loopings.php';
$error_types[210]['description']='Any way that contains any single node more than twice is considered an error. Any way may contain just one node twice. If more than one node is found twice it is considered an error.';

$error_types[220]['name']='misspelled tags';
$error_types[220]['enabled']=true;
$error_types[220]['source']='0220_misspelled_tags.php';
$error_types[220]['description']='Tags that are used very seldom and almost look like very common tags (only one character difference) are reported as a warning.';

$error_types[230]['name']='layer conflicts';
$error_types[230]['enabled']=true;
$error_types[230]['source']='0230_layer_conflicts.php';
$error_types[230]['description']='Connected ways should be on the same layer. Crossings on intermediate nodes of ways on different layers are obviously wrong. Junctions on end-nodes of ways on different layers are also deprecated, but common practice. So you may ignore this part of the check and switch them off separately. Please note that bridges are set to layer +1, and tunnels to -1, anything else to layer 0 implicitly if no layer tag is present.';

$error_types[230]['subtype'][231]='mixed layers intersections';
$error_types[230]['subtype'][232]='strange layers';
$error_types[230]['subtype'][233]='strange layer of waterway';


# 240, 250 reserved for Peter


$error_types[260]['name']='map features';
$error_types[260]['enabled']=false;
$error_types[260]['source']='0260_map_features.php';
$error_types[260]['description']='checks taggins against the proposed map features and asserts dependencies of tags';

$error_types[270]['name']='motorways connected directly';
$error_types[270]['enabled']=true;
$error_types[270]['source']='0270_motorways_connected_directly.php';
$error_types[270]['description']='Motorways should only be connected to other motorways or motorway_links. They should especially not be directly connected to highway=primary or highway=residential roads. Please note: This check may produce false positives on motorways ending in cities but it can be a valuable tool for looking up unwanted connections of motorways and other roads.';


$error_types[280]['name']='boundaries';
$error_types[280]['enabled']=true;
$error_types[280]['source']='0280_boundaries.php';
$error_types[280]['description']='Administrative Boundaries can be expressed either by tagging ways or by adding them to a relation. They should be closed-loop sequences of ways, they must not self-intersect or split and they must have a name and an admin_level.';

$error_types[280]['subtype'][281]='missing name';
$error_types[280]['subtype'][282]='missing admin_level';
$error_types[280]['subtype'][283]='not closed loop';
$error_types[280]['subtype'][284]='splitting boundary';
$error_types[280]['subtype'][285]='admin_level too high';


$error_types[290]['name']='restrictions';
$error_types[290]['enabled']=true;
$error_types[290]['source']='0290_restrictions.php';
$error_types[290]['description']='This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as \'from\' member, another way as \'to\' member and optionally one or more ways or nodes as via members that need to be connected to \'from\' and \'to\'';

$error_types[290]['subtype'][291]='missing type';
$error_types[290]['subtype'][292]='missing from way';
$error_types[290]['subtype'][293]='missing to way';
$error_types[290]['subtype'][294]='from or to not a way';
$error_types[290]['subtype'][295]='via is not on the way ends';
$error_types[290]['subtype'][296]='wrong restriction angle';
$error_types[290]['subtype'][297]='wrong direction of to member';
$error_types[290]['subtype'][298]='already restricted by oneway';


$error_types[300]['name']='missing maxspeed';
$error_types[300]['enabled']=true;
$error_types[300]['source']='0300_maxspeed.php';
$error_types[300]['class']='warning';
$error_types[300]['description']='maxspeed tags are not mandatory but they are very helpful for successful routing. This check shall help users finding highways lacking the maxspeed tag. Please only add maxspeed tags if the allowed maxspeed differs from the default speed for the given highway!';


$error_types[310]['name']='roundabouts';
$error_types[310]['enabled']=true;
$error_types[310]['source']='0310_roundabouts.php';
$error_types[310]['description']='roundabout are always one-way streets directed counter-clockwise in right-hand driving countries or clockwise in left-hand driving countries.';

$error_types[310]['subtype'][311]='not closed loop';
$error_types[310]['subtype'][312]='wrong direction';
$error_types[310]['subtype'][313]='faintly connected';


$error_types[320]['name']='*_link-connections';
$error_types[320]['enabled']=true;
$error_types[320]['source']='0320_highway_link_connections.php';
$error_types[320]['description']='Any highway tagged as (motorway|trunk|primary|secondary)_link should have at least one connection to a (motorway|trunk|primary|secondary) highway or a _link highway of the same type';


$error_types[350]['name']='bridge-tags';
$error_types[350]['enabled']=true;
$error_types[350]['source']='0350_bridges.php';
$error_types[350]['description']='Someone draws a bridge and forgets to tag it as highway... A bridge should always have one of the \'major\' tags that its direct neighbour ways have. Currently these are: highway, railway, cycleway, waterway, footway, piste, aerialway, pipeline, building, via_ferrata';


$error_types[360]['name']='language unknown';
$error_types[360]['enabled']=true;
$error_types[360]['source']='0360_language_unknown.php';
$error_types[360]['class']='warning';
$error_types[360]['description']='To help applications choose the best matching language they should know the language used for the name tag. It is therefore necessary to provide a name:XX tag with the same content as found in the name tag where XX specifies the language used for the name tag.';


$error_types[370]['name']='doubled places';
$error_types[370]['enabled']=true;
$error_types[370]['source']='0370_double_place.php';
$error_types[370]['class']='error';
$error_types[370]['description']='a node inside of an area that is tagged with same name and representing the same physical entity leads to wrong statistics and doubled labels on the map';


$error_types[380]['name']='non-physical use of sport-tag';
$error_types[380]['enabled']=true;
$error_types[380]['source']='0380_nonphysical_sport_tag.php';
$error_types[380]['class']='error';
$error_types[380]['description']='';

$error_types[390]['name']='missing tracktype';
$error_types[390]['enabled']=true;
$error_types[390]['source']='0390_missing_tracktype.php';
$error_types[390]['class']='warning';
$error_types[390]['description']='highway=track ways should be added with more detail about the tracktype (grade1..grade5). The tracktype is included in rendering rules and makes maps more expressive.';

$error_types[400]['name']='geometry glitches';
$error_types[400]['enabled']=true;
$error_types[400]['source']='0400_geometry_glitches.php';
$error_types[400]['class']='error';
$error_types[400]['description']='looks for impossible sharp angles on highways and junctions. These may be caused by missing turn restrictions on junctions or glitches along the linestring of ways';
$error_types[400]['subtype'][401]='missing turn restriction';
$error_types[400]['subtype'][402]='impossible angles';


$error_types[410]['name']='websites';
$error_types[410]['enabled']=false;
$error_types[410]['source']='0410_website.php';
$error_types[410]['class']='error';
$error_types[410]['description']='calls websites and tries to match website content with key tags from the osm element';
$error_types[410]['subtype'][411]='http error';
$error_types[410]['subtype'][412]='domain hijacking';
$error_types[410]['subtype'][413]='non-match';

$error_types[420]['name']='suspicious values and tags';
$error_types[420]['enabled']=true;
$error_types[420]['source']='0420_suspicious_values.php';
$error_types[420]['class']='error';
$error_types[420]['description']='Check for correct units and inappropriate list of values as well as wrong tag combinations';
$error_types[420]['subtype'][421]='wrong units';
$error_types[420]['subtype'][422]='list of values';
$error_types[420]['subtype'][423]='Tag is unlikely to have numeric value';
$error_types[420]['subtype'][424]='Tag combination seems wrong';

$error_types[9000]['name']='test';
$error_types[9000]['enabled']=false;
$error_types[9000]['source']='9000_testcheck.php';
$error_types[9000]['class']='error';
$error_types[9000]['description']='just for testing';


?>
