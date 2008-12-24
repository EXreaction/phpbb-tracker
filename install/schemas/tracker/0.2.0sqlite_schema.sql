#
# $Id: $
#

BEGIN TRANSACTION;

# Table: 'phpbb_tracker_project_watch'
CREATE TABLE phpbb_tracker_project_watch (
	user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	project_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (user_id, project_id)
);


# Table: 'phpbb_tracker_ticket_watch'
CREATE TABLE phpbb_tracker_ticket_watch (
	user_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	ticket_id INTEGER UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (user_id, ticket_id)
);



COMMIT;