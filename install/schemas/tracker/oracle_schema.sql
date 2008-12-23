/*

 $Id: $

*/

/*
  This first section is optional, however its probably the best method
  of running phpBB on Oracle. If you already have a tablespace and user created
  for phpBB you can leave this section commented out!

  The first set of statements create a phpBB tablespace and a phpBB user,
  make sure you change the password of the phpBB user before you run this script!!
*/

/*
CREATE TABLESPACE "PHPBB"
	LOGGING
	DATAFILE 'E:\ORACLE\ORADATA\LOCAL\PHPBB.ora'
	SIZE 10M
	AUTOEXTEND ON NEXT 10M
	MAXSIZE 100M;

CREATE USER "PHPBB"
	PROFILE "DEFAULT"
	IDENTIFIED BY "phpbb_password"
	DEFAULT TABLESPACE "PHPBB"
	QUOTA UNLIMITED ON "PHPBB"
	ACCOUNT UNLOCK;

GRANT ANALYZE ANY TO "PHPBB";
GRANT CREATE SEQUENCE TO "PHPBB";
GRANT CREATE SESSION TO "PHPBB";
GRANT CREATE TABLE TO "PHPBB";
GRANT CREATE TRIGGER TO "PHPBB";
GRANT CREATE VIEW TO "PHPBB";
GRANT "CONNECT" TO "PHPBB";

COMMIT;
DISCONNECT;

CONNECT phpbb/phpbb_password;
*/
/*
	Table: 'phpbb_tracker_project'
*/
CREATE TABLE phpbb_tracker_project (
	project_id number(8) NOT NULL,
	project_name varchar2(255) DEFAULT '' ,
	project_name_clean varchar2(255) DEFAULT '' ,
	project_desc varchar2(765) DEFAULT '' ,
	project_group number(8) DEFAULT '0' NOT NULL,
	project_type number(4) DEFAULT '0' NOT NULL,
	project_enabled number(4) DEFAULT '0' NOT NULL,
	project_security number(4) DEFAULT '0' NOT NULL,
	CONSTRAINT pk_phpbb_tracker_project PRIMARY KEY (project_id)
)
/


CREATE SEQUENCE phpbb_tracker_project_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_project
BEFORE INSERT ON phpbb_tracker_project
FOR EACH ROW WHEN (
	new.project_id IS NULL OR new.project_id = 0
)
BEGIN
	SELECT phpbb_tracker_project_seq.nextval
	INTO :new.project_id
	FROM dual;
END;
/


/*
	Table: 'phpbb_tracker_config'
*/
CREATE TABLE phpbb_tracker_config (
	config_name varchar2(255) DEFAULT '' ,
	config_value varchar2(765) DEFAULT '' ,
	CONSTRAINT pk_phpbb_tracker_config PRIMARY KEY (config_name)
)
/


/*
	Table: 'phpbb_tracker_attachments'
*/
CREATE TABLE phpbb_tracker_attachments (
	attach_id number(8) NOT NULL,
	ticket_id number(8) DEFAULT '0' NOT NULL,
	post_id number(8) DEFAULT '0' NOT NULL,
	poster_id number(8) DEFAULT '0' NOT NULL,
	is_orphan number(1) DEFAULT '1' NOT NULL,
	physical_filename varchar2(255) DEFAULT '' ,
	real_filename varchar2(255) DEFAULT '' ,
	extension varchar2(100) DEFAULT '' ,
	mimetype varchar2(100) DEFAULT '' ,
	filesize number(20) DEFAULT '0' NOT NULL,
	filetime number(11) DEFAULT '0' NOT NULL,
	CONSTRAINT pk_phpbb_tracker_attachments PRIMARY KEY (attach_id)
)
/

CREATE INDEX phpbb_tracker_attachments_filetime ON phpbb_tracker_attachments (filetime)
/
CREATE INDEX phpbb_tracker_attachments_ticket_id ON phpbb_tracker_attachments (ticket_id)
/
CREATE INDEX phpbb_tracker_attachments_post_id ON phpbb_tracker_attachments (post_id)
/
CREATE INDEX phpbb_tracker_attachments_poster_id ON phpbb_tracker_attachments (poster_id)
/
CREATE INDEX phpbb_tracker_attachments_is_orphan ON phpbb_tracker_attachments (is_orphan)
/

CREATE SEQUENCE phpbb_tracker_attachments_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_attachments
BEFORE INSERT ON phpbb_tracker_attachments
FOR EACH ROW WHEN (
	new.attach_id IS NULL OR new.attach_id = 0
)
BEGIN
	SELECT phpbb_tracker_attachments_seq.nextval
	INTO :new.attach_id
	FROM dual;
END;
/


