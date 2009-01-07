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
	Table: 'phpbb_tracker_project_categories'
*/
CREATE TABLE phpbb_tracker_project_categories (
	project_cat_id number(8) NOT NULL,
	project_name varchar2(255) DEFAULT '' ,
	project_name_clean varchar2(255) DEFAULT '' ,
	CONSTRAINT pk_phpbb_tracker_project_categories PRIMARY KEY (project_cat_id)
)
/


CREATE SEQUENCE phpbb_tracker_project_categories_seq
/

CREATE OR REPLACE TRIGGER t_phpbb_tracker_project_categories
BEFORE INSERT ON phpbb_tracker_project_categories
FOR EACH ROW WHEN (
	new.project_cat_id IS NULL OR new.project_cat_id = 0
)
BEGIN
	SELECT phpbb_tracker_project_categories_seq.nextval
	INTO :new.project_cat_id
	FROM dual;
END;
/


/*
	Table: 'phpbb_tracker_project_watch'
*/
CREATE TABLE phpbb_tracker_project_watch (
	user_id number(8) DEFAULT '0' NOT NULL,
	project_id number(8) DEFAULT '0' NOT NULL,
	CONSTRAINT pk_phpbb_tracker_project_watch PRIMARY KEY (user_id, project_id)
)
/


/*
	Table: 'phpbb_tracker_tickets_watch'
*/
CREATE TABLE phpbb_tracker_tickets_watch (
	user_id number(8) DEFAULT '0' NOT NULL,
	ticket_id number(8) DEFAULT '0' NOT NULL,
	CONSTRAINT pk_phpbb_tracker_tickets_watch PRIMARY KEY (user_id, ticket_id)
)
/


