#
# $Id: $
#


# Table: 'phpbb_tracker_project_watch'
CREATE TABLE phpbb_tracker_project_watch (
	user_id INTEGER DEFAULT 0 NOT NULL,
	project_id INTEGER DEFAULT 0 NOT NULL
);;

ALTER TABLE phpbb_tracker_project_watch ADD PRIMARY KEY (user_id, project_id);;


# Table: 'phpbb_tracker_tickets_watch'
CREATE TABLE phpbb_tracker_tickets_watch (
	user_id INTEGER DEFAULT 0 NOT NULL,
	ticket_id INTEGER DEFAULT 0 NOT NULL
);;

ALTER TABLE phpbb_tracker_tickets_watch ADD PRIMARY KEY (user_id, ticket_id);;


