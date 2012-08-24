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
	if ($this->installed_version)
	{
		return;
	}

	$module[] = array(
		'module_type'		=> 'install',
		'module_title'		=> 'INSTALL',
		'module_filename'	=> substr(basename(__FILE__), 0, -strlen($phpEx)-1),
		'module_order'		=> 10,
		'module_subs'		=> '',
		'module_stages'		=> array('INTRO', 'REQUIREMENTS', 'INSTALL'),
		'module_reqs'		=> ''
	);
}

/**
* Installation
* @package install
*/
class install_install extends module
{
	function install_install(&$p_master)
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
					'TITLE'			=> $user->lang['INSTALL_INTRO'],
					'BODY'			=> $user->lang['INSTALL_INTRO_BODY'],
					'L_SUBMIT'		=> $user->lang['NEXT_STEP'],
					'U_ACTION'		=> $this->p_master->module_url . "?mode=$mode&amp;sub=requirements",
				));

			break;

			case 'requirements':
				$this->requirements($mode, $sub);

			break;

			case 'install':
				$this->install($mode, $sub);

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

		if (version_compare($config['version'], $mod_config['version']['phpbb'], '<'))
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
		$url = (!in_array(false, $passed)) ? $this->p_master->module_url . "?mode=$mode&amp;sub=install" : $this->p_master->module_url . "?mode=$mode&amp;sub=requirements";
		$submit = (!in_array(false, $passed)) ? $user->lang['INSTALL_START'] : $user->lang['INSTALL_TEST'];

		$template->assign_vars(array(
			'L_SUBMIT'	=> $submit,
			'S_HIDDEN'	=> $s_hidden_fields,
			'U_ACTION'	=> $url,
		));
	}

	/**
	* Obtain the information required to connect to the database
	*/
	function install($mode, $sub)
	{
		global $user, $template, $cache, $phpEx, $phpbb_root_path, $phpbb_db_tools, $mod_config, $table_prefix;

		$this->page_title = $user->lang['STAGE_INSTALL_TRACKER'];

		// Load all the tables
		include ($phpbb_root_path . 'install/schemas/tracker/schema_structure.' . $phpEx);
		foreach ($schema_data as $table_name => $table_data)
		{
			// Change prefix, we always have phpbb_, therefore we can do a substr() here
			$table_name = $table_prefix . substr($table_name, 6);
			// Now create the table
			$phpbb_db_tools->sql_create_table($table_name, $table_data);
		}

		// Load all the tracker data
		if (!empty($mod_config['data_file']['add']))
		{
			$this->p_master->load_data($mod_config['data_file']['add']);
		}

		$this->p_master->set_config('attachment_path', 'files/tracker');
		$this->p_master->set_config('allow_attachments', '1');
		$this->p_master->set_config('max_attachments', '5');
		$this->p_master->set_config('enable_post_confirm', '1');
		$this->p_master->set_config('send_email', '1');
		$this->p_master->set_config('tickets_per_page', '10');
		$this->p_master->set_config('posts_per_page', '10');
		$this->p_master->set_config('top_reporters', '10');
		$this->p_master->set_config('default_status_type', '1');
		$this->p_master->set_config('version', '0.5.0');

		// Alter some existing tables
		if (!empty($mod_config['schema_changes']))
		{
			$phpbb_db_tools->perform_schema_changes($mod_config['schema_changes']);
		}

		// Add the admin permissions for the tracker acp modules
		$this->p_master->add_permissions($mod_config['permission_options']['phpbb']);
		// Add the admin permissions for the tracker acp modules to the correct roles
		$this->p_master->update_roles(array('ROLE_ADMIN_STANDARD', 'ROLE_ADMIN_FULL'), $mod_config['permission_options']['phpbb']['global']);

		// Add the modules
		foreach ($mod_config['modules'] as $modules)
		{
			$this->p_master->create_modules($modules['parent_module_data'], $modules['module_data']);
		}

		// Purge the cache
		$this->p_master->cache_purge(array('template', 'theme', 'imageset', 'auth', ''));

		$template->assign_vars(array(
			'TITLE'		=> $user->lang['INSTALL_CONGRATS'],
			'BODY'		=> $user->lang['STAGE_INSTALL_TRACKER_EXPLAIN'] . '<br /><br />' . sprintf($user->lang['INSTALL_CONGRATS_EXPLAIN'], $mod_config['version']['current']),
			'L_SUBMIT'	=> $user->lang['INSTALL_LOGIN'],
			'U_ACTION'	=> append_sid("{$phpbb_root_path}adm/index.$phpEx", false, true, $user->session_id),
		));
	}
}

?>