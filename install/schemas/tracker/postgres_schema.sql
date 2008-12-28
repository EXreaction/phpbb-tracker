/*

 $Id: $

*/

BEGIN;

/*
	Domain definition
*/
/*
CREATE DOMAIN varchar_ci AS varchar(255) NOT NULL DEFAULT ''::character varying;
*/
/*
	Operation Functions
*/
/*
CREATE FUNCTION _varchar_ci_equal(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) = LOWER($2)' LANGUAGE SQL STRICT;
CREATE FUNCTION _varchar_ci_not_equal(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) != LOWER($2)' LANGUAGE SQL STRICT;
CREATE FUNCTION _varchar_ci_less_than(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) < LOWER($2)' LANGUAGE SQL STRICT;
CREATE FUNCTION _varchar_ci_less_equal(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) <= LOWER($2)' LANGUAGE SQL STRICT;
CREATE FUNCTION _varchar_ci_greater_than(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) > LOWER($2)' LANGUAGE SQL STRICT;
CREATE FUNCTION _varchar_ci_greater_equals(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) >= LOWER($2)' LANGUAGE SQL STRICT;
*/
/*
	Operators
*/
/*
CREATE OPERATOR <(
  PROCEDURE = _varchar_ci_less_than,
  LEFTARG = varchar_ci,
  RIGHTARG = varchar_ci,
  COMMUTATOR = >,
  NEGATOR = >=,
  RESTRICT = scalarltsel,
  JOIN = scalarltjoinsel);

CREATE OPERATOR <=(
  PROCEDURE = _varchar_ci_less_equal,
  LEFTARG = varchar_ci,
  RIGHTARG = varchar_ci,
  COMMUTATOR = >=,
  NEGATOR = >,
  RESTRICT = scalarltsel,
  JOIN = scalarltjoinsel);

CREATE OPERATOR >(
  PROCEDURE = _varchar_ci_greater_than,
  LEFTARG = varchar_ci,
  RIGHTARG = varchar_ci,
  COMMUTATOR = <,
  NEGATOR = <=,
  RESTRICT = scalargtsel,
  JOIN = scalargtjoinsel);

CREATE OPERATOR >=(
  PROCEDURE = _varchar_ci_greater_equals,
  LEFTARG = varchar_ci,
  RIGHTARG = varchar_ci,
  COMMUTATOR = <=,
  NEGATOR = <,
  RESTRICT = scalargtsel,
  JOIN = scalargtjoinsel);

CREATE OPERATOR <>(
  PROCEDURE = _varchar_ci_not_equal,
  LEFTARG = varchar_ci,
  RIGHTARG = varchar_ci,
  COMMUTATOR = <>,
  NEGATOR = =,
  RESTRICT = neqsel,
  JOIN = neqjoinsel);

CREATE OPERATOR =(
  PROCEDURE = _varchar_ci_equal,
  LEFTARG = varchar_ci,
  RIGHTARG = varchar_ci,
  COMMUTATOR = =,
  NEGATOR = <>,
  RESTRICT = eqsel,
  JOIN = eqjoinsel,
  HASHES,
  MERGES,
  SORT1= <);
*/
/*
	Table: 'phpbb_tracker_project'
*/
CREATE SEQUENCE phpbb_tracker_project_seq;

CREATE TABLE phpbb_tracker_project (
	project_id INT4 DEFAULT nextval('phpbb_tracker_project_seq'),
	project_name varchar(255) DEFAULT '' NOT NULL,
	project_name_clean varchar(255) DEFAULT '' NOT NULL,
	project_desc varchar(255) DEFAULT '' NOT NULL,
	project_group INT4 DEFAULT '0' NOT NULL CHECK (project_group >= 0),
	project_type INT2 DEFAULT '0' NOT NULL,
	project_enabled INT2 DEFAULT '0' NOT NULL,
	project_security INT2 DEFAULT '0' NOT NULL,
	ticket_security INT2 DEFAULT '0' NOT NULL,
	show_php INT2 DEFAULT '0' NOT NULL,
	show_dbms INT2 DEFAULT '0' NOT NULL,
	lang_php varchar(255) DEFAULT '' NOT NULL,
	lang_dbms varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (project_id)
);


/*
	Table: 'phpbb_tracker_config'
*/
CREATE TABLE phpbb_tracker_config (
	config_name varchar(255) DEFAULT '' NOT NULL,
	config_value varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (config_name)
);


