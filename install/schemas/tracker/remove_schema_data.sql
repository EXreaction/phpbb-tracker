#
# $Id: remove_schema_data.sql $
#

# POSTGRES BEGIN #

# -- Drop Mod Tables
DROP TABLE phpbb_tracker_config;
DROP TABLE phpbb_tracker_attachments;
DROP TABLE phpbb_tracker_project_categories;
DROP TABLE phpbb_tracker_project;
DROP TABLE phpbb_tracker_tickets;
DROP TABLE phpbb_tracker_components;
DROP TABLE phpbb_tracker_posts;
DROP TABLE phpbb_tracker_history;
DROP TABLE phpbb_tracker_version;
DROP TABLE phpbb_tracker_project_watch;
DROP TABLE phpbb_tracker_tickets_watch;
# POSTGRES COMMIT #