#
# $Id: remove_schema_data.sql $
#

# POSTGRES BEGIN #

# -- Drop Mod Tables
DROP SEQUENCE phpbb_tracker_project_seq;
DROP SEQUENCE phpbb_tracker_attachments_seq;
DROP SEQUENCE phpbb_tracker_tickets_seq;
DROP SEQUENCE phpbb_tracker_posts_seq;
DROP SEQUENCE phpbb_tracker_components_seq;
DROP SEQUENCE phpbb_tracker_history_seq;
DROP SEQUENCE phpbb_tracker_version_seq;

# POSTGRES COMMIT #