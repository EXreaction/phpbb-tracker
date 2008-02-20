#
# $Id: $
#

BEGIN TRANSACTION;

# Table: 'phpbb_tracker_project'
CREATE TABLE phpbb_tracker_project (
	project_id INTEGER PRIMARY KEY NOT NULL ,
	project_name text(65535) NOT NULL DEFAULT '',
	project_desc text(65535) NOT NULL DEFAULT '',
	project_group INTEGER UNSIGNED NOT NULL DEFAULT '0',
	project_type tinyint(4) NOT NULL DEFAULT '0',
	project_enabled tinyint(4) NOT NULL DEFAULT '0'
);


# Table: 'phpbb_tracker_config'
CREATE TABLE phpbb_tracker_config (
	config_name varchar(255) NOT NULL DEFAULT '',
	config_value varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (config_name)
);


# Table: 'phpbb_tracker_attachments'
CREATE TABLE phpbb_tracker_attachments (
	attach_id INTEGER PRIMARY KEY NOT NULL ,
	ticket_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	post_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	poster_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	is_orphan INTEGER UNSIGNED NOT NULL DEFAULT '1',
	physical_filename varchar(255) NOT NULL DEFAULT '',
	real_filename varchar(255) NOT NULL DEFAULT '',
	extension varchar(100) NOT NULL DEFAULT '',
	mimetype varchar(100) NOT NULL DEFAULT '',
	filesize INTEGER UNSIGNED NOT NULL DEFAULT '0',
	filetime INTEGER UNSIGNED NOT NULL DEFAULT '0'
);

CREATE INDEX phpbb_tracker_attachments_filetime ON phpbb_tracker_attachments (filetime);
CREATE INDEX phpbb_tracker_attachments_ticket_id ON phpbb_tracker_attachments (ticket_id);
CREATE INDEX phpbb_tracker_attachments_post_id ON phpbb_tracker_attachments (post_id);
CREATE INDEX phpbb_tracker_attachments_poster_id ON phpbb_tracker_attachments (poster_id);
CREATE INDEX phpbb_tracker_attachments_is_orphan ON phpbb_tracker_attachments (is_orphan);

# Table: 'phpbb_tracker_tickets'
CREATE TABLE phpbb_tracker_tickets (
	ticket_id INTEGER PRIMARY KEY NOT NULL ,
	project_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	ticket_title text(65535) NOT NULL DEFAULT '',
	ticket_desc text(65535) NOT NULL DEFAULT '',
	ticket_desc_bitfield varchar(255) NOT NULL DEFAULT '',
	ticket_desc_options INTEGER UNSIGNED NOT NULL DEFAULT '7',
	ticket_desc_uid varchar(8) NOT NULL DEFAULT '',
	ticket_status tinyint(4) NOT NULL DEFAULT '0',
	ticket_hidden tinyint(4) NOT NULL DEFAULT '0',
	ticket_assigned_to int(8) NOT NULL DEFAULT '0',
	status_id tinyint(4) NOT NULL DEFAULT '0',
	component_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	version_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	severity_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	priority_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	ticket_php text(65535) NOT NULL DEFAULT '',
	ticket_dbms text(65535) NOT NULL DEFAULT '',
	ticket_user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	ticket_time int(11) NOT NULL DEFAULT '0',
	last_post_user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	last_post_time int(11) NOT NULL DEFAULT '0',
	last_visit_user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	last_visit_time INTEGER UNSIGNED NOT NULL DEFAULT '0',
	last_visit_username varchar(255) NOT NULL DEFAULT '',
	last_visit_user_colour varchar(6) NOT NULL DEFAULT '',
	edit_time int(11) NOT NULL DEFAULT '0',
	edit_reason varchar(255) NOT NULL DEFAULT '',
	edit_user INTEGER UNSIGNED NOT NULL DEFAULT '0',
	edit_count INTEGER UNSIGNED NOT NULL DEFAULT '0'
);


# Table: 'phpbb_tracker_posts'
CREATE TABLE phpbb_tracker_posts (
	post_id INTEGER PRIMARY KEY NOT NULL ,
	ticket_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	post_desc text(65535) NOT NULL DEFAULT '',
	post_desc_bitfield varchar(255) NOT NULL DEFAULT '',
	post_desc_options INTEGER UNSIGNED NOT NULL DEFAULT '7',
	post_desc_uid varchar(8) NOT NULL DEFAULT '',
	post_user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	post_time int(11) NOT NULL DEFAULT '0',
	edit_time int(11) NOT NULL DEFAULT '0',
	edit_reason varchar(255) NOT NULL DEFAULT '',
	edit_user INTEGER UNSIGNED NOT NULL DEFAULT '0',
	edit_count INTEGER UNSIGNED NOT NULL DEFAULT '0'
);


# Table: 'phpbb_tracker_components'
CREATE TABLE phpbb_tracker_components (
	component_id INTEGER PRIMARY KEY NOT NULL ,
	project_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	component_name varchar(255) NOT NULL DEFAULT ''
);


# Table: 'phpbb_tracker_history'
CREATE TABLE phpbb_tracker_history (
	history_id INTEGER PRIMARY KEY NOT NULL ,
	ticket_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	history_time int(11) NOT NULL DEFAULT '0',
	history_status INTEGER UNSIGNED NOT NULL DEFAULT '0',
	history_user_id int(8) NOT NULL DEFAULT '0',
	history_assigned_to int(8) NOT NULL DEFAULT '0',
	history_old_status INTEGER UNSIGNED NOT NULL DEFAULT '0',
	history_new_status INTEGER UNSIGNED NOT NULL DEFAULT '0'
);


# Table: 'phpbb_tracker_version'
CREATE TABLE phpbb_tracker_version (
	version_id INTEGER PRIMARY KEY NOT NULL ,
	project_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	version_name varchar(255) NOT NULL DEFAULT ''
);



COMMIT;