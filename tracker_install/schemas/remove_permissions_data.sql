#
# $Id: remove_permission_data.sql $
#

# POSTGRES BEGIN #

# -- Remove Permmissions
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_view";
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_post";
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_edit";
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_attach";
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_download";
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_delete_all";
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_delete_global"; 
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_edit_global";
DELETE FROM phpbb_acl_options WHERE auth_option = "u_tracker_edit_all";
DELETE FROM phpbb_acl_options WHERE auth_option = "a_tracker";

# POSTGRES COMMIT #