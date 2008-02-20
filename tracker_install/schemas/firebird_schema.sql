#
# $Id: $
#


# Table: 'phpbb_tracker_project'
CREATE TABLE phpbb_tracker_project (
	project_id INTEGER NOT NULL,
	project_name VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE,
	project_desc VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE,
	project_group INTEGER DEFAULT 0 NOT NULL,
	project_type INTEGER DEFAULT 0 NOT NULL,
	project_enabled INTEGER DEFAULT 0 NOT NULL
);;

ALTER TABLE phpbb_tracker_project ADD PRIMARY KEY (project_id);;


CREATE GENERATOR phpbb_tracker_project_gen;;
SET GENERATOR phpbb_tracker_project_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_project FOR phpbb_tracker_project
BEFORE INSERT
AS
BEGIN
	NEW.project_id = GEN_ID(phpbb_tracker_project_gen, 1);
END;;


# Table: 'phpbb_tracker_config'
CREATE TABLE phpbb_tracker_config (
	config_name VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	config_value VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE
);;

ALTER TABLE phpbb_tracker_config ADD PRIMARY KEY (config_name);;


# Table: 'phpbb_tracker_attachments'
CREATE TABLE phpbb_tracker_attachments (
	attach_id INTEGER NOT NULL,
	ticket_id INTEGER DEFAULT 0 NOT NULL,
	post_id INTEGER DEFAULT 0 NOT NULL,
	poster_id INTEGER DEFAULT 0 NOT NULL,
	is_orphan INTEGER DEFAULT 1 NOT NULL,
	physical_filename VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	real_filename VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	extension VARCHAR(100) CHARACTER SET NONE DEFAULT '' NOT NULL,
	mimetype VARCHAR(100) CHARACTER SET NONE DEFAULT '' NOT NULL,
	filesize INTEGER DEFAULT 0 NOT NULL,
	filetime INTEGER DEFAULT 0 NOT NULL
);;

ALTER TABLE phpbb_tracker_attachments ADD PRIMARY KEY (attach_id);;

CREATE INDEX phpbb_tracker_attachments_filetime ON phpbb_tracker_attachments(filetime);;
CREATE INDEX phpbb_tracker_attachments_ticket_id ON phpbb_tracker_attachments(ticket_id);;
CREATE INDEX phpbb_tracker_attachments_post_id ON phpbb_tracker_attachments(post_id);;
CREATE INDEX phpbb_tracker_attachments_poster_id ON phpbb_tracker_attachments(poster_id);;
CREATE INDEX phpbb_tracker_attachments_is_orphan ON phpbb_tracker_attachments(is_orphan);;

CREATE GENERATOR phpbb_tracker_attachments_gen;;
SET GENERATOR phpbb_tracker_attachments_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_attachments FOR phpbb_tracker_attachments
BEFORE INSERT
AS
BEGIN
	NEW.attach_id = GEN_ID(phpbb_tracker_attachments_gen, 1);
END;;


# Table: 'phpbb_tracker_tickets'
CREATE TABLE phpbb_tracker_tickets (
	ticket_id INTEGER NOT NULL,
	project_id INTEGER DEFAULT 0 NOT NULL,
	ticket_title VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE,
	ticket_desc BLOB SUB_TYPE TEXT CHARACTER SET UTF8 DEFAULT '' NOT NULL,
	ticket_desc_bitfield VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	ticket_desc_options INTEGER DEFAULT 7 NOT NULL,
	ticket_desc_uid VARCHAR(8) CHARACTER SET NONE DEFAULT '' NOT NULL,
	ticket_status INTEGER DEFAULT 0 NOT NULL,
	ticket_hidden INTEGER DEFAULT 0 NOT NULL,
	ticket_assigned_to INTEGER DEFAULT 0 NOT NULL,
	status_id INTEGER DEFAULT 0 NOT NULL,
	component_id INTEGER DEFAULT 0 NOT NULL,
	version_id INTEGER DEFAULT 0 NOT NULL,
	severity_id INTEGER DEFAULT 0 NOT NULL,
	priority_id INTEGER DEFAULT 0 NOT NULL,
	ticket_php VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE,
	ticket_dbms VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE,
	ticket_user_id INTEGER DEFAULT 0 NOT NULL,
	ticket_time INTEGER DEFAULT 0 NOT NULL,
	last_post_user_id INTEGER DEFAULT 0 NOT NULL,
	last_post_time INTEGER DEFAULT 0 NOT NULL,
	last_visit_user_id INTEGER DEFAULT 0 NOT NULL,
	last_visit_time INTEGER DEFAULT 0 NOT NULL,
	last_visit_username VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE,
	last_visit_user_colour VARCHAR(6) CHARACTER SET NONE DEFAULT '' NOT NULL,
	edit_time INTEGER DEFAULT 0 NOT NULL,
	edit_reason VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	edit_user INTEGER DEFAULT 0 NOT NULL,
	edit_count INTEGER DEFAULT 0 NOT NULL
);;

