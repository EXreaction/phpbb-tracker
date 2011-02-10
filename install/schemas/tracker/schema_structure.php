<?php
/**
*
* @package install
* @version $Id$
* @copyright (c) 2010 http://www.phpbbarcade.com
* @copyright (c) 2008 http://www.jeffrusso.net
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/
if (!defined('IN_INSTALL') || !defined('IN_PHPBB'))
{
	// Someone has tried to access the file direct. This is not a good idea, so exit
	exit;
}

$schema_data = array();

	$schema_data['phpbb_tracker_project_categories'] = array(
		'COLUMNS'		=> array(
			'project_cat_id'		=> array('UINT', NULL, 'auto_increment'),
			'project_name'			=> array('VCHAR', ''),
			'project_name_clean'	=> array('VCHAR', ''),
		),
		'PRIMARY_KEY'	=> 'project_cat_id',
	);

	$schema_data['phpbb_tracker_project'] = array(
		'COLUMNS'		=> array(
			'project_id'			=> array('UINT', NULL, 'auto_increment'),
			'project_desc'			=> array('STEXT_UNI', ''),
			'project_cat_id'		=> array('UINT', 0),
			'project_group'			=> array('UINT', 0),
			'project_type'			=> array('TINT:2', 0),
			'project_enabled'		=> array('TINT:1', 0),
			'project_security'		=> array('TINT:1', 0),
			'ticket_security'		=> array('TINT:1', 0),
			'show_php'				=> array('TINT:1', 0),
			'show_dbms'				=> array('TINT:1', 0),
			'lang_php'				=> array('VCHAR', ''),
			'lang_dbms'				=> array('VCHAR', ''),
		),
		'PRIMARY_KEY'	=> 'project_id',
	);

	$schema_data['phpbb_tracker_config'] = array(
		'COLUMNS'		=> array(
			'config_name'		=> array('VCHAR', ''),
			'config_value'		=> array('VCHAR_UNI', ''),
		),
		'PRIMARY_KEY'	=> 'config_name',
	);

	$schema_data['phpbb_tracker_attachments'] = array(
		'COLUMNS'		=> array(
			'attach_id'				=> array('UINT', NULL, 'auto_increment'),
			'ticket_id'				=> array('UINT', 0),
			'post_id'				=> array('UINT', 0),
			'poster_id'				=> array('UINT', 0),
			'is_orphan'				=> array('BOOL', 1),
			'physical_filename'		=> array('VCHAR', ''),
			'real_filename'			=> array('VCHAR', ''),
			'download_count'		=> array('UINT', 0),
			'attach_comment'		=> array('TEXT_UNI', ''),
			'extension'				=> array('VCHAR:100', ''),
			'mimetype'				=> array('VCHAR:100', ''),
			'filesize'				=> array('UINT:20', 0),
			'filetime'				=> array('TIMESTAMP', 0),
			'thumbnail'				=> array('BOOL', 0),
		),
		'PRIMARY_KEY'	=> 'attach_id',
		'KEYS'			=> array(
			'filetime'			=> array('INDEX', 'filetime'),
			'ticket_id'			=> array('INDEX', 'ticket_id'),
			'post_id'			=> array('INDEX', 'post_id'),
			'poster_id'			=> array('INDEX', 'poster_id'),
			'is_orphan'			=> array('INDEX', 'is_orphan'),
		),
	);

  	$schema_data['phpbb_tracker_tickets'] = array(
		'COLUMNS'		=> array(
			'ticket_id'						=> array('UINT', NULL, 'auto_increment'),
			'project_id'					=> array('UINT', 0),
			'ticket_title'					=> array('STEXT_UNI', ''),
			'ticket_desc'					=> array('MTEXT_UNI', ''),
			'ticket_desc_bitfield'			=> array('VCHAR:255', ''),
			'ticket_desc_options'			=> array('UINT:11', 7),
			'ticket_desc_uid'				=> array('VCHAR:8', ''),
			'ticket_status'					=> array('TINT:1', 0),
			'ticket_hidden'					=> array('TINT:1', 0),
			'ticket_security'				=> array('TINT:1', 0),
			'ticket_assigned_to'			=> array('INT:8', 0),
			'ticket_attachment'				=> array('BOOL', 0),
			'status_id'						=> array('TINT:2', 0),
			'component_id'					=> array('UINT', 0),
			'version_id'					=> array('UINT', 0),
			'severity_id'					=> array('UINT', 0),
			'priority_id'					=> array('UINT', 0),
			'ticket_php'					=> array('STEXT_UNI', ''),
			'ticket_dbms'					=> array('STEXT_UNI', ''),
			'ticket_user_id'				=> array('UINT', 0),
			'ticket_username'				=> array('VCHAR:255', ''),
			'ticket_time'					=> array('INT:11', 0),
			'last_post_user_id'				=> array('UINT', 0),
			'last_post_time'				=> array('INT:11', 0),
			'last_visit_user_id'			=> array('UINT', 0),
			'last_visit_time'				=> array('TIMESTAMP', 0),
			'last_visit_username'			=> array('VCHAR_UNI', ''),
			'last_visit_user_colour'		=> array('VCHAR:6', ''),
			'edit_time'						=> array('INT:11', 0),
			'edit_reason'					=> array('VCHAR:255', ''),
			'edit_user'						=> array('UINT', 0),
			'edit_count'					=> array('USINT', 0),
		),
		'PRIMARY_KEY'	=> 'ticket_id',
	);

	  $schema_data['phpbb_tracker_posts'] = array(
		'COLUMNS'		=> array(
			'post_id'					=> array('UINT', NULL, 'auto_increment'),
			'ticket_id'					=> array('UINT', 0),
			'post_attachment'			=> array('BOOL', 0),
			'post_desc'					=> array('MTEXT_UNI', ''),
			'post_desc_bitfield'		=> array('VCHAR:255', ''),
			'post_desc_options'			=> array('UINT:11', 7),
			'post_desc_uid'				=> array('VCHAR:8', ''),
			'post_user_id'				=> array('UINT', 0),
			'post_username'				=> array('VCHAR:255', ''),
			'post_time'					=> array('INT:11', 0),
			'edit_time'					=> array('INT:11', 0),
			'edit_reason'				=> array('VCHAR:255', ''),
			'edit_user'					=> array('UINT', 0),
			'edit_count'				=> array('USINT', 0),
		),
		'PRIMARY_KEY'	=> array('post_id', 'ticket_id', 'post_username'),
	);

	$schema_data['phpbb_tracker_components'] = array(
		'COLUMNS'		=> array(
			'component_id'			=> array('UINT', NULL, 'auto_increment'),
			'project_id'			=> array('UINT', 0),
			'component_name'		=> array('VCHAR_UNI', ''),
		),
		'PRIMARY_KEY'	=> 'component_id',
	);

	$schema_data['phpbb_tracker_history'] = array(
		'COLUMNS'		=> array(
			'history_id'			=> array('UINT', NULL, 'auto_increment'),
			'ticket_id'				=> array('UINT', 0),
			'history_time'			=> array('INT:11', 0),
			'history_status'		=> array('UINT', 0),
			'history_user_id'		=> array('INT:8', 0),
			'history_assigned_to'	=> array('INT:8', 0),
			'history_old_status'	=> array('UINT', 0),
			'history_new_status'	=> array('UINT', 0),
			'history_old_priority'	=> array('UINT', 0),
			'history_new_priority'	=> array('UINT', 0),
			'history_old_severity'	=> array('UINT', 0),
			'history_new_severity'	=> array('UINT', 0),
		),
		'PRIMARY_KEY'	=> 'history_id',
	);

	$schema_data['phpbb_tracker_version'] = array(
		'COLUMNS'		=> array(
			'version_id'			=> array('UINT', NULL, 'auto_increment'),
			'project_id'			=> array('UINT', 0),
			'version_name'			=> array('VCHAR_UNI', ''),
			'version_enabled'		=> array('TINT:1', 1),
		),
		'PRIMARY_KEY'	=> 'version_id',
	);

	$schema_data['phpbb_tracker_project_watch'] = array(
		'COLUMNS'		=> array(
			'user_id'		=> array('UINT', 0),
			'project_id'	=> array('UINT', 0),
		),
		'PRIMARY_KEY'	=> array('user_id', 'project_id'),
	);

	$schema_data['phpbb_tracker_tickets_watch'] = array(
		'COLUMNS'		=> array(
			'user_id'		=> array('UINT', 0),
			'ticket_id'		=> array('UINT', 0),
		),
		'PRIMARY_KEY'	=> array('user_id', 'ticket_id'),
	);

?>