-- table structure and config data for web server database

-- phpMyAdmin SQL Dump
-- version 4.0.9
-- http://www.phpmyadmin.net
--



SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Datenbank: `k000382_1`
--

-- --------------------------------------------------------

--
-- structure of table `announce`
--

CREATE TABLE IF NOT EXISTS `announce` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `body` text CHARACTER SET latin1 COLLATE latin1_general_ci,
  `txt1` text CHARACTER SET latin1 COLLATE latin1_general_ci,
  `txt2` text CHARACTER SET latin1 COLLATE latin1_general_ci,
  `txt3` text CHARACTER SET latin1 COLLATE latin1_general_ci,
  `tstamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `visible` tinyint(4) DEFAULT '0',
  `archived` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=68 ;

-- --------------------------------------------------------

--
-- structure of table `comments`
--

CREATE TABLE IF NOT EXISTS `comments` (
  `schema` varchar(6) NOT NULL DEFAULT '',
  `error_id` int(11) NOT NULL,
  `state` enum('ignore_temporarily','ignore') DEFAULT NULL,
  `comment` text,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `i` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`i`),
  KEY `error_id` (`error_id`),
  KEY `schema` (`schema`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1788505 ;

-- --------------------------------------------------------

--
-- structure of table `comments_historic`
--

CREATE TABLE IF NOT EXISTS `comments_historic` (
  `schema` varchar(6) NOT NULL DEFAULT '',
  `error_id` int(11) NOT NULL,
  `state` enum('ignore_temporarily','ignore') DEFAULT NULL,
  `comment` text,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  KEY `schema_error_id` (`schema`,`error_id`),
  KEY `schema` (`schema`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- structure of table `error_counts`
--

CREATE TABLE IF NOT EXISTS `error_counts` (
  `schema` varchar(6) NOT NULL,
  `error_type` int(11) NOT NULL,
  `error_count` int(1) NOT NULL,
  UNIQUE KEY `schema_error_type` (`schema`,`error_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- structure of table `error_types`
--