/*
	Table: 'phpbb_tracker_tickets'
*/
CREATE TABLE phpbb_tracker_tickets (
	ticket_id number(8) NOT NULL,
	project_id number(8) DEFAULT '0' NOT NULL,
	ticket_title varchar2(765) DEFAULT '' ,
	ticket_desc clob DEFAULT '' ,
	ticket_desc_bitfield varchar2(255) DEFAULT '' ,
	ticket_desc_options number(11) DEFAULT '7' NOT NULL,
	ticket_desc_uid varchar2(8) DEFAULT '' ,
	ticket_status number(4) DEFAULT '0' NOT NULL,
	ticket_hidden number(4) DEFAULT '0' NOT NULL,
	ticket_assigned_to number(8) DEFAULT '0' NOT NULL,
	status_id number(4) DEFAULT '0' NOT NULL,
	component_id number(8) DEFAULT '0' NOT NULL,
	version_id number(8) DEFAULT '0' NOT NULL,
	severity_id number(8) DEFAULT '0' NOT NULL,
	priority_id number(8) DEFAULT '0' NOT NULL,
	ticket_php varchar2(765) DEFAULT '' ,
	ticket_dbms varchar2(765) DEFAULT '' ,
	ticket_user_id number(8) DEFAULT '0' NOT NULL,
	ticket_time number(11) DEFAULT '0' NOT NULL,
	last_post_user_id number(8) DEFAULT '0' NOT NULL,
	last_post_time number(11) DEFAULT '0' NOT NULL,
	last_visit_user_id number(8) DEFAULT '0' NOT NULL,
	last_visit_time number(11) DEFAULT '0' NOT NULL,
	last_visit_username varchar2(765) DEFAULT '' ,
	last_visit_user_colour varchar2(6) DEFAULT '' ,
	edit_time number(11) DEFAULT '0' NOT NULL,
	edit_reason varchar2(255) DEFAULT '' ,
	edit_user number(8) DEFAULT '0' NOT NULL,
	edit_count number(4) DEFAULT '0' NOT NULL,
	CONSTRAINT pk_phpbb_tracker_tickets PRIMARY KEY (ticket_id)
)
/


CREATE SEQUENCE phpbb_tracker_tickets_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_tickets
BEFORE INSERT ON phpbb_tracker_tickets
FOR EACH ROW WHEN (
	new.ticket_id IS NULL OR new.ticket_id = 0
)
BEGIN
	SELECT phpbb_tracker_tickets_seq.nextval
	INTO :new.ticket_id
	FROM dual;
END;
/


/*
	Table: 'phpbb_tracker_posts'
*/
CREATE TABLE phpbb_tracker_posts (
	post_id number(8) NOT NULL,
	ticket_id number(8) DEFAULT '0' NOT NULL,
	post_desc clob DEFAULT '' ,
	post_desc_bitfield varchar2(255) DEFAULT '' ,
	post_desc_options number(11) DEFAULT '7' NOT NULL,
	post_desc_uid varchar2(8) DEFAULT '' ,
	post_user_id number(8) DEFAULT '0' NOT NULL,
	post_time number(11) DEFAULT '0' NOT NULL,
	edit_time number(11) DEFAULT '0' NOT NULL,
	edit_reason varchar2(255) DEFAULT '' ,
	edit_user number(8) DEFAULT '0' NOT NULL,
	edit_count number(4) DEFAULT '0' NOT NULL,
	CONSTRAINT pk_phpbb_tracker_posts PRIMARY KEY (post_id)
)
/


CREATE SEQUENCE phpbb_tracker_posts_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_posts
BEFORE INSERT ON phpbb_tracker_posts
FOR EACH ROW WHEN (
	new.post_id IS NULL OR new.post_id = 0
)
BEGIN
	SELECT phpbb_tracker_posts_seq.nextval
	INTO :new.post_id
	FROM dual;
END;
/


/*
	Table: 'phpbb_tracker_components'
*/
CREATE TABLE phpbb_tracker_components (
	component_id number(8) NOT NULL,
	project_id number(8) DEFAULT '0' NOT NULL,
	component_name varchar2(765) DEFAULT '' ,
	CONSTRAINT pk_phpbb_tracker_components PRIMARY KEY (component_id)
)
/


CREATE SEQUENCE phpbb_tracker_components_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_components
BEFORE INSERT ON phpbb_tracker_components
FOR EACH ROW WHEN (
	new.component_id IS NULL OR new.component_id = 0
)
BEGIN
	SELECT phpbb_tracker_components_seq.nextval
	INTO :new.component_id
	FROM dual;
END;
/


/*
	Table: 'phpbb_tracker_history'
*/
CREATE TABLE phpbb_tracker_history (
	history_id number(8) NOT NULL,
	ticket_id number(8) DEFAULT '0' NOT NULL,
	history_time number(11) DEFAULT '0' NOT NULL,
	history_status number(8) DEFAULT '0' NOT NULL,
	history_user_id number(8) DEFAULT '0' NOT NULL,
	history_assigned_to number(8) DEFAULT '0' NOT NULL,
	history_old_status number(8) DEFAULT '0' NOT NULL,
	history_new_status number(8) DEFAULT '0' NOT NULL,
	history_old_priority number(8) DEFAULT '0' NOT NULL,
	history_new_priority number(8) DEFAULT '0' NOT NULL,
	history_old_severity number(8) DEFAULT '0' NOT NULL,
	history_new_severity number(8) DEFAULT '0' NOT NULL,
	CONSTRAINT pk_phpbb_tracker_history PRIMARY KEY (history_id)
)
/


CREATE SEQUENCE phpbb_tracker_history_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_history
BEFORE INSERT ON phpbb_tracker_history
FOR EACH ROW WHEN (
	new.history_id IS NULL OR new.history_id = 0
)
BEGIN
	SELECT phpbb_tracker_history_seq.nextval
	INTO :new.history_id
	FROM dual;
END;
/


/*
	Table: 'phpbb_tracker_version'
*/
CREATE TABLE phpbb_tracker_version (
	version_id number(8) NOT NULL,
	project_id number(8) DEFAULT '0' NOT NULL,
	version_name varchar2(765) DEFAULT '' ,
	CONSTRAINT pk_phpbb_tracker_version PRIMARY KEY (version_id)
)
/


CREATE SEQUENCE phpbb_tracker_version_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_version
BEFORE INSERT ON phpbb_tracker_version
FOR EACH ROW WHEN (
	new.version_id IS NULL OR new.version_id = 0
)
BEGIN
	SELECT phpbb_tracker_version_seq.nextval
	INTO :new.version_id
	FROM dual;
END;
/


