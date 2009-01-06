#
# $Id: $
#


# Table: 'phpbb_tracker_project_categories'
CREATE TABLE phpbb_tracker_project_categories (
	project_cat_id INTEGER NOT NULL,
	project_cat_name VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL,
	project_cat_name_clean VARCHAR(255) CHARACTER SET NONE DEFAULT '' NOT NULL
);;

ALTER TABLE phpbb_tracker_project_categories ADD PRIMARY KEY (project_cat_id);;


CREATE GENERATOR phpbb_tracker_project_categories_gen;;
SET GENERATOR phpbb_tracker_project_categories_gen TO 0;;

CREATE TRIGGER t_phpbb_tracker_project_categories FOR phpbb_tracker_project_categories
BEFORE INSERT
AS
BEGIN
	NEW.project_cat_id = GEN_ID(phpbb_tracker_project_categories_gen, 1);
END;;


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


