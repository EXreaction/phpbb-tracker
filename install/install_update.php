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
	if (!$this->installed_version || $this->installed_version == $mod_config['mod_version'])
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
			'LEGEND_EXPLAIN'	=> sprintf($user->lang['PHP_SETTINGS_EXPLAIN'], $mod_config['phpbb_version']),
		));

		if (version_compare($config['version'], $mod_config['phpbb_version']) < 0)
		{
			$result = '<strong style="color:red">' . $user->lang['NO'] . '</strong>';
		}
		else
		{
			$passed['phpbb'] = true;
			$result = '<strong style="color:green">' . $user->lang['YES'] . '</strong>';
		}

		$template->assign_block_vars('checks', array(
			'TITLE'			=> sprintf($user->lang['PHPBB_VERSION_REQD'], $mod_config['phpbb_version']),
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

		$directories = array('files', 'files/tracker');

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
		
		if (version_compare($this->p_master->installed_version, $mod_config['mod_version'], '<'))
		{
			switch ($this->p_master->installed_version)
			{
				case '0.1.0':
					$phpbb_db_tools->perform_schema_changes($CFG['update_schema_changes']['0.1.1']);

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
					$phpbb_db_tools->perform_schema_changes($CFG['update_schema_changes']['0.1.1']);
					$phpbb_db_tools->perform_schema_changes($CFG['update_schema_changes']['0.1.2']);
				
				case '0.1.2':
					$this->p_master->add_permissions($CFG['update_permission_options']['0.2.0']);
					$this->p_master->load_tables('0.2.0');
					$this->p_master->set_config('project_view', false);
					
				break;

				default:
				break;
			}
			
			// Set tracker version config value to latest version
			$this->p_master->set_config('version', $mod_config['mod_version']);
			// Purge the cache
			$cache->purge();
		}

		$template->assign_vars(array(
			'TITLE'		=> $user->lang['INSTALL_CONGRATS'],
			'BODY'		=> $user->lang['STAGE_UPDATE_TRACKER_EXPLAIN'] . '<br /><br />' . sprintf($user->lang['UPDATE_CONGRATS_EXPLAIN'], $mod_config['mod_version']),
			'L_SUBMIT'	=> $user->lang['INSTALL_LOGIN'],
			'U_ACTION'	=> append_sid("{$phpbb_root_path}adm/index.$phpEx", false, true, $user->session_id),
		));
	}

}

?>