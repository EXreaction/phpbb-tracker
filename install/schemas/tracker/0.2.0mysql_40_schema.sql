#
# $Id: $
#

# Table: 'phpbb_tracker_project_categories'
CREATE TABLE phpbb_tracker_project_categories (
	project_cat_id mediumint(8) UNSIGNED NOT NULL auto_increment,
	project_cat_name varbinary(255) DEFAULT '' NOT NULL,
	project_cat_name_clean varbinary(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (project_cat_id)
);


# Table: 'phpbb_tracker_project_watch'
CREATE TABLE phpbb_tracker_project_watch (
	user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	project_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (user_id, project_id)
);


# Table: 'phpbb_tracker_tickets_watch'
CREATE TABLE phpbb_tracker_tickets_watch (
	user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	ticket_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (user_id, ticket_id)
);


