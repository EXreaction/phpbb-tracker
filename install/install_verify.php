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
	$module[] = array(
		'module_type'		=> 'install',
		'module_title'		=> 'VERIFY',
		'module_filename'	=> substr(basename(__FILE__), 0, -strlen($phpEx)-1),
		'module_order'		=> 40,
		'module_subs'		=> '',
		'module_stages'		=> array('INTRO', 'VERIFY'),
		'module_reqs'		=> ''
	);
}

/**
* Installation
* @package install
*/
class install_verify extends module
{
	function install_verify(&$p_master)
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
					'TITLE'			=> $user->lang['VERIFY_INTRO'],
					'BODY'			=> $user->lang['VERIFY_INTRO_BODY'],
					'L_SUBMIT'		=> $user->lang['NEXT_STEP'],
					'U_ACTION'		=> $this->p_master->module_url . "?mode=$mode&amp;sub=verify",
				));

			break;

			case 'verify':
				$this->verify($mode, $sub);

			break;
		}

		$this->tpl_name = 'install_install';
	}

	/**
	* Checks that the server we are installing on meets the requirements for running phpBB
	*/
	function verify($mode, $sub)
	{
		global $user, $config, $mod_config, $template, $phpbb_root_path, $phpEx, $db;

		$this->page_title = $user->lang['STAGE_REQUIREMENTS'];

		$template->assign_vars(array(
			'TITLE'		=> $user->lang['REQUIREMENTS_TITLE'],
			'BODY'		=> $user->lang['REQUIREMENTS_EXPLAIN'],
		));
	
		// We will only check for files from the styles that are installed
		$sql = 'SELECT template_id, template_name FROM ' . STYLES_TEMPLATE_TABLE;
		$result = $db->sql_query($sql);

		$installed_templates = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$installed_templates[$row['template_id']] = $row['template_name'];
		}
		$db->sql_freeresult($result);

		$passed = array('phpbb' => false, 'files' => false, 'mod' => true,);

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

		// Check for url_fopen
		if (@ini_get('allow_url_fopen') == '1' || strtolower(@ini_get('allow_url_fopen')) == 'on')
		{
			$result = '<strong style="color:green">' . $user->lang['YES'] . '</strong>';
		}
		else
		{
			$result = '<strong style="color:red">' . $user->lang['NO'] . '</strong>';
		}

		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['PHP_URL_FOPEN_SUPPORT'],
			'TITLE_EXPLAIN'	=> $user->lang['PHP_URL_FOPEN_SUPPORT_EXPLAIN'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> true,
			'S_LEGEND'		=> false,
		));


		// Check for curl
		if (function_exists('curl_init') && !@ini_get('safe_mode') && !@ini_get('open_basedir'))
		{
			$result = '<strong style="color:green">' . $user->lang['YES'] . '</strong>';
		}
		else
		{
			$result = '<strong style="color:red">' . $user->lang['NO'] . '</strong>';
		}

		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['PHP_CURL_SUPPORT'],
			'TITLE_EXPLAIN'	=> $user->lang['PHP_CURL_SUPPORT_EXPLAIN'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> true,
			'S_LEGEND'		=> false,
		));

		// Check permissions on files/directories we need access to
		$template->assign_block_vars('checks', array(
			'S_LEGEND'			=> true,
			'LEGEND'			=> $user->lang['FILES_REQUIRED'],
			'LEGEND_EXPLAIN'	=> $user->lang['FILES_REQUIRED_EXPLAIN'],
		));

		$directories = array('arcade', 'arcade/gamedata', 'arcade/games', 'arcade/install');

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
		

		$template->assign_block_vars('checks', array(
			'S_LEGEND'			=> true,
			'LEGEND'			=> $user->lang['VERIFY_ARCADE_INSTALLATION'],
			'LEGEND_EXPLAIN'	=> $user->lang['VERIFY_ARCADE_INSTALLATION_EXPLAIN'],
		));
		
		// Check files exist
		$error = array();
		foreach ($mod_config['install_check']['files']['core'] as $file)
		{
			if (!file_exists($phpbb_root_path . $file))
			{
				$error[] = 'phpbb_root_path/' . $file;
			}
		}		

		foreach($installed_templates as $name)
		{
			if (isset($mod_config['install_check']['files']['styles'][$name]))
			{
				foreach ($mod_config['install_check']['files']['styles'][$name] as $file)
				{
					if (!file_exists($phpbb_root_path . $file))
					{
						$error[] = 'phpbb_root_path/' . $file;
					}
				}
			}
		}

		if (sizeof($error))
		{
			$passed['mod'] = false;
			$result = '<strong style="color:red">' . sprintf($user->lang['VERIFY_MISSING_FILES'],  implode('<br />', $error)) . '</strong>';
		}
		else
		{
			$result = '<strong style="color:green">' . $user->lang['VERIFY_ALL_FILES'] . '</strong>';
		}
		unset($error);
		
		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['VERIFY_FILES_EXIST'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> false,
			'S_LEGEND'		=> false,
		));
		
		// Check if tables exist
		$error = array();
		$tables = get_tables($db);
		foreach ($mod_config['install_check']['tables'] as $table_name)
		{
			if (!in_array($table_name, $tables))
			{
				$error[] = $table_name;
			}
		}
		unset($tables);

		if (sizeof($error))
		{
			$passed['mod'] = false;
			$result = '<strong style="color:red">' . sprintf($user->lang['VERIFY_MISSING_TABLES'],  implode('<br />', $error)) . '</strong>';
		}
		else
		{
			$result = '<strong style="color:green">' . $user->lang['VERIFY_ALL_TABLES'] . '</strong>';
		}
		unset($error);
		
		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['VERIFY_TABLES_EXIST'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> false,
			'S_LEGEND'		=> false,
		));
		
		// Check files edits exist
		$error = array();
		foreach ($mod_config['install_check']['edits']['core'] as $key => $value)
		{
			if ($content = @file_get_contents($phpbb_root_path . $key))
			{
				foreach ($value as $edit)
				{
					if (strpos($content, $edit) === false)
					{
						$error[] = 'phpbb_root_path/' . $key . ' - <span style="color: black;">' . htmlspecialchars($edit) . '</span>';
						break;
					}
				}
			}
			else
			{
				$error[] = 'phpbb_root_path/' . $key . ' - <span style="color: black;">' . $user->lang['NOT_FOUND'] . '</span>';
			}
		}
		
		foreach ($installed_templates as $name)
		{
			if (isset($mod_config['install_check']['edits']['styles'][$name]))
			{
				foreach ($mod_config['install_check']['edits']['styles'][$name] as $key => $value)
				{
					if ($content = @file_get_contents($phpbb_root_path . $key))
					{
						foreach ($value as $edit)
						{
							if (strpos($content, $edit) === false)
							{
								$error[] = 'phpbb_root_path/' . $key . ' - <span style="color: black;">' . htmlspecialchars($edit) . '</span>';
								break;
							}
						}
					}
					else
					{
						$error[] = 'phpbb_root_path/' . $key . ' - <span style="color: black;">' . $user->lang['NOT_FOUND'] . '</span>';
					}
				}
			}
		}

		if (sizeof($error))
		{
			$passed['mod'] = false;
			$result = '<strong style="color:red">' . sprintf($user->lang['VERIFY_MISSING_FILES_EDITED'],  implode('<br />', $error)) . '</strong>';
		}
		else
		{
			$result = '<strong style="color:green">' . $user->lang['VERIFY_ALL_FILES_EDITED'] . '</strong>';
		}
		unset($error);
		
		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['VERIFY_FILES_EDITED'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> false,
			'S_LEGEND'		=> false,
		));
		
		// Check other db data
		$error = array();
		foreach ($mod_config['install_check']['alter_db'] as $key => $value)
		{
			$table = $key;
			foreach ($value as $column)
			{
				$sql = 'SELECT ' . $column . '
					FROM ' . $table;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if ($db->sql_error_triggered)
				{
					$db->sql_error_triggered = false;
					$error[] = $column;
				}
				unset($row);
			}
			
			if (sizeof($error))
			{
				$passed['mod'] = false;
				$result = '<strong style="color:red">' . sprintf($user->lang['VERIFY_TABLE_NOT_ALTERED'], $table, implode('<br />', $error)) . '</strong>';
			}
			else
			{
				$result = '<strong style="color:green">' . sprintf($user->lang['VERIFY_TABLE_ALTERED'], $table) . '</strong>';
			}
			unset($error);
		}
		
		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['VERIFY_OTHER_DB_DATA'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> false,
			'S_LEGEND'		=> false,
		));
		
		// Check if modules are present
		$error = array();
		foreach($mod_config['install_check']['modules']['acp'] as $key => $value)
		{
			$module_basename = $key;
			foreach ($value as $module_mode)
			{
				$sql = 'SELECT parent_id
					FROM ' . MODULES_TABLE . "
					WHERE module_basename = '$module_basename'
						AND module_class = 'acp'
						AND module_mode = '$module_mode'";
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					$error[] = $user->lang['MODULE_ACP'] . ': module_basename = ' . $module_basename . ', module_mode = ' . $module_mode;
				}
				unset($row);
			}
		}

		foreach($mod_config['install_check']['modules']['ucp'] as $key => $value)
		{
			$module_basename = $key;
			foreach ($value as $module_mode)
			{
				$sql = 'SELECT parent_id
					FROM ' . MODULES_TABLE . "
					WHERE module_basename = '$module_basename'
						AND module_class = 'ucp'
						AND module_mode = '$module_mode'";
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					$error[] = $user->lang['MODULE_UCP'] . ': module_basename = ' . $module_basename . ', module_mode = ' . $module_mode;
				}
				unset($row);
			}
		}
		
		if (sizeof($error))
		{
			$passed['mod'] = false;
			$result = '<strong style="color:red">' . sprintf($user->lang['VERIFY_MISSING_MODULES'], implode('<br /><br />', $error)) . '</strong>';
		}
		else
		{
			$result = '<strong style="color:green">' . $user->lang['VERIFY_ALL_MODULES'] . '</strong>';
		}
		unset($error);
		
		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['VERIFY_MODULES'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> false,
			'S_LEGEND'		=> false,
		));
		
		// Check if permissions exist
		$error = array();
		foreach ($mod_config['permission_options']['global'] as $value)
		{
			$sql = 'SELECT auth_option_id
				FROM ' . ACL_OPTIONS_TABLE . "
				WHERE auth_option = '$value'";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				$error[] = $value;
			}
			unset($row);
		}

		foreach ($mod_config['arcade_permission_options']['local'] as $value)
		{
			$sql = 'SELECT auth_option_id
				FROM ' . ACL_ARCADE_OPTIONS_TABLE . "
				WHERE auth_option = '$value'";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row || $db->sql_error_triggered)
			{
				$db->sql_error_triggered = false;
				$error[] = $value;
			}
			unset($row);
		}

		if (sizeof($error))
		{
			$passed['mod'] = false;
			$result = '<strong style="color:red">' . sprintf($user->lang['VERIFY_MISSING_PERMISSIONS'], implode('<br />', $error)) . '</strong>';
		}
		else
		{
			$result = '<strong style="color:green">' . $user->lang['VERIFY_ALL_PERMISSIONS'] . '</strong>';
		}
		unset($error);
		
		$template->assign_block_vars('checks', array(
			'TITLE'			=> $user->lang['VERIFY_PERMISSIONS'],
			'RESULT'		=> $result,

			'S_EXPLAIN'		=> false,
			'S_LEGEND'		=> false,
		));

		$tables = array(ACL_OPTIONS_TABLE, ACL_ARCADE_OPTIONS_TABLE);
		foreach ($tables as $table)
		{
			$sql = 'SELECT auth_option_id, auth_option
				FROM ' . $table . '
				ORDER BY auth_option_id';
			$result = $db->sql_query($sql);
			$auth_option_id = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$auth_option_id[$row['auth_option_id']] = $row['auth_option'];
			}
			$db->sql_freeresult($result);

			$duplicate_auth = array_count_values($auth_option_id);
			unset($auth_option_id);

			$auth_option_id = array();
			foreach ($duplicate_auth as $key => $value)
			{
				if ($value > 1)
				{
					$auth_option_id[] = sprintf($user->lang['DUPLICATE_AUTH_FOUND'], $key, $value);
				}
			}

			if (!empty($auth_option_id))
			{
				$passed['mod'] = false;
				$result = '<strong style="color:red">' . sprintf($user->lang['VERIFY_FOUND_DUPLICATE_PERMISSIONS'], $table, implode('<br />', $auth_option_id)) . '</strong>';
			}
			else
			{
				$result = '<strong style="color:green">' . $user->lang['VERIFY_NO_DUPLICATE_PERMISSIONS'] . '</strong>';
			}
			
			$template->assign_block_vars('checks', array(
				'TITLE'			=> ($table == ACL_OPTIONS_TABLE) ? $user->lang['VERIFY_DUPLICATE_PERMISSIONS'] : $user->lang['VERIFY_DUPLICATE_ARCADE_PERMISSIONS'],
				'RESULT'		=> $result,

				'S_EXPLAIN'		=> false,
				'S_LEGEND'		=> false,
			));
		}
		
		$title = (!in_array(false, $passed)) ? $user->lang['INSTALL_CONGRATS'] : $user->lang['VERIFY_ERRORS'];
		$body = (!in_array(false, $passed)) ? sprintf($user->lang['VERIFY_CONGRATS_EXPLAIN'], $mod_config['mod_version']) : sprintf($user->lang['VERIFY_ERRORS_EXPLAIN'], $mod_config['mod_version']);
		$url = (!in_array(false, $passed)) ? append_sid("{$phpbb_root_path}adm/index.$phpEx", false, true, $user->session_id) : $this->p_master->module_url . "?mode=$mode&amp;sub=verify";
		$submit = (!in_array(false, $passed)) ? $user->lang['INSTALL_LOGIN'] : $user->lang['INSTALL_TEST'];

		$template->assign_vars(array(
			'TITLE'		=> $title,
			'BODY'		=> $body,
			'L_SUBMIT'	=> $submit,
			'U_ACTION'	=> $url,
		));
	}
}

?>