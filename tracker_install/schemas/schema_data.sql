#
# $Id: schema_data.sql $
#

# POSTGRES BEGIN #

# -- Config
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('attachment_path', 'files/tracker');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('send_email', '1');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('tickets_per_page', '10');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('posts_per_page', '10');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('top_reporters', '10');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('version', '0.1.2');
# Added by Daniel Young
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('environment_enabled', '1');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('component_enabled', '1');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('version_enabled', '0');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('custom1_enabled', '0');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('custom2_enabled', '0');
INSERT INTO phpbb_tracker_config (config_name, config_value) VALUES ('viewall_enabled', '0');
# DY

# POSTGRES COMMIT #