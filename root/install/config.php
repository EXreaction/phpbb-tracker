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
	'version'	=> array(
		'current' 	=> '0.5.1',
		'oldest'	=> '0.1.0',
		'phpbb'		=> '3.0.8',
	),
	'data_file'		=> array(
		'add'		=> '',
		'remove'	=> '',
	),
	'permission_options'		=> array(
		'phpbb'		=> array(
			'local'		=> array(),
			'global'	=> array('u_tracker_view', 'u_tracker_post', 'u_tracker_delete_all', 'u_tracker_delete_global', 'u_tracker_edit', 'u_tracker_edit_global','u_tracker_edit_all', 'u_tracker_attach', 'u_tracker_download', 'u_tracker_ticket_security', 'a_tracker'),
		),
		'update'	=> array(
			'0.2.0'=> array(
				'phpbb'	=> array(
					'local'		=> array(),
					'global'	=> array('u_tracker_delete_all', 'u_tracker_delete_global', 'u_tracker_edit_global','u_tracker_edit_all', 'u_tracker_ticket_security'),
				),
			),
		),
	),
	'modules'				=> array(
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
				array(
					'module_basename'	=> 'tracker',
					'module_enabled' 	=> '1',
					'module_display' 	=> '1',
					'module_class' 		=> 'acp',
					'module_langname' 	=> 'ACP_TRACKER_ATTACHMENTS',
					'module_mode' 		=> 'attachments',
					'module_auth' 		=> 'acl_a_tracker',
				),
			),
		),
	),
	'modules_remove'	=> array(
		'modules'		=> array('tracker'),
		'parents'		=> array('ACP_TRACKER'),
	),
	'schema_changes'=> array(
		'add'		=> array(),
		'update'	=> array(
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
						'version_enabled'		=> array('TINT:1', 1),
					),
					TRACKER_PROJECT_TABLE	=> array(
						'project_cat_id'		=> array('UINT', 0),
						'show_php'				=> array('TINT:1', 0),
						'show_dbms'				=> array('TINT:1', 0),
						'ticket_security'		=> array('TINT:1', 0),
						'lang_php'				=> array('VCHAR', 'TRACKER_TICKET_PHP'),
						'lang_dbms'				=> array('VCHAR', 'TRACKER_TICKET_DBMS'),
					),
					TRACKER_TICKETS_TABLE	=> array(
						'ticket_security'		=> array('TINT:1', 0),
					),
				),
				'change_columns'		=> array(
					TRACKER_TICKETS_TABLE	=> array(
						'ticket_status'					=> array('TINT:1', 0),
						'ticket_hidden'					=> array('TINT:1', 0),
					),
					TRACKER_PROJECT_TABLE	=> array(
						'project_cat_id'		=> array('UINT', 0),
						'project_type'			=> array('TINT:2', 0),
						'project_enabled'		=> array('TINT:1', 0),
						'project_security'		=> array('TINT:1', 0),
					),
				),
			),
		),
		'remove'	=> array(),
	),
);

$mod_config['verify'] = array(
	'tables'		=> array(TRACKER_CONFIG_TABLE, TRACKER_ATTACHMENTS_TABLE, TRACKER_PROJECT_CATS_TABLE, TRACKER_PROJECT_TABLE, TRACKER_TICKETS_TABLE, TRACKER_POSTS_TABLE, TRACKER_COMPONENTS_TABLE, TRACKER_HISTORY_TABLE, TRACKER_VERSION_TABLE, TRACKER_PROJECT_WATCH_TABLE, TRACKER_TICKETS_WATCH_TABLE),
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
		),
		'langs'			=> array(
			'en'			=> array(
				'language/en/email/tracker_notify.txt',
				'language/en/email/tracker_notify_comment.txt',
				'language/en/email/tracker_notify_status_double.txt',
				'language/en/email/tracker_notify_status_single.txt',
				'language/en/mods/tracker.php',
				'language/en/mods/tracker_install.php',
				'language/en/mods/permissions_tracker.php',
				'language/en/mods/info_acp_tracker.php',
			),
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