ALTER TABLE phpbb_tracker_tickets ADD PRIMARY KEY (ticket_id);;


CREATE GENERATOR phpbb_tracker_tickets_gen;;
SET GENERATOR phpbb_tracker_tickets_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_tickets FOR phpbb_tracker_tickets
BEFORE INSERT
AS
BEGIN
	NEW.ticket_id = GEN_ID(phpbb_tracker_tickets_gen, 1);
END;;


# Table: 'phpbb_tracker_posts'
CREATE TABLE phpbb_tracker_posts (
	post_id INTEGER NOT NULL,
	ticket_id INTEGER DEFAULT 0 NOT NULL,
	post_desc BLOB SUB_TYPE TEXT CHARACTER SET UTF8 DEFAULT '' NOT NULL,
	post_desc_bitfield VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	post_desc_options INTEGER DEFAULT 7 NOT NULL,
	post_desc_uid VARCHAR(8) CHARACTER SET NONE DEFAULT '' NOT NULL,
	post_user_id INTEGER DEFAULT 0 NOT NULL,
	post_time INTEGER DEFAULT 0 NOT NULL,
	edit_time INTEGER DEFAULT 0 NOT NULL,
	edit_reason VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	edit_user INTEGER DEFAULT 0 NOT NULL,
	edit_count INTEGER DEFAULT 0 NOT NULL
);;

ALTER TABLE phpbb_tracker_posts ADD PRIMARY KEY (post_id);;


CREATE GENERATOR phpbb_tracker_posts_gen;;
SET GENERATOR phpbb_tracker_posts_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_posts FOR phpbb_tracker_posts
BEFORE INSERT
AS
BEGIN
	NEW.post_id = GEN_ID(phpbb_tracker_posts_gen, 1);
END;;


# Table: 'phpbb_tracker_components'
CREATE TABLE phpbb_tracker_components (
	component_id INTEGER NOT NULL,
	project_id INTEGER DEFAULT 0 NOT NULL,
	component_name VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE
);;

ALTER TABLE phpbb_tracker_components ADD PRIMARY KEY (component_id);;


CREATE GENERATOR phpbb_tracker_components_gen;;
SET GENERATOR phpbb_tracker_components_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_components FOR phpbb_tracker_components
BEFORE INSERT
AS
BEGIN
	NEW.component_id = GEN_ID(phpbb_tracker_components_gen, 1);
END;;


# Table: 'phpbb_tracker_history'
CREATE TABLE phpbb_tracker_history (
	history_id INTEGER NOT NULL,
	ticket_id INTEGER DEFAULT 0 NOT NULL,
	history_time INTEGER DEFAULT 0 NOT NULL,
	history_status INTEGER DEFAULT 0 NOT NULL,
	history_user_id INTEGER DEFAULT 0 NOT NULL,
	history_assigned_to INTEGER DEFAULT 0 NOT NULL,
	history_old_status INTEGER DEFAULT 0 NOT NULL,
	history_new_status INTEGER DEFAULT 0 NOT NULL
);;

ALTER TABLE phpbb_tracker_history ADD PRIMARY KEY (history_id);;


CREATE GENERATOR phpbb_tracker_history_gen;;
SET GENERATOR phpbb_tracker_history_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_history FOR phpbb_tracker_history
BEFORE INSERT
AS
BEGIN
	NEW.history_id = GEN_ID(phpbb_tracker_history_gen, 1);
END;;


# Table: 'phpbb_tracker_version'
CREATE TABLE phpbb_tracker_version (
	version_id INTEGER NOT NULL,
	project_id INTEGER DEFAULT 0 NOT NULL,
	version_name VARCHAR(255) CHARACTER SET UTF8 DEFAULT '' NOT NULL COLLATE UNICODE
);;

ALTER TABLE phpbb_tracker_version ADD PRIMARY KEY (version_id);;


CREATE GENERATOR phpbb_tracker_version_gen;;
SET GENERATOR phpbb_tracker_version_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_version FOR phpbb_tracker_version
BEFORE INSERT
AS
BEGIN
	NEW.version_id = GEN_ID(phpbb_tracker_version_gen, 1);
END;;


