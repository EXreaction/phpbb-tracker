#
# $Id: $
#

# Table: 'phpbb_tracker_project'
CREATE TABLE phpbb_tracker_project (
	project_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	project_name varchar(255) DEFAULT '' NOT NULL,
	project_name_clean varchar(255) DEFAULT '' NOT NULL,
	project_desc varchar(255) DEFAULT '' NOT NULL,
	project_group mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	project_type tinyint(4) DEFAULT '0' NOT NULL,
	project_enabled tinyint(4) DEFAULT '0' NOT NULL,
	project_security tinyint(4) DEFAULT '0' NOT NULL,
	ticket_security tinyint(4) DEFAULT '0' NOT NULL,
	show_php tinyint(4) DEFAULT '0' NOT NULL,
	show_dbms tinyint(4) DEFAULT '0' NOT NULL,
	lang_php varchar(255) DEFAULT '' NOT NULL,
	lang_dbms varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (project_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_config'
CREATE TABLE phpbb_tracker_config (
	config_name varchar(255) DEFAULT '' NOT NULL,
	config_value varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (config_name)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_attachments'
CREATE TABLE phpbb_tracker_attachments (
	attach_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	ticket_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	post_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	poster_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	is_orphan tinyint(1) UNSIGNED DEFAULT '1' NOT NULL,
	physical_filename varchar(255) DEFAULT '' NOT NULL,
	real_filename varchar(255) DEFAULT '' NOT NULL,
	extension varchar(100) DEFAULT '' NOT NULL,
	mimetype varchar(100) DEFAULT '' NOT NULL,
	filesize int(20) UNSIGNED DEFAULT '0' NOT NULL,
	filetime int(11) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (attach_id),
	KEY filetime (filetime),
	KEY ticket_id (ticket_id),
	KEY post_id (post_id),
	KEY poster_id (poster_id),
	KEY is_orphan (is_orphan)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_tickets'
CREATE TABLE phpbb_tracker_tickets (
	ticket_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	project_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	ticket_title varchar(255) DEFAULT '' NOT NULL,
	ticket_desc mediumtext NOT NULL,
	ticket_desc_bitfield varchar(255) DEFAULT '' NOT NULL,
	ticket_desc_options int(11) UNSIGNED DEFAULT '7' NOT NULL,
	ticket_desc_uid varchar(8) DEFAULT '' NOT NULL,
	ticket_status tinyint(4) DEFAULT '0' NOT NULL,
	ticket_hidden tinyint(4) DEFAULT '0' NOT NULL,
	ticket_security tinyint(4) DEFAULT '0' NOT NULL,
	ticket_assigned_to int(8) DEFAULT '0' NOT NULL,
	status_id tinyint(4) DEFAULT '0' NOT NULL,
	component_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	version_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	severity_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	priority_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	ticket_php varchar(255) DEFAULT '' NOT NULL,
	ticket_dbms varchar(255) DEFAULT '' NOT NULL,
	ticket_user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	ticket_time int(11) DEFAULT '0' NOT NULL,
	last_post_user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	last_post_time int(11) DEFAULT '0' NOT NULL,
	last_visit_user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	last_visit_time int(11) UNSIGNED DEFAULT '0' NOT NULL,
	last_visit_username varchar(255) DEFAULT '' NOT NULL,
	last_visit_user_colour varchar(6) DEFAULT '' NOT NULL,
	edit_time int(11) DEFAULT '0' NOT NULL,
	edit_reason varchar(255) DEFAULT '' NOT NULL,
	edit_user mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	edit_count smallint(4) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (ticket_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_posts'
CREATE TABLE phpbb_tracker_posts (
	post_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	ticket_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	post_desc mediumtext NOT NULL,
	post_desc_bitfield varchar(255) DEFAULT '' NOT NULL,
	post_desc_options int(11) UNSIGNED DEFAULT '7' NOT NULL,
	post_desc_uid varchar(8) DEFAULT '' NOT NULL,
	post_user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	post_time int(11) DEFAULT '0' NOT NULL,
	edit_time int(11) DEFAULT '0' NOT NULL,
	edit_reason varchar(255) DEFAULT '' NOT NULL,
	edit_user mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	edit_count smallint(4) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (post_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_components'
CREATE TABLE phpbb_tracker_components (
	component_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	project_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	component_name varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (component_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_history'
CREATE TABLE phpbb_tracker_history (
	history_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	ticket_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	history_time int(11) DEFAULT '0' NOT NULL,
	history_status mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	history_user_id int(8) DEFAULT '0' NOT NULL,
	history_assigned_to int(8) DEFAULT '0' NOT NULL,
	history_old_status mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	history_new_status mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	history_old_priority mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	history_new_priority mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	history_old_severity mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	history_new_severity mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (history_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_version'
CREATE TABLE phpbb_tracker_version (
	version_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	project_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	version_name varchar(255) DEFAULT '' NOT NULL,
	version_enabled tinyint(4) DEFAULT '1' NOT NULL,
	PRIMARY KEY (version_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_project_watch'
CREATE TABLE phpbb_tracker_project_watch (
	user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	project_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (user_id, project_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_tickets_watch'
CREATE TABLE phpbb_tracker_tickets_watch (
	user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	ticket_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (user_id, ticket_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