/*
	Table: 'phpbb_tracker_attachments'
*/
CREATE SEQUENCE phpbb_tracker_attachments_seq;

CREATE TABLE phpbb_tracker_attachments (
	attach_id INT4 DEFAULT nextval('phpbb_tracker_attachments_seq'),
	ticket_id INT4 DEFAULT '0' NOT NULL CHECK (ticket_id >= 0),
	post_id INT4 DEFAULT '0' NOT NULL CHECK (post_id >= 0),
	poster_id INT4 DEFAULT '0' NOT NULL CHECK (poster_id >= 0),
	is_orphan INT2 DEFAULT '1' NOT NULL CHECK (is_orphan >= 0),
	physical_filename varchar(255) DEFAULT '' NOT NULL,
	real_filename varchar(255) DEFAULT '' NOT NULL,
	extension varchar(100) DEFAULT '' NOT NULL,
	mimetype varchar(100) DEFAULT '' NOT NULL,
	filesize INT4 DEFAULT '0' NOT NULL CHECK (filesize >= 0),
	filetime INT4 DEFAULT '0' NOT NULL CHECK (filetime >= 0),
	PRIMARY KEY (attach_id)
);

CREATE INDEX phpbb_tracker_attachments_filetime ON phpbb_tracker_attachments (filetime);
CREATE INDEX phpbb_tracker_attachments_ticket_id ON phpbb_tracker_attachments (ticket_id);
CREATE INDEX phpbb_tracker_attachments_post_id ON phpbb_tracker_attachments (post_id);
CREATE INDEX phpbb_tracker_attachments_poster_id ON phpbb_tracker_attachments (poster_id);
CREATE INDEX phpbb_tracker_attachments_is_orphan ON phpbb_tracker_attachments (is_orphan);

/*
	Table: 'phpbb_tracker_tickets'
*/
CREATE SEQUENCE phpbb_tracker_tickets_seq;

CREATE TABLE phpbb_tracker_tickets (
	ticket_id INT4 DEFAULT nextval('phpbb_tracker_tickets_seq'),
	project_id INT4 DEFAULT '0' NOT NULL CHECK (project_id >= 0),
	ticket_title varchar(255) DEFAULT '' NOT NULL,
	ticket_desc TEXT DEFAULT '' NOT NULL,
	ticket_desc_bitfield varchar(255) DEFAULT '' NOT NULL,
	ticket_desc_options INT4 DEFAULT '7' NOT NULL CHECK (ticket_desc_options >= 0),
	ticket_desc_uid varchar(8) DEFAULT '' NOT NULL,
	ticket_status INT2 DEFAULT '0' NOT NULL,
	ticket_hidden INT2 DEFAULT '0' NOT NULL,
	ticket_security INT2 DEFAULT '0' NOT NULL,
	ticket_assigned_to INT4 DEFAULT '0' NOT NULL,
	status_id INT2 DEFAULT '0' NOT NULL,
	component_id INT4 DEFAULT '0' NOT NULL CHECK (component_id >= 0),
	version_id INT4 DEFAULT '0' NOT NULL CHECK (version_id >= 0),
	severity_id INT4 DEFAULT '0' NOT NULL CHECK (severity_id >= 0),
	priority_id INT4 DEFAULT '0' NOT NULL CHECK (priority_id >= 0),
	ticket_php varchar(255) DEFAULT '' NOT NULL,
	ticket_dbms varchar(255) DEFAULT '' NOT NULL,
	ticket_user_id INT4 DEFAULT '0' NOT NULL CHECK (ticket_user_id >= 0),
	ticket_time INT4 DEFAULT '0' NOT NULL,
	last_post_user_id INT4 DEFAULT '0' NOT NULL CHECK (last_post_user_id >= 0),
	last_post_time INT4 DEFAULT '0' NOT NULL,
	last_visit_user_id INT4 DEFAULT '0' NOT NULL CHECK (last_visit_user_id >= 0),
	last_visit_time INT4 DEFAULT '0' NOT NULL CHECK (last_visit_time >= 0),
	last_visit_username varchar(255) DEFAULT '' NOT NULL,
	last_visit_user_colour varchar(6) DEFAULT '' NOT NULL,
	edit_time INT4 DEFAULT '0' NOT NULL,
	edit_reason varchar(255) DEFAULT '' NOT NULL,
	edit_user INT4 DEFAULT '0' NOT NULL CHECK (edit_user >= 0),
	edit_count INT2 DEFAULT '0' NOT NULL CHECK (edit_count >= 0),
	PRIMARY KEY (ticket_id)
);


