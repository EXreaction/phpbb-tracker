<?php
/**
*
* @package tracker
* @version $Id$
* @copyright (c) 2008 http://www.jeffrusso.net
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* Some config values so the script can be used for different mods.
* Currently only setup to add parent modules to .MODS tab in ACP
* then add the mods modules to this new parent.
* EDIT VALUES BELOW
*/

$CFG = array(
	'mod_title'					=> 'phpBB Tracker',
	'mod_version'				=> '0.1.3',
	'mod_copyright'				=> 'Powered by phpBB Tracker 0.1.2 BETA &copy; 2008 <a href="http://www.jeffrusso.net">JRSweets</a><br />',
	'mod_dir'					=> 'tracker_install',
	'clear_cache_install'		=> true,
	'clear_cache_uninstall'		=> true,
	'clear_cache_update'		=> true,
	'data_file'					=> 'schemas/schema_data.sql',
	'remove_data_file'			=> 'schemas/remove_schema_data.sql',
	'remove_permissions_file'	=> 'schemas/remove_permissions_data.sql',
	'parent_module_remove'		=> array('ACP_TRACKER'),
	'module_remove'				=> array('tracker'),
	'permission_options'		=> array(
		'local'		=> array(),
		'global'	=> array('u_tracker_view', 'u_tracker_post', 'u_tracker_delete_all', 'u_tracker_delete_global', 'u_tracker_edit', 'u_tracker_edit_global','u_tracker_edit_all', 'u_tracker_attach', 'u_tracker_download', 'a_tracker'),
	),
	'mod_modules'				=> array(
		array(
			'parent_module_data'	=> array(
				'module_basename' 	=> '',
				'module_enabled'	=> '1',
				'module_display' 	=> '1',
				'parent_id' 		=> '0',
				'module_class' 		=> 'acp',
				'module_langname' 	=> 'ACP_TRACKER',
				'module_mode' 		=> '',
				'module_auth' 		=> '',
			),
			'module_data'			=> array(
				array(
					'module_basename'	=> 'tracker',
					'module_enabled' 	=> '1',
					'module_display' 	=> '1',
					'module_class' 		=> 'acp',
					'module_langname' 	=> 'ACP_TRACKER_SETTINGS',
					'module_mode' 		=> 'settings',
					'module_auth' 		=> 'acl_a_tracker',
				),
				array(
					'module_basename'	=> 'tracker',
					'module_enabled' 	=> '1',
					'module_display' 	=> '1',
					'module_class' 		=> 'acp',
					'module_langname' 	=> 'ACP_TRACKER_ATTACHMENTS',
					'module_mode' 		=> 'attachments',
					'module_auth' 		=> 'acl_a_tracker',
				),
				array(
					'module_basename'	=> 'tracker',
					'module_enabled' 	=> '1',
					'module_display' 	=> '1',
					'module_class' 		=> 'acp',
					'module_langname' 	=> 'ACP_TRACKER_PROJECT',
					'module_mode' 		=> 'project',
					'module_auth' 		=> 'acl_a_tracker',
				),
				array(
					'module_basename'	=> 'tracker',
					'module_enabled' 	=> '1',
					'module_display' 	=> '1',
					'module_class' 		=> 'acp',
					'module_langname' 	=> 'ACP_TRACKER_COMPONENT',
					'module_mode' 		=> 'component',
					'module_auth' 		=> 'acl_a_tracker',
				),
				array(
					'module_basename'	=> 'tracker',
					'module_enabled' 	=> '1',
					'module_display' 	=> '1',
					'module_class' 		=> 'acp',
					'module_langname' 	=> 'ACP_TRACKER_VERSION',
					'module_mode' 		=> 'version',
					'module_auth' 		=> 'acl_a_tracker',
				),
			),
		),
	),
	'update_schema_changes'	=> array(
		//Change from version 0.1.0 to 0.1.1
		'0.1.1'	=> array(
			'add_columns'		=> array(
				$table_prefix . 'tracker_project'	=> array(
					'project_name_clean'		=> array('VCHAR', ''),
				),		
			),
			'change_columns'		=> array(
				$table_prefix . 'tracker_tickets'	=> array(
					'ticket_desc'	=> array('MTEXT_UNI', ''),
				),
				$table_prefix . 'tracker_posts'	=> array(
					'post_desc'	=> array('MTEXT_UNI', ''),
				),
			),
		),
		'0.1.2'	=> array(
			'change_columns'		=> array(
				$table_prefix . 'tracker_project'	=> array(
					'project_name'		=> array('VCHAR', ''),
				),		
			),
		),
	),
	'update_permission_options' => array(
		'0.1.3'	=> array(
			'local'		=> array(),
			'global'	=> array('u_tracker_delete_all', 'u_tracker_delete_global', 'u_tracker_edit_global','u_tracker_edit_all'),
		),
	),
);

?>