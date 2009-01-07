#
# $Id: $
#

BEGIN TRANSACTION;

# Table: 'phpbb_tracker_project_categories'
CREATE TABLE phpbb_tracker_project_categories (
	project_cat_id INTEGER PRIMARY KEY NOT NULL ,
	project_name varchar(255) NOT NULL DEFAULT '',
	project_name_clean varchar(255) NOT NULL DEFAULT ''
);


# Table: 'phpbb_tracker_project_watch'
CREATE TABLE phpbb_tracker_project_watch (
	user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	project_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (user_id, project_id)
);


# Table: 'phpbb_tracker_tickets_watch'
CREATE TABLE phpbb_tracker_tickets_watch (
	user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	ticket_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (user_id, ticket_id)
);



COMMIT;