/*
	Table: 'phpbb_tracker_posts'
*/
CREATE SEQUENCE phpbb_tracker_posts_seq;

CREATE TABLE phpbb_tracker_posts (
	post_id INT4 DEFAULT nextval('phpbb_tracker_posts_seq'),
	ticket_id INT4 DEFAULT '0' NOT NULL CHECK (ticket_id >= 0),
	post_desc TEXT DEFAULT '' NOT NULL,
	post_desc_bitfield varchar(255) DEFAULT '' NOT NULL,
	post_desc_options INT4 DEFAULT '7' NOT NULL CHECK (post_desc_options >= 0),
	post_desc_uid varchar(8) DEFAULT '' NOT NULL,
	post_user_id INT4 DEFAULT '0' NOT NULL CHECK (post_user_id >= 0),
	post_time INT4 DEFAULT '0' NOT NULL,
	edit_time INT4 DEFAULT '0' NOT NULL,
	edit_reason varchar(255) DEFAULT '' NOT NULL,
	edit_user INT4 DEFAULT '0' NOT NULL CHECK (edit_user >= 0),
	edit_count INT2 DEFAULT '0' NOT NULL CHECK (edit_count >= 0),
	PRIMARY KEY (post_id)
);


/*
	Table: 'phpbb_tracker_components'
*/
CREATE SEQUENCE phpbb_tracker_components_seq;

CREATE TABLE phpbb_tracker_components (
	component_id INT4 DEFAULT nextval('phpbb_tracker_components_seq'),
	project_id INT4 DEFAULT '0' NOT NULL CHECK (project_id >= 0),
	component_name varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (component_id)
);


/*
	Table: 'phpbb_tracker_history'
*/
CREATE SEQUENCE phpbb_tracker_history_seq;

CREATE TABLE phpbb_tracker_history (
	history_id INT4 DEFAULT nextval('phpbb_tracker_history_seq'),
	ticket_id INT4 DEFAULT '0' NOT NULL CHECK (ticket_id >= 0),
	history_time INT4 DEFAULT '0' NOT NULL,
	history_status INT4 DEFAULT '0' NOT NULL CHECK (history_status >= 0),
	history_user_id INT4 DEFAULT '0' NOT NULL,
	history_assigned_to INT4 DEFAULT '0' NOT NULL,
	history_old_status INT4 DEFAULT '0' NOT NULL CHECK (history_old_status >= 0),
	history_new_status INT4 DEFAULT '0' NOT NULL CHECK (history_new_status >= 0),
	history_old_priority INT4 DEFAULT '0' NOT NULL CHECK (history_old_priority >= 0),
	history_new_priority INT4 DEFAULT '0' NOT NULL CHECK (history_new_priority >= 0),
	history_old_severity INT4 DEFAULT '0' NOT NULL CHECK (history_old_severity >= 0),
	history_new_severity INT4 DEFAULT '0' NOT NULL CHECK (history_new_severity >= 0),
	PRIMARY KEY (history_id)
);


/*
	Table: 'phpbb_tracker_version'
*/
CREATE SEQUENCE phpbb_tracker_version_seq;

CREATE TABLE phpbb_tracker_version (
	version_id INT4 DEFAULT nextval('phpbb_tracker_version_seq'),
	project_id INT4 DEFAULT '0' NOT NULL CHECK (project_id >= 0),
	version_name varchar(255) DEFAULT '' NOT NULL,
	version_enabled INT2 DEFAULT '1' NOT NULL,
	PRIMARY KEY (version_id)
);


/*
	Table: 'phpbb_tracker_project_watch'
*/
CREATE TABLE phpbb_tracker_project_watch (
	user_id INT4 DEFAULT '0' NOT NULL CHECK (user_id >= 0),
	project_id INT4 DEFAULT '0' NOT NULL CHECK (project_id >= 0),
	PRIMARY KEY (user_id, project_id)
);


/*
	Table: 'phpbb_tracker_tickets_watch'
*/
CREATE TABLE phpbb_tracker_tickets_watch (
	user_id INT4 DEFAULT '0' NOT NULL CHECK (user_id >= 0),
	ticket_id INT4 DEFAULT '0' NOT NULL CHECK (ticket_id >= 0),
	PRIMARY KEY (user_id, ticket_id)
);



COMMIT;