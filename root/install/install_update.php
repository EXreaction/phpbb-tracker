<?php
/**
*
* @package install
* @version $Id$
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/
if (!defined('IN_INSTALL'))
{
	// Someone has tried to access the file direct. This is not a good idea, so exit
	exit;
}

if (!empty($setmodules))
{
	if (!$this->installed_version || $this->installed_version == $mod_config['version']['current'])
	{
		return;
	}

	$module[] = array(
		'module_type'		=> 'install',
		'module_title'		=> 'UPDATE',
		'module_filename'	=> substr(basename(__FILE__), 0, -strlen($phpEx)-1),
		'module_order'		=> 20,
		'module_subs'		=> '',
		'module_stages'		=> array('INTRO', 'REQUIREMENTS', 'UPDATE'),
		'module_reqs'		=> ''
	);
}

/**
* Installation
* @package install
*/
class install_update extends module
{
	function install_update(&$p_master)
	{
		$this->p_master = &$p_master;
	}

	function main($mode, $sub)
	{
		global $user, $template, $phpbb_root_path;

		switch ($sub)
		{
			case 'intro':
				$this->page_title = $user->lang['SUB_INTRO'];

				$template->assign_vars(array(
					'TITLE'			=> $user->lang['UPDATE_INTRO'],
					'BODY'			=> $user->lang['UPDATE_INTRO_BODY'],
					'L_SUBMIT'		=> $user->lang['NEXT_STEP'],
					'U_ACTION'		=> $this->p_master->module_url . "?mode=$mode&amp;sub=requirements",
				));

			break;

			case 'requirements':
				$this->requirements($mode, $sub);

			break;

			case 'update':
				$this->update($mode, $sub);

			break;
		}

		$this->tpl_name = 'install_install';
	}

	/**
	* Checks that the server we are installing on meets the requirements for running phpBB
	*/
	function requirements($mode, $sub)
	{
		global $user, $config, $mod_config, $template, $phpbb_root_path, $phpEx;

		$this->page_title = $user->lang['STAGE_REQUIREMENTS'];

		$template->assign_vars(array(
			'TITLE'		=> $user->lang['REQUIREMENTS_TITLE'],
			'BODY'		=> $user->lang['REQUIREMENTS_EXPLAIN'],
		));

		$passed = array('phpbb' => false, 'files' => false,);

		// Test for basic PHP settings
		$template->assign_block_vars('checks', array(
			'S_LEGEND'			=> true,
			'LEGEND'			=> $user->lang['PHP_SETTINGS'],
			'LEGEND_EXPLAIN'	=> sprintf($user->lang['PHP_SETTINGS_EXPLAIN'], $mod_config['version']['phpbb']),
		));

		if (phpbb_version_compare($config['version'], $mod_config['version']['phpbb'], '<'))
		{
			$result = '<strong style="color:red">' . $user->lang['NO'] . '</strong>';
		}
		else
		{
			$passed['phpbb'] = true;
			$result = '<strong style="color:green">' . $user->lang['YES'] . '</strong>';
		}

		$template->assign_block_vars('checks', array(
			'TITLE'			=> sprintf($user->lang['PHPBB_VERSION_REQD'], $mod_config['version']['phpbb']),
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> false,
			'S_LEGEND'		=> false,
		));

		// Check permissions on files/directories we need access to
		$template->assign_block_vars('checks', array(
			'S_LEGEND'			=> true,
			'LEGEND'			=> $user->lang['FILES_REQUIRED'],
			'LEGEND_EXPLAIN'	=> $user->lang['FILES_REQUIRED_EXPLAIN'],
		));

		$directories = array('files/', 'files/tracker/');

		umask(0);

		$passed['files'] = true;
		foreach ($directories as $dir)
		{
			$exists = $write = false;

			// Try to create the directory if it does not exist
			if (!file_exists($phpbb_root_path . $dir))
			{
				@mkdir($phpbb_root_path . $dir, 0777);
				@chmod($phpbb_root_path . $dir, 0777);
			}

			// Now really check
			if (file_exists($phpbb_root_path . $dir) && is_dir($phpbb_root_path . $dir))
			{
				if (!@is_writable($phpbb_root_path . $dir))
				{
					@chmod($phpbb_root_path . $dir, 0777);
				}
				$exists = true;
			}

			// Now check if it is writable by storing a simple file
			$fp = @fopen($phpbb_root_path . $dir . 'test_lock', 'wb');
			if ($fp !== false)
			{
				$write = true;
			}
			@fclose($fp);

			@unlink($phpbb_root_path . $dir . 'test_lock');

			$passed['files'] = ($exists && $write && $passed['files']) ? true : false;

			$exists = ($exists) ? '<strong style="color:green">' . $user->lang['FOUND'] . '</strong>' : '<strong style="color:red">' . $user->lang['NOT_FOUND'] . '</strong>';
			$write = ($write) ? ', <strong style="color:green">' . $user->lang['WRITABLE'] . '</strong>' : (($exists) ? ', <strong style="color:red">' . $user->lang['UNWRITABLE'] . '</strong>' : '');

			$template->assign_block_vars('checks', array(
				'TITLE'		=> $dir,
				'RESULT'	=> $exists . $write,

				'S_EXPLAIN'	=> false,
				'S_LEGEND'	=> false,
			));
		}

		$s_hidden_fields = '';
		$url = (!in_array(false, $passed)) ? $this->p_master->module_url . "?mode=$mode&amp;sub=update" : $this->p_master->module_url . "?mode=$mode&amp;sub=requirements";
		$submit = (!in_array(false, $passed)) ? $user->lang['UPDATE_START'] : $user->lang['INSTALL_TEST'];

		$template->assign_vars(array(
			'L_SUBMIT'	=> $submit,
			'S_HIDDEN'	=> $s_hidden_fields,
			'U_ACTION'	=> $url,
		));
	}

