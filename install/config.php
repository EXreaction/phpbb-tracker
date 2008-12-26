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
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Mod installation script created for phpbb Arcade
* by JRSweets. This can easily be modifed for
* use with any mod.
* @copyright (c) 2008 http://www.JeffRusso.net
* Some config values so the script can be used for different mods.
* EDIT VALUES BELOW
*/

$mod_config = array(
	'mod_title'					=> 'phpBB Tracker',
	'mod_version'				=> '0.2.0',
	'phpbb_version'				=> '3.0.0',
	'data_file'					=> 'schemas/tracker/schema_data.sql',
	'remove_data_file'			=> 'schemas/tracker/remove_schema_data.sql',
	'pg_remove_data_file'		=> 'schemas/tracker/postgres_remove_schema_data.sql',
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
		// Change from version 0.1.0 to 0.1.1
		'0.1.1'	=> array(
			'add_columns'		=> array(
				TRACKER_PROJECT_TABLE	=> array(
					'project_name_clean'		=> array('VCHAR', ''),
				),
			),
			'change_columns'		=> array(
				TRACKER_TICKETS_TABLE	=> array(
					'ticket_desc'	=> array('MTEXT_UNI', ''),
				),
				TRACKER_POSTS_TABLE	=> array(
					'post_desc'	=> array('MTEXT_UNI', ''),
				),
			),
		),
		'0.1.2'	=> array(
			'change_columns'		=> array(
				TRACKER_PROJECT_TABLE	=> array(
					'project_name'		=> array('VCHAR', ''),
				),
			),
		),
		'0.2.0'	=> array(
			'add_columns'		=> array(
				TRACKER_VERSION_TABLE	=> array(
					'version_enabled'		=> array('TINT:4', 1),
				),
				TRACKER_PROJECT_TABLE	=> array(
					'show_php'				=> array('TINT:4', 0),
					'show_dbms'				=> array('TINT:4', 0),
					'lang_php'				=> array('VCHAR', 'TRACKER_TICKET_PHP'),
					'lang_dbms'				=> array('VCHAR', 'TRACKER_TICKET_DBMS'),
				),
			),
		),
	),
	'update_permission_options' => array(
		'0.2.0'	=> array(
			'local'		=> array(),
			'global'	=> array('u_tracker_delete_all', 'u_tracker_delete_global', 'u_tracker_edit_global','u_tracker_edit_all'),
		),
	),
);

$mod_config['install_check'] = array(
	'tables'		=> array(TRACKER_CONFIG_TABLE, TRACKER_ATTACHMENTS_TABLE, TRACKER_PROJECT_TABLE, TRACKER_TICKETS_TABLE, TRACKER_POSTS_TABLE, TRACKER_COMPONENTS_TABLE, TRACKER_HISTORY_TABLE, TRACKER_VERSION_TABLE, TRACKER_PROJECT_WATCH_TABLE, TRACKER_TICKETS_WATCH_TABLE),
	'files' 		=> array(
		'core'			=> array(
			'tracker.php',
			'adm/style/acp_tracker.html',
			'includes/acp/acp_tracker.php',
			'includes/acp/info/acp_tracker.php',
			'includes/tracker/tracker_api.php',
			'includes/tracker/tracker_cache.php',
			'includes/tracker/tracker_class.php',
			'includes/tracker/tracker_constants.php',
			'includes/tracker/tracker_download.php',
			'includes/tracker/tracker_status.php',
			'includes/tracker/tracker_types.php',
			'includes/tracker/tracker_viewonline.php',
			'includes/tracker/functions_files.php',
			'language/en/email/tracker_notify.txt',
			'language/en/email/tracker_notify_comment.txt',
			'language/en/email/tracker_notify_status_double.txt',
			'language/en/email/tracker_notify_status_single.txt',
			'language/en/mods/tracker.php',
			'language/en/mods/tracker_install.php',
			'language/en/mods/permissions_tracker.php',
			'language/en/mods/info_acp_tracker.php',
		),
		'styles'			=> array(
			'prosilver'			=> array(
				'styles/prosilver/template/tracker/tracker_index_body.html',
				'styles/prosilver/template/tracker/tracker_tickets_add_body.html',
				'styles/prosilver/template/tracker/tracker_tickets_body.html',
				'styles/prosilver/template/tracker/tracker_tickets_view_body.html',
				'styles/prosilver/template/tracker/tracker_header.html',
				'styles/prosilver/template/tracker/tracker_move.html',
			),
			'subsilver2'		=> array(		
				'styles/subsilver2/template/tracker/tracker_index_body.html',
				'styles/subsilver2/template/tracker/tracker_tickets_add_body.html',
				'styles/subsilver2/template/tracker/tracker_tickets_body.html',
				'styles/subsilver2/template/tracker/tracker_tickets_view_body.html',
				'styles/subsilver2/template/tracker/tracker_header.html',
				'styles/subsilver2/template/tracker/tracker_move.html',
			),
		),
	),
	'edits'	=> array(
		'core'		=> array(
			'viewonline.php' => array(
				'case \'tracker\':',
				'include($phpbb_root_path . \'includes/tracker/tracker_viewonline.\' . $phpEx);',
			),
			'includes/functions.php' => array(
				'global $tracker;',
				'$user->add_lang(\'mods/tracker\');',
				'$template->assign_var(\'U_TRACKER\', append_sid("{$phpbb_root_path}tracker.$phpEx"));',
			),
		),
		'styles'		=> array(
			'prosilver'		=> array(
				'styles/prosilver/template/overall_header.html' => array(
					'<!-- IF S_IN_TRACKER -->',
					'<!-- INCLUDE tracker/tracker_header.html -->',
				),
			),
			'subsilver2'	=> array(
				'styles/subsilver2/template/overall_header.html' => array(
					'<!-- IF S_IN_TRACKER -->',
					'<!-- INCLUDE tracker/tracker_header.html -->',
				),
			),
		),
	),
	'modules'		=> array(
		'acp' => array(
			'tracker' 					=> array('settings', 'project', 'component', 'version'),
		),
	),
);

?>