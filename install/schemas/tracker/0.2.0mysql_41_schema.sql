#
# $Id: $
#

# Table: 'phpbb_tracker_project_watch'
CREATE TABLE phpbb_tracker_project_watch (
	user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	project_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (user_id, project_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


# Table: 'phpbb_tracker_ticket_watch'
CREATE TABLE phpbb_tracker_ticket_watch (
	user_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	ticket_id mediumint(8) UNSIGNED DEFAULT '0' NOT NULL,
	PRIMARY KEY (user_id, ticket_id)
) CHARACTER SET `utf8` COLLATE `utf8_bin`;