	/**
	* Obtain the information required to connect to the database
	*/
	function update($mode, $sub)
	{
		global $user, $template, $cache, $phpEx, $phpbb_root_path, $file_functions, $phpbb_db_tools, $db, $mod_config;

		$this->page_title = $user->lang['STAGE_UPDATE_TRACKER'];

		// Purge the cache
		$cache->purge();

		if (phpbb_version_compare($this->p_master->installed_version, $mod_config['version']['current'], '<'))
		{
			switch ($this->p_master->installed_version)
			{
				case '0.1.0':
					$phpbb_db_tools->perform_schema_changes($mod_config['schema_changes']['update']['0.1.1']);

					$sql = 'SELECT project_name, project_id
						FROM ' . TRACKER_PROJECT_TABLE;
					$result = $db->sql_query($sql);

					$row = $db->sql_fetchrowset($result);
					$db->sql_freeresult($result);

					foreach ($row as $item)
					{
						$sql = 'UPDATE ' . TRACKER_PROJECT_TABLE . "
							SET project_name_clean = '" . $db->sql_escape(utf8_clean_string($item['project_name'])) . "'
						WHERE project_id = " . (int) $item['project_id'];
						$db->sql_query($sql);
					}

					if ($tracker->config['attachment_path'] == 'includes/tracker/files')
					{
						file_functions::copy_dir('./../includes/tracker/files', './../files/tracker', true, false);
						file_functions::delete_dir('./../includes/tracker/files');
						$this->p_master->set_config('attachment_path', 'files/tracker');
					}

				case '0.1.1':
					// This is need because of a bug when installing 0.1.1 new
					$phpbb_db_tools->perform_schema_changes($mod_config['schema_changes']['update']['0.1.1']);
					$phpbb_db_tools->perform_schema_changes($mod_config['schema_changes']['update']['0.1.2']);

				case '0.1.2':
				case '0.1.3':
					$phpbb_db_tools->perform_schema_changes($mod_config['schema_changes']['update']['0.2.0']);
					$this->p_master->add_permissions($mod_config['permission_options']['update']['0.2.0']['phpbb']);

					$schema_data = array();
					$schema_data[TRACKER_PROJECT_CATS_TABLE] = array(
						'COLUMNS'		=> array(
							'project_cat_id'		=> array('UINT', NULL, 'auto_increment'),
							'project_name'			=> array('VCHAR', ''),
							'project_name_clean'	=> array('VCHAR', ''),
						),
						'PRIMARY_KEY'	=> 'project_cat_id',
					);

					$schema_data[TRACKER_PROJECT_WATCH_TABLE] = array(
						'COLUMNS'		=> array(
							'user_id'		=> array('UINT', 0),
							'project_id'	=> array('UINT', 0),
						),
						'PRIMARY_KEY'	=> array('user_id', 'project_id'),
					);

					$schema_data['TRACKER_TICKETS_WATCH_TABLE'] = array(
						'COLUMNS'		=> array(
							'user_id'		=> array('UINT', 0),
							'ticket_id'		=> array('UINT', 0),
						),
						'PRIMARY_KEY'	=> array('user_id', 'ticket_id'),
					);

					foreach ($schema_data as $table_name => $table_data)
					{
						// Now create the table
						$phpbb_db_tools->sql_create_table($table_name, $table_data);
					}

					$this->p_master->set_config('default_status_type', TRACKER_ALL_OPENED);

					// First lets pull all the project name data
					$sql = 'SELECT project_id, project_name, project_name_clean
						FROM ' . TRACKER_PROJECT_TABLE;
					$result = $db->sql_query($sql);

					$row = $db->sql_fetchrowset($result);
					$db->sql_freeresult($result);

					// Now we will insert every project into its own category
					// and update project table with the new project_cat _id.
					// This will be the safest way to do it. Every old project
					// will get its own category and the admin can moves things
					// around as they see fit after.
					foreach ($row as $item)
					{
						$sql_array = array(
							'project_name'			=> $item['project_name'],
							'project_name_clean'	=> $item['project_name_clean'],
						);

						$db->sql_query('INSERT INTO ' . TRACKER_PROJECT_CATS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_array));

						// Get project id
						$project_cat_id = $db->sql_nextid();

						$sql = 'UPDATE ' . TRACKER_PROJECT_TABLE . '
							SET project_cat_id = ' . (int) $project_cat_id . '
							WHERE project_id = ' . (int) $item['project_id'];
						$db->sql_query($sql);
					}

					// Remove the unnecessary columns from the tracker_project table.
					$schema_changes = array(
						'drop_columns'		=> array(
							TRACKER_PROJECT_TABLE	=> array('project_name', 'project_name_clean'),
						),
					);
					$phpbb_db_tools->perform_schema_changes($schema_changes);

				case '0.2.0':
				case '0.3.0':
					$this->p_master->set_config('enable_post_confirm', true);

				case '0.3.1':
				case '0.4.0':
				case '0.5.0':
					$this->p_master->set_config('allow_attachments', '1');
					$this->p_master->set_config('max_attachments', '5');

					// Add columns
					$schema_changes = array(
						'add_columns'		=> array(
							TRACKER_ATTACHMENTS_TABLE	=> array(
								'download_count' => array('UINT', 0),
								'attach_comment' => array('TEXT_UNI', ''),
								'thumbnail' => array('BOOL', 0),
							),

							TRACKER_TICKETS_TABLE	=> array(
								'ticket_username' => array('VCHAR_UNI', ''),
								'last_post_username' => array('VCHAR_UNI', ''),
								'ticket_attachment'	=> array('BOOL', 0),
							),

							TRACKER_POSTS_TABLE	=> array(
								'post_attachment' => array('BOOL', 0),
								'post_username' => array('VCHAR_UNI', ''),
							),
						),
					);
					$phpbb_db_tools->perform_schema_changes($schema_changes);

				break;

				default:
				break;
			}

			// Set tracker version config value to latest version
			$this->p_master->set_config('version', $mod_config['version']['current']);
			// Purge the cache
			$this->p_master->cache_purge(array('template', 'theme', 'imageset', 'auth', ''));
		}

		$template->assign_vars(array(
			'TITLE'		=> $user->lang['INSTALL_CONGRATS'],
			'BODY'		=> $user->lang['STAGE_UPDATE_TRACKER_EXPLAIN'] . '<br /><br />' . sprintf($user->lang['UPDATE_CONGRATS_EXPLAIN'], $mod_config['version']['current']),
			'L_SUBMIT'	=> $user->lang['INSTALL_LOGIN'],
			'U_ACTION'	=> append_sid("{$phpbb_root_path}adm/index.$phpEx", false, true, $user->session_id),
		));
	}

}

?>