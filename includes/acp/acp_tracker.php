<?php
/**
*
* @package acp
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
* @package acp
*/
class acp_tracker
{
	var $u_action;

	function main($id, $mode)
	{
		global $config, $db, $user, $auth, $template, $cache;
		global $phpbb_root_path, $phpbb_admin_path, $phpEx, $table_prefix;

		include($phpbb_root_path . 'includes/tracker/tracker_class.' . $phpEx);

		$this->tpl_name = 'acp_tracker';
		$action	= request_var('action', '');
		$version_check = (isset($_POST['version_check'])) ? true : false;

		if ($version_check)
		{
			$mode = 'version_check';
		}

		switch ($mode)
		{
			case 'version_check':
				$this->manage_version_check();
			break;

			case 'settings':
				$this->manage_settings();
			break;

			case 'attachments':
				$this->manage_attachments($action);
			break;

			case 'project':
				$this->manage_project($action);
			break;

			case 'component':
				$this->manage_component($action);
			break;

			case 'version':
				$this->manage_version($action);
			break;

			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}
	}

	function manage_version_check()
	{
		global $db, $user, $auth, $template, $cache, $mode;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;

		$tracker = new tracker();

		$user->add_lang('install');
		$this->page_title = 'ACP_VERSION_CHECK';
		$current_version = str_replace(' ', '.', $tracker->config['version']);

		// Get current and latest version
		$errstr = '';
		$errno = 0;

		$info = get_remote_file('www.jeffrusso.net', '/updatecheck', 'phpBBTracker.txt', $errstr, $errno);

		if ($info === false)
		{
			trigger_error($errstr, E_USER_WARNING);
		}

		$info = explode("\n", $info);
		$latest_version = trim($info[0]);

		$announcement_url = trim($info[1]);
		$update_link = append_sid($phpbb_root_path . 'tracker_install/install.' . $phpEx);

		$up_to_date = (version_compare(str_replace('rc', 'RC', strtolower($current_version)), str_replace('rc', 'RC', strtolower($latest_version)), '<')) ? false : true;

		$template->assign_vars(array(
			'S_VERSION_CHECK'	=> true,
			'S_UP_TO_DATE'		=> $up_to_date,

			'LATEST_VERSION'	=> $latest_version,
			'CURRENT_VERSION'	=> $current_version,

			'UPDATE_INSTRUCTIONS'	=> sprintf($user->lang['TRACKER_UPDATE_INSTRUCTIONS'], $announcement_url, $update_link),
		));
	}

	function manage_settings()
	{
		global $db, $user, $auth, $template, $cache, $mode, $tracker;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;

		$tracker = new tracker();

		$form_key = 'acp_tracker';
		add_form_key($form_key);

		$submit	= (isset($_POST['submit'])) ? true : false;

		$template->assign_var('S_IN_MANAGE_SETTINGS', true);

		$display_vars = array(
			'title'			=> 'ACP_TRACKER_SETTINGS',
			'title_explain'	=> 'ACP_TRACKER_SETTINGS_EXPLAIN',
			'vars'	=> array(
				'legend1'				=> 'ACP_TRACKER_VERSION_INFO',
				'version'				=> array('lang' => 'TRACKER_VERSION', 	'validate' => 'string', 'type' => 'custom', 'method' => 'tracker_version', 'explain' => true),

				'legend2'				=> 'ACP_TRACKER_SETTINGS_GENERAL',
				'attachment_path'		=> array('lang' => 'TRACKER_ATTACHMENT_PATH',		'validate' => 'path', 	'type' => 'text:30:65', 	'explain' => true),
				'send_email'			=> array('lang' => 'TRACKER_SEND_EMAIL',			'validate' => 'bool', 	'type' => 'radio:yes_no', 	'explain' => true),
				'tickets_per_page'		=> array('lang' => 'TRACKER_TICKETS_PER_PAGE',		'validate' => 'int', 	'type' => 'text:3:4', 		'explain' => true),
				'posts_per_page'		=> array('lang' => 'TRACKER_POSTS_PER_PAGE',		'validate' => 'int', 	'type' => 'text:3:4', 		'explain' => true),
				'top_reporters'			=> array('lang' => 'TRACKER_TOP_REPORTERS',			'validate' => 'int', 	'type' => 'text:3:4', 		'explain' => true),
			)
		);

		$this->new_config = $tracker->config;
		$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc(request_var('config', array('' => ''), true)) : $this->new_config;
		$error = array();

		// We validate the complete config if whished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		if ($submit && !check_form_key($form_key))
		{
			$error[] = $user->lang['FORM_INVALID'];
		}

		// Do not write values if there is an error
		if (sizeof($error))
		{
			$submit = false;
		}

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			$config_value = $cfg_array[$config_name];
			$this->new_config[$config_name] = $config_value;

			if ($submit)
			{
				$tracker->set_config($config_name, $config_value);
			}
		}

