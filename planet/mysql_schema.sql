-- keepright web presentation database schema

-- phpMyAdmin SQL Dump
-- version 2.11.8.1deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 11. März 2009 um 22:17
-- Server Version: 5.0.67
-- PHP-Version: 5.2.6-2ubuntu4.1

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS comments (
  error_id int(11) NOT NULL,
  state enum('ignore_temporarily','ignore') default NULL,
  `comment` text,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  ip varchar(255) default NULL,
  user_agent varchar(255) default NULL,
  KEY error_id (error_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS comments_historic (
  error_id int(11) NOT NULL,
  state enum('ignore_temporarily','ignore') default NULL,
  `comment` text,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  ip varchar(255) default NULL,
  user_agent varchar(255) default NULL,
  KEY error_id (error_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS error_types (
  error_type int(11) NOT NULL,
  error_name varchar(100) NOT NULL,
  error_description text NOT NULL,
  PRIMARY KEY  (error_type)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS error_view (
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
  lat int(11) NOT NULL,
  lon int(11) NOT NULL,
  KEY error_id (error_id),
  KEY lat (lat),
  KEY lon (lon),
  KEY error_type (error_type),
  KEY state (state)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS error_view_old (
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
  lat int(11) NOT NULL,
  lon int(11) NOT NULL,
  KEY error_id (error_id),
  KEY lat (lat),
  KEY lon (lon),
  KEY error_type (error_type),
  KEY state (state)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;