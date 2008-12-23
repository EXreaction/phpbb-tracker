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
* Some config values so the script can be used for different mods.
* Currently only setup to add parent modules to .MODS tab in ACP
* then add the mods modules to this new parent.
* EDIT VALUES BELOW
*/

global $table_prefix;

$CFG = array(
	'mod_title'					=> 'phpBB Tracker',
	'mod_version'				=> '0.1.3',
	'mod_copyright'				=> 'Powered by phpBB Tracker 0.1.3 BETA &copy; 2008 <a href="http://www.jeffrusso.net">JRSweets</a><br />',
	'mod_dir'					=> 'tracker_install',
	'clear_cache_install'		=> true,
	'clear_cache_uninstall'		=> true,
	'clear_cache_update'		=> true,
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
		array('acp', 'acp_tracker'),
	),
	'update_schema_changes'	=> array(
		// Change from version 0.1.0 to 0.1.1
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