		if ($submit)
		{
			add_log('admin', 'LOG_TRACKER_' . strtoupper($mode));
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{

			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
					'S_LEGEND'	=> true,
					'LEGEND'		=> $user->lang[$vars])
				);

				continue;
			}

			$type = explode(':', $vars['type']);

			$template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> $user->lang[$vars['lang']],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> ($vars['explain']) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '',
				'CONTENT'		=> build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars),
			));

			unset($display_vars['vars'][$config_key]);
		}

		if (isset($display_vars['lang']))
		{
			$user->add_lang($display_vars['lang']);
		}

		$this->page_title = $display_vars['title'];

		$template->assign_vars(array(
				'L_TITLE'			=> $user->lang[$display_vars['title']],
				'L_EXPLAIN'			=> $user->lang[$display_vars['title_explain']],

				'S_ERROR'			=> (sizeof($error)) ? true : false,
				'ERROR_MSG'			=> implode('<br />', $error),

				'U_ACTION'			=> $this->u_action,
		));
	}

	function manage_attachments($action)
	{
		global $db, $user, $auth, $template, $cache, $mode, $tracker;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;

		$tracker = new tracker();

		$form_key = 'acp_tracker';
		add_form_key($form_key);
		$this->page_title = 'ACP_TRACKER_ATTACHMENTS';

		$submit	= (isset($_POST['submit'])) ? true : false;
		$attach_ids	= (isset($_POST['attach_ids'])) ? request_var('attach_ids', array(0)) : array();
		$extra_files	= (isset($_POST['extra_files'])) ? request_var('extra_files', array('')) : array();
		$errors = array();

		$template->assign_vars(array(
			'S_IN_MANAGE_ATTACHMENTS_DEFAULT'	=> true,
			'S_IN_MANAGE_ATTACHMENTS'			=> true,
			'U_ACTION' 							=> "{$this->u_action}&amp;action=delete",
		));

		switch ($action)
		{
			case 'delete':
				if (empty($attach_ids) && empty($extra_files))
				{
					break;
				}

				if (confirm_box(true))
				{
					if (!empty($attach_ids))
					{
						//Remove attachments
						$tracker->delete_orphan($attach_ids, $errors);
					}

					if (!empty($extra_files))
					{
						//Delete files
						$tracker->delete_extra_files($extra_files, $errors);
					}

					if (!sizeof($errors))
					{
						trigger_error($user->lang['TRACKER_FILES_DELETED'] . adm_back_link($this->u_action));
					}
				}
				else
				{
					confirm_box(false, 'ACP_TRACKER_ATTACHMENTS_DELETE', build_hidden_fields(array(
						'action'		=> 'delete',
						'submit'		=> true,
						'attach_ids'	=> $attach_ids,
						'extra_files'	=> $extra_files,
					)));
				}
			break;

			default:
			break;
		}

		$orphaned = $tracker->get_orphaned();
		foreach ($orphaned as $item)
		{
			$filesize = $item['filesize'];
			$size_lang = ($filesize >= 1048576) ? $user->lang['MB'] : ( ($filesize >= 1024) ? $user->lang['KB'] : $user->lang['BYTES'] );
			$filesize = ($filesize >= 1048576) ? round((round($filesize / 1048576 * 100) / 100), 2) : (($filesize >= 1024) ? round((round($filesize / 1024 * 100) / 100), 2) : $filesize);

			$template->assign_block_vars('orphan', array(
				'ATTACH_ID'			=> $item['attach_id'],
				'POSTER_USERNAME'	=> get_username_string('full', $item['poster_id'], $item['username'], $item['user_colour']),
				'REAL_FILENAME'		=> $item['real_filename'],
				'U_DOWNLOAD_LINK'	=> append_sid("{$phpbb_root_path}tracker.$phpEx", "mode=download&amp;id={$item['attach_id']}"),
				'FILESIZE'			=> $filesize,
				'SIZE_LANG'			=> $size_lang,
				'FILETIME'			=> $user->format_date($item['filetime']),
			));
		}

		$extra_files = $tracker->get_extra_files();
		foreach ($extra_files as $key => $value)
		{
			$template->assign_block_vars('extra_files', array(
				'FILENAME'			=> $key,
				'FULL_FILENAME'		=> $value,
			));
		}

		if (sizeof($errors))
		{
			$template->assign_vars(array(
				'S_ERROR'		=> true,
				'ERROR_MSG'		=> implode('<br />', $errors),
			));
		}
		$this->set_template_title($mode);
	}

	function manage_project($action)
	{
		global $db, $user, $auth, $template, $cache, $mode;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;

		$tracker = new tracker();

		$form_key = 'acp_tracker';
		add_form_key($form_key);
		$this->page_title = 'ACP_TRACKER_PROJECT';

		$project_id = request_var('project_id', 0);
		$submit	= (isset($_POST['submit'])) ? true : false;

		$template->assign_vars(array(
			'S_IN_MANAGE_PROJECT'	=> true,
		));

		switch ($action)
		{
			case 'add':
				if ($submit)
				{
					if (!check_form_key($form_key))
					{
						trigger_error('FORM_INVALID', E_USER_WARNING);
					}

					$project_data = array(
						'project_name'			=> utf8_normalize_nfc(request_var('project_name', '', true)),
						'project_desc'			=> utf8_normalize_nfc(request_var('project_desc', '', true)),
						'project_group'			=> request_var('project_group', 0),
						'project_type'			=> request_var('project_type', 0),
						'project_enabled'		=> request_var('project_enabled', 0),
						'project_security'		=> request_var('project_security', 0),
					);

					$tracker->add_project($project_data);
					add_log('admin', 'LOG_TRACKER_PROJECT_ADD', $project_data['project_name']);
					trigger_error($user->lang['ACP_TRACKER_PROJECT_ADDED'] . adm_back_link($this->u_action));
				}
				else
				{
					$project_data = array();
				}
			break;

			case 'edit':
				if ($submit)
				{
					if (!check_form_key($form_key))
					{
						trigger_error('FORM_INVALID', E_USER_WARNING);
					}

					$project_data = array(
						'project_name'			=> utf8_normalize_nfc(request_var('project_name', '', true)),
						'project_desc'			=> utf8_normalize_nfc(request_var('project_desc', '', true)),
						'project_group'			=> request_var('project_group', 0),
						'project_type'			=> request_var('project_type', 0),
						'project_enabled'		=> request_var('project_enabled', 0),
						'project_security'		=> request_var('project_security', 0),
					);

					$tracker->update_project($project_data, $project_id);
					add_log('admin', 'LOG_TRACKER_PROJECT_EDIT', $project_data['project_name']);
					trigger_error($user->lang['ACP_TRACKER_PROJECT_EDITED'] . adm_back_link($this->u_action));
				}
				else
				{
					$sql = 'SELECT *
							FROM ' . TRACKER_PROJECT_TABLE . '
							WHERE project_id = ' . $project_id;
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					$project_data = $row;
				}
			break;

			case 'delete':
				if (confirm_box(true) && $submit)
				{
					$sql = 'SELECT *
							FROM ' . TRACKER_PROJECT_TABLE . '
							WHERE project_id = ' . $project_id;
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					if ($row)
					{
						$tracker->delete_project($project_id);
						add_log('admin', 'LOG_TRACKER_PROJECT_DELETE', $row['project_name']);
						trigger_error($user->lang['ACP_TRACKER_PROJECT_DELETED'] . adm_back_link($this->u_action));
					}
					else
					{
						trigger_error($user->lang['ACP_TRACKER_PROJECT_NO_ID'] . adm_back_link($this->u_action), E_USER_WARNING);
					}
				}
			break;

			default:
			break;
		}

		switch ($action)
		{
			case 'delete':
				confirm_box(false, 'ACP_TRACKER_PROJECT_DELETE', build_hidden_fields(array(
					'action'		=> 'delete',
					'submit'		=> true,
					'project_id'	=> $project_id,
				)));
			break;

			case 'edit':
				$template->assign_vars(array(
					'PROJECT_ID'				=> $project_data['project_id'],
				));
			case 'add':
				if ($action == 'add')
				{
					$project_data = array_merge($project_data, array(
						'project_name'		=> '',
						'project_desc'		=> '',
						'project_group'		=> 5,
						'project_type'		=> 0,
						'project_enabled'	=> false,
						'project_security'	=> false,
					));
				}

				$template->assign_vars(array(
					'S_SELECT_GROUP'		=> true,
					'S_GROUP_OPTIONS'		=> group_select_options($project_data['project_group'], false, (($user->data['user_type'] == USER_FOUNDER) ? false : 0)),

					'S_SELECT_TYPE'			=> true,
					'S_TYPE_OPTIONS'		=> $tracker->type_select_options($project_data['project_type']),
				));

				$template->assign_vars(array(
					'S_IN_MANAGE_PROJECT_ADD_EDIT'	=> true,
					'U_BACK'						=> $this->u_action,
					'U_ACTION'						=> $this->u_action . '&amp;action=' . $action,

					'PROJECT_NAME'					=> $project_data['project_name'],
					'PROJECT_DESC'					=> $project_data['project_desc'],
					'PROJECT_GROUP'					=> $project_data['project_group'],
					'PROJECT_TYPE'					=> $project_data['project_type'],
					'PROJECT_ENABLED'				=> $project_data['project_enabled'],
					'PROJECT_SECURITY'				=> $project_data['project_security'],
				));

				$this->set_template_title($mode);

				return;
			break;

			case 'enable':
			case 'disable':
				$tracker->set_project_enabled($project_id, $action);
				redirect($this->u_action);
			break;

			default:
			break;
		}

		$template->assign_vars(array(
			'S_IN_MANAGE_PROJECT_DEFAULT'	=> true,
			'U_ACTION' 						=> "{$this->u_action}&amp;action=add",
		));

		$sql = 'SELECT *
			FROM ' . TRACKER_PROJECT_TABLE . '
				ORDER BY project_type ASC, lower(project_name) ASC';
		$result = $db->sql_query($sql);

		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		foreach ($row as $item)
		{
			$template->assign_block_vars('project', array(
				'PROJECT_NAME'		=> $item['project_name'],
				'PROJECT_DESC'		=> $item['project_desc'],
				'PROJECT_TYPE'		=> $tracker->set_lang_name($tracker->types[$item['project_type']]['title']),
				'PROJECT_ENABLED'	=> $item['project_enabled'],
				'U_EDIT' 			=> "{$this->u_action}&amp;action=edit&amp;project_id={$item['project_id']}",
				'U_DELETE' 			=> "{$this->u_action}&amp;action=delete&amp;project_id={$item['project_id']}",
				'U_ENABLE' 			=> "{$this->u_action}&amp;action=enable&amp;project_id={$item['project_id']}",
				'U_DISABLE' 		=> "{$this->u_action}&amp;action=disable&amp;project_id={$item['project_id']}",
			));
		}

		$this->set_template_title($mode);
	}

	function manage_component($action)
	{
		global $db, $user, $auth, $template, $cache, $mode;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;

		$tracker = new tracker();

		$form_key = 'acp_tracker';
		add_form_key($form_key);
		$this->page_title = 'ACP_TRACKER_COMPONENT';

		$project_id = request_var('project_id', 0);
		$component_id = request_var('component_id', 0);
		$submit	= (isset($_POST['submit'])) ? true : false;

		$projects = $tracker->get_projects();

		$template->assign_var('S_IN_MANAGE_COMPONENT', true);

		switch ($action)
		{
			case 'add':
				if ($submit)
				{
					if (!check_form_key($form_key))
					{
						trigger_error('FORM_INVALID', E_USER_WARNING);
					}

					$component_data = array(
						'component_name'		=> utf8_normalize_nfc(request_var('component_name', '', true)),
						'project_id'			=> $project_id,
					);

					$tracker->handle_project_items('add', $mode, $component_data);
					add_log('admin', 'LOG_TRACKER_COMPONENT_ADD', $component_data['component_name']);
					trigger_error($user->lang['ACP_TRACKER_COMPONENT_ADDED'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"));
				}
				else
				{
					$component_data = array();
				}
			break;

			case 'edit':
				if ($submit)
				{
					if (!check_form_key($form_key))
					{
						trigger_error('FORM_INVALID', E_USER_WARNING);
					}

					$component_data = array(
						'component_name'		=> utf8_normalize_nfc(request_var('component_name', '', true)),
						'project_id'			=> $project_id,
					);


					$tracker->handle_project_items('update', $mode, $component_data, $component_id);
					add_log('admin', 'LOG_TRACKER_COMPONENT_EDIT', $component_data['component_name']);
					trigger_error($user->lang['ACP_TRACKER_COMPONENT_EDITED'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"));
				}
				else
				{
					$sql = 'SELECT *
							FROM ' . TRACKER_COMPONENTS_TABLE . '
							WHERE component_id = ' . $component_id;
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					$component_data = $row;
				}
			break;

			case 'delete':
				if (confirm_box(true) && $submit)
				{
					$sql = 'SELECT *
							FROM ' . TRACKER_COMPONENTS_TABLE . '
							WHERE component_id = ' . $component_id;
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					if ($row)
					{
						$tracker->handle_project_items('delete', $mode, false, $component_id);
						add_log('admin', 'LOG_TRACKER_COMPONENT_DELETE', $row['component_name']);
						trigger_error($user->lang['ACP_TRACKER_COMPONENT_DELETED'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"));
					}
					else
					{
						trigger_error($user->lang['ACP_TRACKER_COMPONENT_NO_ID'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"), E_USER_WARNING);
					}
				}
			break;

			default:
			break;
		}

		switch ($action)
		{
			case 'delete':
				$s_hidden_fields = array(
					'action'		=> 'delete',
					'submit'		=> true,
					'component_id'	=> $component_id,
				);
				confirm_box(false, 'ACP_TRACKER_COMPONENT_DELETE', build_hidden_fields($s_hidden_fields));
			break;

			case 'edit':
				$template->assign_var('COMPONENT_ID', $component_data['component_id']);
			case 'add':
				if ($action == 'add')
				{
					$component_data['component_name'] = '';
				}

				$template->assign_vars(array(
					'S_IN_MANAGE_COMPONENT_ADD_EDIT'	=> true,
					'U_BACK'							=> $this->u_action,
					'U_ACTION'							=> $this->u_action . '&amp;action=' . $action,

					'COMPONENT_NAME'					=> $component_data['component_name'],
					'PROJECT_ID'						=> $project_id,
				));

				$this->set_template_title($mode);

				return;
			break;

			case 'view':
				$template->assign_vars(array(
					'S_IN_MANAGE_COMPONENT_VIEW'	=> true,
					'PROJECT_ID'					=> $project_id,
					'U_ACTION' 						=> "{$this->u_action}&amp;action=add",
				));

				$sql = 'SELECT *
					FROM ' . TRACKER_COMPONENTS_TABLE . '
						WHERE project_id = ' . $project_id . '
						ORDER BY component_name';
				$result = $db->sql_query($sql);

				$row = $db->sql_fetchrowset($result);
				$db->sql_freeresult($result);

				foreach ($row as $item)
				{
						$template->assign_block_vars('component', array(
							'COMPONENT_NAME'	=> $tracker->set_lang_name($item['component_name']),
							'U_EDIT' 			=> "{$this->u_action}&amp;action=edit&amp;component_id={$item['component_id']}&amp;project_id={$item['project_id']}",
							'U_DELETE' 			=> "{$this->u_action}&amp;action=delete&amp;component_id={$item['component_id']}&amp;project_id={$item['project_id']}",
						));
				}

				$this->set_template_title($mode);
				return;
			break;

			default:
			break;
		}

		$template->assign_vars(array(
			'S_IN_MANAGE_COMPONENT_DEFAULT'	=> true,
			'S_PROJECT_OPTIONS'				=> $tracker->project_select_options($projects, false, $mode),
			'U_ACTION' 						=> "{$this->u_action}&amp;action=view",
		));

		$this->set_template_title($mode);
	}

	function manage_version($action)
	{
		global $db, $user, $auth, $template, $cache, $mode;
		global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;

		$tracker = new tracker();

		$form_key = 'acp_tracker';
		add_form_key($form_key);
		$this->page_title = 'ACP_TRACKER_VERSION';

		$project_id = request_var('project_id', 0);
		$version_id = request_var('version_id', 0);
		$submit	= (isset($_POST['submit'])) ? true : false;

		$projects = $tracker->get_projects();

		$template->assign_var('S_IN_MANAGE_VERSION', true);

		switch ($action)
		{
			case 'add':
				if ($submit)
				{
					if (!check_form_key($form_key))
					{
						trigger_error('FORM_INVALID', E_USER_WARNING);
					}

					$version_data = array(
						'version_name'			=> utf8_normalize_nfc(request_var('version_name', '', true)),
						'project_id'			=> $project_id,
					);

					$tracker->handle_project_items('add', $mode, $version_data);
					add_log('admin', 'LOG_TRACKER_VERSION_ADD', $version_data['version_name']);
					trigger_error($user->lang['ACP_TRACKER_VERSION_ADDED'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"));
				}
				else
				{
					$version_data = array();
				}
			break;

			case 'edit':
				if ($submit)
				{
					if (!check_form_key($form_key))
					{
						trigger_error('FORM_INVALID', E_USER_WARNING);
					}

					$version_data = array(
						'version_name'			=> utf8_normalize_nfc(request_var('version_name', '', true)),
						'project_id'			=> $project_id,
					);


					$tracker->handle_project_items('update', $mode, $version_data, $version_id);
					add_log('admin', 'LOG_TRACKER_VERSION_EDIT', $version_data['version_name']);
					trigger_error($user->lang['ACP_TRACKER_VERSION_EDITED'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"));
				}
				else
				{
					$sql = 'SELECT *
							FROM ' . TRACKER_VERSION_TABLE . '
							WHERE version_id = ' . $version_id;
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					$version_data = $row;
				}
			break;

			case 'delete':
				if (confirm_box(true) && $submit)
				{
					$sql = 'SELECT *
							FROM ' . TRACKER_VERSION_TABLE . '
							WHERE version_id = ' . $version_id;
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					if ($row)
					{
						$tracker->handle_project_items('delete', $mode, false, $version_id);
						add_log('admin', 'LOG_TRACKER_VERSION_DELETE', $row['version_name']);
						trigger_error($user->lang['ACP_TRACKER_VERSION_DELETED'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"));
					}
					else
					{
						trigger_error($user->lang['ACP_TRACKER_VERSION_NO_ID'] . adm_back_link($this->u_action . "&amp;action=view&amp;project_id=$project_id"), E_USER_WARNING);
					}
				}
			break;

			default:
			break;
		}

		switch ($action)
		{
			case 'delete':
				$s_hidden_fields = array(
					'action'		=> 'delete',
					'submit'		=> true,
					'version_id'	=> $version_id,
				);
				confirm_box(false, 'ACP_TRACKER_VERSION_DELETE', build_hidden_fields($s_hidden_fields));
			break;

			case 'edit':
				$template->assign_var('VERSION_ID', $version_data['version_id']);
			case 'add':
				if ($action == 'add')
				{
					$version_data['version_name'] = '';
				}

				$template->assign_vars(array(
					'S_IN_MANAGE_VERSION_ADD_EDIT'		=> true,
					'U_BACK'							=> $this->u_action,
					'U_ACTION'							=> $this->u_action . '&amp;action=' . $action,

					'VERSION_NAME'						=> $version_data['version_name'],
					'PROJECT_ID'						=> $project_id,
				));

				$this->set_template_title($mode);

				return;
			break;

			case 'view':
				$template->assign_vars(array(
					'S_IN_MANAGE_VERSION_VIEW'	=> true,
					'PROJECT_ID'				=> $project_id,
					'U_ACTION' 					=> "{$this->u_action}&amp;action=add",
				));

				$sql = 'SELECT *
					FROM ' . TRACKER_VERSION_TABLE . '
						WHERE project_id = ' . $project_id . '
						ORDER BY version_name';
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrowset($result);
				$db->sql_freeresult($result);

				foreach ($row as $item)
				{
						$template->assign_block_vars('version', array(
							'VERSION_NAME'		=> $tracker->set_lang_name($item['version_name']),
							'U_EDIT' 			=> "{$this->u_action}&amp;action=edit&amp;version_id={$item['version_id']}&amp;project_id={$item['project_id']}",
							'U_DELETE' 			=> "{$this->u_action}&amp;action=delete&amp;version_id={$item['version_id']}&amp;project_id={$item['project_id']}",

						));
				}

				$this->set_template_title($mode);
				return;
			break;

			default:
			break;
		}


		$template->assign_vars(array(
			'S_IN_MANAGE_VERSION_DEFAULT'	=> true,
			'S_PROJECT_OPTIONS'				=> $tracker->project_select_options($projects, false, $mode),
			'U_ACTION' 						=> "{$this->u_action}&amp;action=view",
		));

		$this->set_template_title($mode);
	}

	function set_template_title($mode)
	{
		global $user, $template;

		$l_title = $user->lang['ACP_TRACKER_' . strtoupper($mode)];
		$l_title_explain = $user->lang['ACP_TRACKER_' . strtoupper($mode) . '_EXPLAIN'];

		$template->assign_vars(array(
			'L_TITLE'					=> $l_title,
			'L_EXPLAIN'					=> $l_title_explain,
		));
	}

	function tracker_version()
	{
		global $tracker, $user;

		return $tracker->config['version'] . '&nbsp;&nbsp;&nbsp;<input class="button1" type="submit" id="submit" name="version_check" value="' . $user->lang['TRACKER_CHECK_UPDATES'] . '" />';
	}

}
?>