CREATE TABLE IF NOT EXISTS `error_types` (
  `error_type` int(11) NOT NULL,
  `error_name` varchar(100) NOT NULL,
  `error_description` text NOT NULL,
  `error_class` varchar(255) NOT NULL DEFAULT 'error',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`error_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- structure of table `schemata`
--

CREATE TABLE IF NOT EXISTS `schemata` (
  `left` double NOT NULL,
  `right` double NOT NULL,
  `top` double NOT NULL,
  `bottom` double NOT NULL,
  `left_padded` double NOT NULL,
  `right_padded` double NOT NULL,
  `top_padded` double NOT NULL,
  `bottom_padded` double NOT NULL,
  `schema` varchar(6) NOT NULL,
  PRIMARY KEY (`schema`),
  KEY `leftright_padded` (`left_padded`,`right_padded`),
  KEY `topbottom_padded` (`top_padded`,`bottom_padded`),
  KEY `leftright` (`left`,`right`),
  KEY `topbottom` (`top`,`bottom`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


--
-- data for table `error_types`
--

INSERT INTO `error_types` (`error_type`, `error_name`, `error_description`, `error_class`, `hidden`) VALUES
(30, 'non-closed areas', 'By convention ways tagged with special combinations of key and value pairs are considered to be areas and are drawn as such in the map. Therefore it is necessary that they form closed loops. Non-closed areas are invisible in the map! The standard.xml file is used to determine which key-value-combinations are drawn as areas; any instance of these has to be closed-loop.', 'error', 0),
(40, 'dead-ended one-ways', 'Streets tagged as one-way must not be dead-ended (where should all the cars pile at the end of the road?). Nodes where just one-way streets begin (or end) cannot be reached (escaped from). Please note that motorways and motorway_links are considered one-way implicitly, so this check applies.', 'error', 0),
(50, 'almost-junctions', 'Streets that have (at least) one unconnected end are examined here. If such an end-node is very close to any other way an error is reported. Unconnected end-nodes should probably be connected to adjacent ways.', 'error', 0),
(60, 'deprecated tags', 'As shown in http://wiki.openstreetmap.org/index.php/Deprecated_features some tags get replaced by others as new tagging conventions evolve. Consider this as a notice, not as an error. The term ''deprecated'' is deprecated; you''re free to tag anything the way you like. This is just a hint for new tagging conventions that have evolved in the meantime.', 'warning', 0),
(70, 'missing tags', 'Ways, nodes and relations should in most cases have at least one tag (besides created_by)', 'error', 0),
(90, 'motorways without ref', 'The ref-tag documents the reference (e.g. ''A 10'') for motorways. These are mandatory as they are very important information', 'error', 0),
(100, 'places of worship without religion', 'Churches, mosques and synagogues etc. need an extra religion tag giving info about the religion', 'error', 0),
(110, 'point of interest without name', 'The name tag should be specified for every point of interest as its content gets rendered in the map next to the POI symbol', 'error', 0),
(120, 'ways without nodes', 'Ways that don''t consist of at least two nodes don''t make much sense as they won''t get rendered.', 'error', 0),
(130, 'floating islands', 'Any highways drawn on the map should be accessible by car starting anywhere in the world. Ferries and highways (even railway platforms) are included in this check, so almost any island in the sea should be reachable starting from the mainland.', 'error', 0),
(150, 'railway crossings without tag', '(Level)-Crossings of railways and highways should have a common node as junction that is tagged with ''railway=level_crossing'' if it is a crossing where larger vehicles can cross or with ''railway=crossing'' if it is a crossing just for pedestrians', 'error', 0),
(160, 'wrongly used railway crossing tag', 'Ways that take part in level-crossings of railways and highways have to be on the same layer and should in the normal case be not tagged as bridge or tunnel', 'error', 0),
(170, 'fixme-tagged items', 'Nodes, ways or relations that are tagged with FIXME should be reviewed...', 'error', 0),
(180, 'relations without type', 'Find any relation that has no type tag, wich is mandatory for relations.', 'error', 0),
(190, 'intersections without junctions', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(191, 'highway-highway', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(192, 'highway-waterway', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(193, 'highway-riverbank', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(194, 'waterway-waterway', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(200, 'overlapping ways', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(201, 'highway-highway', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(202, 'highway-waterway', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(203, 'highway-riverbank', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(204, 'waterway-waterway', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(210, 'loopings', 'Any way that contains any single node more than twice is considered an error. Any way may contain just one node twice. If more than one node is found twice it is considered an error.', 'error', 0),
(220, 'misspelled tags', 'Tags that are used very seldom and almost look like very common tags (only one character difference) are reported as a warning.', 'error', 0),
(230, 'layer conflicts', 'Connected ways should be on the same layer. Crossings on intermediate nodes of ways on different layers are obviously wrong. Junctions on end-nodes of ways on different layers are also deprecated, but common practice. So you may ignore this part of the check and switch them off separately. Please note that bridges are set to layer +1, and tunnels to -1, anything else to layer 0 implicitly if no layer tag is present.', 'error', 0),
(231, 'mixed layers intersections', 'Connected ways should be on the same layer. Crossings on intermediate nodes of ways on different layers are obviously wrong. Junctions on end-nodes of ways on different layers are also deprecated, but common practice. So you may ignore this part of the check and switch them off separately. Please note that bridges are set to layer +1, and tunnels to -1, anything else to layer 0 implicitly if no layer tag is present.', 'error', 0),
(232, 'strange layers', 'Connected ways should be on the same layer. Crossings on intermediate nodes of ways on different layers are obviously wrong. Junctions on end-nodes of ways on different layers are also deprecated, but common practice. So you may ignore this part of the check and switch them off separately. Please note that bridges are set to layer +1, and tunnels to -1, anything else to layer 0 implicitly if no layer tag is present.', 'error', 0),
(270, 'motorways connected directly', 'Motorways should only be connected to other motorways or motorway_links. They should especially not be directly connected to highway=primary or highway=residential roads. Please note: This check may produce false positives on motorways ending in cities but it can be a valuable tool for looking up unwanted connections of motorways and other roads.', 'error', 0),
(280, 'boundaries', 'Administrative Boundaries can be expressed either by tagging ways or by adding them to a relation. They should be closed-loop sequences of ways, they must not self-intersect or split and they must have a name and an admin_level.', 'error', 0),
(281, 'missing name', 'Administrative Boundaries can be expressed either by tagging ways or by adding them to a relation. They should be closed-loop sequences of ways, they must not self-intersect or split and they must have a name and an admin_level.', 'error', 0),
(282, 'missing admin_level', 'Administrative Boundaries can be expressed either by tagging ways or by adding them to a relation. They should be closed-loop sequences of ways, they must not self-intersect or split and they must have a name and an admin_level.', 'error', 0),
(283, 'not closed loop', 'Administrative Boundaries can be expressed either by tagging ways or by adding them to a relation. They should be closed-loop sequences of ways, they must not self-intersect or split and they must have a name and an admin_level.', 'error', 0),
(284, 'splitting boundary', 'Administrative Boundaries can be expressed either by tagging ways or by adding them to a relation. They should be closed-loop sequences of ways, they must not self-intersect or split and they must have a name and an admin_level.', 'error', 0),
(290, 'restrictions', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 0),
(291, 'missing type', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 0),
(292, 'missing from way', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 0),
(293, 'missing to way', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 0),
(195, 'cycleway-cycleway', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(196, 'highway-cycleway', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(197, 'cycleway-waterway', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(198, 'cycleway-riverbank', 'Streets that graphically intersect need a common node that represents the crossing. The only exception are intersections on different layers (eg. bridges or tunnels). Intersections of streets with other objects like waterways or riverbanks should be avoided', 'error', 0),
(205, 'cycleway-cycleway', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(206, 'highway-cycleway', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(207, 'cycleway-waterway', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.', 'error', 0),
(208, 'cycleway-riverbank', 'Segments of ways that lie on top of each other (the same nodes connected by different ways in the same order) are a problem for routing software. They happen accidently and are hard to find.\r\n', 'error', 0),
(300, 'missing maxspeed', 'maxspeed tags are not mandatory but they are very helpful for successful routing. This check shall help users finding highways lacking the maxspeed tag. Please only add maxspeed tags if the allowed maxspeed differs from the default speed for the given highway!', 'warning', 0),
(20, 'multiple nodes on the same spot', 'Try to find nodes that are (almost) on the same spot. Distances less than +/-0.5 meters are considered zero', 'warning', 0),
(310, 'roundabouts', 'roundabout are always one-way streets directed counter-clockwise in right-hand driving countries or clockwise in left-hand driving countries.', 'error', 0),
(311, 'not closed loop', 'roundabout are always one-way streets directed counter-clockwise in right-hand driving countries or clockwise in left-hand driving countries.', 'error', 0),
(312, 'wrong direction', 'roundabout are always one-way streets directed counter-clockwise in right-hand driving countries or clockwise in left-hand driving countries.', 'error', 0),
(350, 'bridge-tags', 'Someone draws a bridge and forgets to tag it as highway... A bridge should always have one of the ''major'' tags that its direct neighbour ways have. Currently these are: highway, railway, cycleway, waterway, footway, piste, aerialway, pipeline, building, via_ferrata', 'error', 0),
(313, 'faintly connected', 'roundabout are always one-way streets directed counter-clockwise in right-hand driving countries or clockwise in left-hand driving countries.', 'error', 0),
(360, 'language unknown', 'To help applications choose the best matching language they should know the language used for the name tag. It is therefore necessary to provide a name:XX tag with the same content as found in the name tag where XX specifies the language used for the name tag.', 'warning', 0),
(380, 'non-physical use of sport-tag', '&apos;sports&apos; is a non-physical tag that needs to be bound to some physical structure\nlike for example a leisure-item or an amenity. Ways tagged with &apos;sports&apos; solely will be invisible on the map', 'error', 0),
(390, 'missing tracktype', 'highway=track ways should be added with more detail about the tracktype (grade1..grade5). The tracktype is included in rendering rules and makes maps more expressive', 'warning', 0),
(410, 'website', 'calls websites and tries to match website content with key tags from the osm element', 'error', 0),
(400, 'geometry glitches', 'looks for impossible sharp angles on highways and junctions. These may be caused by missing turn restrictions on junctions or glitches along the linestring of ways', 'error', 0),
(401, 'missing turn restriction', 'looks for impossible sharp angles on highways and junctions. These may be caused by missing turn restrictions on junctions or glitches along the linestring of ways', 'error', 0),
(402, 'impossible angles', 'looks for impossible sharp angles on highways and junctions. These may be caused by missing turn restrictions on junctions or glitches along the linestring of ways', 'error', 0),
(411, 'http error', 'calls websites and tries to match website content with key tags from the osm element', 'error', 0),
(412, 'domain hijacking', 'calls websites and tries to match website content with key tags from the osm element', 'error', 0),
(413, 'non-match', 'calls websites and tries to match website content with key tags from the osm element', 'error', 0),
(370, 'doubled places', 'a node inside of an area that is tagged with same name and representing the same physical entity leads to wrong statistics and doubled labels on the map', 'error', 0),
(294, 'from or to not a way', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 0),
(285, 'admin_level too high', 'Administrative Boundaries can be expressed either by tagging ways or by adding them to a relation. They should be closed-loop sequences of ways, they must not self-intersect or split and they must have a name and an admin_level.', 'error', 0),
(320, '*_link-connections', 'Any highway tagged as (motorway|trunk|primary|secondary)_link should have at least one connection to a (motorway|trunk|primary|secondary) highway or a _link highway of the same type', 'error', 0),
(295, 'via is not on the way ends', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 1),
(296, 'wrong restriction angle', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 1),
(297, 'wrong direction of to member', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 1),
(298, 'already restricted by oneway', 'This check is about turn restrictions defined by relations. A turn restriction relation needs a valid type attribute, a way as ''from'' member, another way as ''to'' member and optionally one or more ways or nodes as via members that need to be connected to ''from'' and ''to''', 'error', 1);


--
-- data for table `schemata`
--

INSERT INTO `schemata` (`left`, `right`, `top`, `bottom`, `left_padded`, `right_padded`, `top_padded`, `bottom_padded`, `schema`) VALUES
(0, 0, 0, 0, -0.5, 0.5, 0.5, -0.5, '0'),
(-30, 1.8, 90, 52.3, -30.5, 2.3, 90.5, 51.8, '86'),
(1.8, 12, 90, 55, 1.3, 12.5, 90.5, 54.5, '2'),
(110, 131, 90, -10, 109.8203, 131.1797, 89.5016, -10.178, '73'),
(7.5, 10, 55, 49.3, 7, 10.5, 55.5, 48.8, '4'),
(10, 14, 52.3, 50.6, 9.5, 14.5, 52.8, 50.1, '101'),
(3.5, 6.7, 49.3, 45, 3, 7.2, 49.8, 44.5, '7'),
(14, 18, 55, 49.3, 13.820336943176, 18.179663056824, 55.103145517922, 49.182365359326, '80'),
(18, 51.4, 55, 49.3, 17.820336943176, 51.579663056824, 55.103145517922, 49.182365359326, '81'),
(6.7, 10, 46.5, 45, 6.2, 10.5, 47, 44.5, '96'),
(10, 14, 48.1, 46.5, 9.5, 14.5, 48.6, 46, '98'),
(6.5, 18.3, 45, 35, 6, 18.8, 45.5, 34.5, '89'),
(18.3, 51.4, 45, 35, 17.8, 51.9, 45.5, 34.5, '15'),
(12, 23, 90, 55, 11.5, 23.5, 90.5, 54.5, '18'),
(23, 51.4, 90, 55, 22.5, 51.9, 90.5, 54.5, '19'),
(-30, 51.4, 35, -90, -30.5, 51.9, 35.5, -90.5, '17'),
(-180, -116, 50, 46, -180.5, -115.5, 50.5, 45.5, '21'),
(-116, -99, 50, 46, -116.5, -98.5, 50.5, 45.5, '22'),
(-117.3, -111.6, 46, 42, -117.8, -111.1, 46.5, 41.5, '24'),
(-77.4, -72, 46, 42, -77.9, -71.5, 46.5, 41.5, '25'),
(-180, -117.3, 46, 42, -180.5, -116.8, 46.5, 41.5, '26'),
(-111.6, -100.3, 46, 42, -112.1, -99.8, 46.5, 41.5, '27'),
(-100.3, -88.9, 46, 42, -100.8, -88.4, 46.5, 41.5, '28'),
(-88.9, -77.4, 46, 42, -89.4, -76.9, 46.5, 41.5, '29'),
(-72, -30, 46, 42, -72.5, -29.5, 46.5, 41.5, '30'),
(-180, -120.6, 42, 38, -180.5, -120.1, 42.5, 37.5, '31'),
(-113.7, -102.3, 42, 38, -114.2, -101.8, 42.5, 37.5, '32'),
(-102.3, -92, 42, 40, -102.8, -91.5, 42.5, 39.5, '33'),
(-92, -84.5, 42, 40, -92.5, -84, 42.5, 39.5, '34'),
(-79.4, -75, 42, 40, -79.9, -74.5, 42.5, 39.5, '35'),
(-180, -116, 38, 34, -180.5, -115.5, 38.5, 33.5, '36'),
(-116, -100.3, 38, 34, -116.5, -99.8, 38.5, 33.5, '37'),
(-100.3, -94.6, 38, 34, -100.8, -94.1, 38.5, 33.5, '38'),
(-88.9, -85, 38, 36.4, -89.4, -84.5, 38.5, 35.9, '39'),
(-77.6, -30, 38, 34, -78.1, -29.5, 38.5, 33.5, '40'),
(-180, -108.3, 34, 20, -180.5, -107.8, 34.5, 19.5, '41'),
(-108.3, -97.5, 34, 30, -108.8, -97, 34.5, 29.5, '42'),
(-97.5, -90.9, 34, 30, -98, -90.4, 34.5, 29.5, '43'),
(-90.9, -85.7, 34, 30, -91.4, -85.2, 34.5, 29.5, '44'),
(-83.2, -30, 34, 30, -83.7, -29.5, 34.5, 29.5, '45'),
(-120.6, -113.7, 42, 38, -121.1, -113.2, 42.5, 37.5, '51'),
(-102.3, -90.9, 40, 38, -102.8, -90.4, 40.5, 37.5, '52'),
(-84.5, -79.4, 42, 40, -85, -78.9, 42.5, 39.5, '53'),
(-90.9, -85.1, 40, 38, -91.4, -84.6, 40.5, 37.5, '54'),
(-85.1, -79.4, 40, 38, -85.6, -78.9, 40.5, 37.5, '55'),
(-75, -30, 42, 40, -75.5, -29.5, 42.5, 39.5, '56'),
(-79.4, -77.2, 40, 38, -79.9, -76.7, 40.5, 37.5, '57'),
(-77.2, -30, 40, 38, -77.7, -29.5, 40.5, 37.5, '58'),
(-94.6, -88.9, 38, 34, -95.1, -88.4, 38.5, 33.5, '59'),
(-85, -81.4, 38, 36.4, -85.5, -80.9, 38.5, 35.9, '60'),
(-81.4, -78.9, 38, 36.4, -81.9, -78.4, 38.5, 35.9, '61'),
(-88.9, -83.8, 36.4, 34, -89.4, -83.3, 36.9, 33.5, '62'),
(-83.8, -81.4, 36.4, 34, -84.3, -80.9, 36.9, 33.5, '63'),
(-81.4, -78.9, 36.4, 34, -81.9, -78.4, 36.9, 33.5, '64'),
(-85.7, -83.2, 34, 30, -86.2, -82.7, 34.5, 29.5, '65'),
(-78.9, -77.6, 38, 34, -79.4, -77.1, 38.5, 33.5, '69'),
(-180, -30, 14, -90, -180.5, -29.5, 14.5, -90.5, '47'),
(51.4, 110, 90, -90, 50.9, 110.5, 90.5, -90.5, '48'),
(-30, -1.8, 49.3, 45, -30.18, -1.62, 49.42, 44.87, '76'),
(-1.8, 3.5, 49.3, 48.1, -1.98, 3.68, 49.42, 47.98, '77'),
(-180, -30, 90, 50, -180.5, -29.5, 90.5, 49.5, '20'),
(-180, -95, 30, 14, -180.5, -94.5, 30.5, 13.5, '46'),
(-95, -30, 30, 14, -95.5, -29.5, 30.5, 13.5, '68'),
(110, 180, -10, -90, 109.5, 180.5, -9.5, -90.5, '50'),
(1.8, 5.8, 55, 52, 1.3, 6.3, 55.5, 51.5, '90'),
(1.8, 5.5, 52, 51, 1.3, 6, 52.5, 50.5, '92'),
(1.8, 7.5, 51, 49.3, 1.3, 8, 51.5, 48.8, '72'),
(131, 139, 90, -10, 130.8203, 139.1797, 89.5016, -10.178, '74'),
(139, 180, 90, -10, 138.8203, 180, 89.5016, -10.178, '75'),
(-1.8, 3.5, 48.1, 46.5, -1.98, 3.68, 48.22, 46.38, '78'),
(-1.8, 3.5, 46.5, 45, -1.98, 3.68, 46.62, 44.87, '79'),
(14, 19, 49.3, 45, 13.820336943176, 19.179663056824, 49.417352922744, 44.872388189368, '82'),
(19, 51.4, 49.3, 45, 18.820336943176, 51.579663056824, 49.417352922744, 44.872388189368, '83'),
(-30, 0, 45, 35, -30.179663056824, 0.1796630568239, 45.127326318823, 34.852028421568, '84'),
(0, 3.3, 45, 35, -0.1796630568239, 3.4796630568239, 45.127326318823, 34.852028421568, '85'),
(-30, 1.8, 52.3, 49.3, -30.5, 2.3, 52.8, 48.8, '87'),
(5.8, 7.5, 55, 52, 5.3, 8, 55.5, 51.5, '91'),
(5.5, 7.5, 52, 51, 5, 8, 52.5, 50.5, '93'),
(6.7, 10, 49.3, 48.1, 6.2, 10.5, 49.8, 47.6, '94'),
(6.7, 10, 48.1, 46.5, 6.2, 10.5, 48.6, 46, '95'),
(10, 14, 49.3, 48.1, 9.5, 14.5, 49.8, 47.6, '97'),
(10, 14, 46.5, 45, 9.5, 14.5, 47, 44.5, '99'),
(10, 14, 55, 52.3, 9.5, 14.5, 55.5, 51.8, '100'),
(10, 14, 50.6, 49.3, 9.5, 14.5, 51.1, 48.8, '102'),
(3.3, 6.5, 45, 35, 2.8, 7, 45.5, 34.5, '88'),
(-74, -30, 50, 46, -74.5, -29.5, 50.5, 45.5, '104'),
(-99, -74, 50, 46, -99.5, -73.5, 50.5, 45.5, '103');
