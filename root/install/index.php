<?php
/**
*
* @package install
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**#@+
* @ignore
*/
define('IN_PHPBB', true);
define('IN_INSTALL', true);

$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

require($phpbb_root_path . 'common.' . $phpEx);
require($phpbb_root_path . 'includes/db/db_tools.' . $phpEx);
require($phpbb_root_path . 'includes/functions_module.' . $phpEx);
require($phpbb_root_path . 'includes/acp/acp_modules.' . $phpEx);
require($phpbb_root_path . 'includes/acp/auth.' . $phpEx);
require($phpbb_root_path . 'includes/functions_install.' . $phpEx);
require($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

// Report all errors, except notices
error_reporting(E_ALL);

// Try to override some limits - maybe it helps some...
@set_time_limit(0);
$mem_limit = @ini_get('memory_limit');
if (!empty($mem_limit))
{
	$unit = strtolower(substr($mem_limit, -1, 1));
	$mem_limit = (int) $mem_limit;

	if ($unit == 'k')
	{
		$mem_limit = floor($mem_limit / 1024);
	}
	else if ($unit == 'g')
	{
		$mem_limit *= 1024;
	}
	else if (is_numeric($unit))
	{
		$mem_limit = floor((int) ($mem_limit . $unit) / 1048576);
	}
	$mem_limit = max(128, $mem_limit) . 'M';
}
else
{
	$mem_limit = '128M';
}
@ini_set('memory_limit', $mem_limit);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup(array('mods/tracker_install', 'acp/modules'));

// Tracker files and configuration
require($phpbb_root_path . 'tracker/includes/functions_files.' . $phpEx);
require($phpbb_root_path . 'tracker/includes/constants.' . $phpEx);
require($phpbb_root_path . 'install/config.' . $phpEx);

// This is done here so when we add/delete the module we will see the language
// value inside the admin log
$module_info = new p_master();
$module_info->add_mod_info('acp');
$module_info->add_mod_info('mcp');
$module_info->add_mod_info('ucp');

if ($user->data['user_type'] != USER_FOUNDER)
{
	trigger_error('INST_ERR_AUTH');
}

$mode = request_var('mode', 'overview');
$sub = request_var('sub', '');

$template->set_custom_template('../adm/style', 'admin');
$template->assign_var('T_TEMPLATE_PATH', '../adm/style');

// Set some standard variables we want to force
$config['load_tplcompile'] = '1';
// the acp template is never stored in the database
$user->theme['template_storedb'] = false;

$db->sql_return_on_error(true);

$install = new module();
$phpbb_db_tools = new phpbb_db_tools($db);
$file_functions = new file_functions();

$install->create('install', "index.$phpEx", $mode, $sub);
$install->load();

// Generate the page
$install->page_header();
$install->generate_navigation();

$template->set_filenames(array(
	'body' => $install->get_tpl_name())
);

$db->sql_return_on_error(false);
$install->page_footer();

/**
* @package install
*/
class module
{
	var $id = 0;
	var $type = 'install';
	var $module_ary = array();
	var $mod_config = array();
	var $filename;
	var $module_url = '';
	var $tpl_name = '';
	var $mode;
	var $sub;
	var $installed_version = false;

	/**
	* Private methods, should not be overwritten
	*/
	function create($module_type, $module_url, $selected_mod = false, $selected_submod = false)
	{
		global $db, $config, $phpEx, $mod_config, $phpbb_root_path;

		// Check if the tracker is already installed
		$tables = get_tables($db);
		if (in_array(TRACKER_CONFIG_TABLE, $tables))
		{
			$sql = 'SELECT *
				FROM ' . TRACKER_CONFIG_TABLE;
			$result = $db->sql_query($sql);

			$this->mod_config = array();
			while ($row = $db->sql_fetchrow($result))
			{
				if ($row['config_name'] == 'version')
				{
					$this->installed_version = $this->format_version((string) $row['config_value']);
				}
				$this->mod_config[$row['config_name']] = $row['config_value'];
			}
			$db->sql_freeresult($result);
		}
		unset($tables);

		$module = array();

		// Grab module information using Bart's "neat-o-module" system (tm)
		$dir = @opendir('.');

		if (!$dir)
		{
			$this->error('Unable to access the installation directory', __LINE__, __FILE__);
		}

		$setmodules = 1;
		while (($file = readdir($dir)) !== false)
		{
			if (preg_match('#^install_(.*?)\.' . $phpEx . '$#', $file))
			{
				include($file);
			}
		}
		closedir($dir);

		unset($setmodules);

		if (!sizeof($module))
		{
			$this->error('No installation modules found', __LINE__, __FILE__);
		}

		// Order to use and count further if modules get assigned to the same position or not having an order
		$max_module_order = 1000;

		foreach ($module as $row)
		{
			// Check any module pre-reqs
			if ($row['module_reqs'] != '')
			{
			}

			// Module order not specified or module already assigned at this position?
			if (!isset($row['module_order']) || isset($this->module_ary[$row['module_order']]))
			{
				$row['module_order'] = $max_module_order;
				$max_module_order++;
			}

			$this->module_ary[$row['module_order']]['name'] = $row['module_title'];
			$this->module_ary[$row['module_order']]['filename'] = $row['module_filename'];
			$this->module_ary[$row['module_order']]['subs'] = $row['module_subs'];
			$this->module_ary[$row['module_order']]['stages'] = $row['module_stages'];

			if (strtolower($selected_mod) == strtolower($row['module_title']))
			{
				$this->id = (int) $row['module_order'];
				$this->filename = (string) $row['module_filename'];
				$this->module_url = (string) $module_url;
				$this->mode = (string) $selected_mod;
				// Check that the sub-mode specified is valid or set a default if not
				if (is_array($row['module_subs']))
				{
					$this->sub = strtolower((in_array(strtoupper($selected_submod), $row['module_subs'])) ? $selected_submod : $row['module_subs'][0]);
				}
				else if (is_array($row['module_stages']))
				{
					$this->sub = strtolower((in_array(strtoupper($selected_submod), $row['module_stages'])) ? $selected_submod : $row['module_stages'][0]);
				}
				else
				{
					$this->sub = '';
				}
			}
		} // END foreach
	} // END create

	/**
	* Load and run the relevant module if applicable
	*/
	function load($mode = false, $run = true)
	{
		global $phpbb_root_path, $phpEx;

		if ($run)
		{
			if (!empty($mode))
			{
				$this->mode = $mode;
			}

			$module = $this->filename;
			if (!class_exists($module))
			{
				$this->error('Module "' . htmlspecialchars($module) . '" not accessible.', __LINE__, __FILE__);
			}
			$this->module = new $module($this);

			if (method_exists($this->module, 'main'))
			{
				$this->module->main($this->mode, $this->sub);
			}
		}
	}

	/**
	* Output the standard page header
	*/
	function page_header()
	{
		if (defined('HEADER_INC'))
		{
			return;
		}

		define('HEADER_INC', true);
		global $template, $user, $stage, $phpbb_root_path;

		$template->assign_vars(array(
			'PAGE_TITLE'			=> $this->get_page_title(),
			'T_IMAGE_PATH'			=> $phpbb_root_path . 'adm/images/',

			'S_CONTENT_DIRECTION' 	=> $user->lang['DIRECTION'],
			'S_CONTENT_FLOW_BEGIN'	=> ($user->lang['DIRECTION'] == 'ltr') ? 'left' : 'right',
			'S_CONTENT_FLOW_END'	=> ($user->lang['DIRECTION'] == 'ltr') ? 'right' : 'left',
			'S_CONTENT_ENCODING' 	=> 'UTF-8',

			'S_USER_LANG'			=> $user->lang['USER_LANG'],
			)
		);

		header('Content-type: text/html; charset=UTF-8');
		header('Cache-Control: private, no-cache="set-cookie"');
		header('Expires: 0');
		header('Pragma: no-cache');

		return;
	}

	/**
	* Output the standard page footer
	*/
	function page_footer()
	{
		global $db, $template;

		$template->display('body');

		// Close our DB connection.
		if (!empty($db) && is_object($db))
		{
			$db->sql_close();
		}

		if (function_exists('exit_handler'))
		{
			exit_handler();
		}
	}

	/**
	* Returns desired template name
	*/
	function get_tpl_name()
	{
		return $this->module->tpl_name . '.html';
	}

	/**
	* Returns the desired page title
	*/
	function get_page_title()
	{
		global $user;

		if (!isset($this->module->page_title))
		{
			return '';
		}

		return (isset($lang[$this->module->page_title])) ? $lang[$this->module->page_title] : $this->module->page_title;
	}

	/**
	* Generate the navigation tabs
	*/
	function generate_navigation()
	{
		global $user, $template, $phpEx;

		if (is_array($this->module_ary))
		{
			@ksort($this->module_ary);
			foreach ($this->module_ary as $cat_ary)
			{
				$cat = $cat_ary['name'];
				$l_cat = (!empty($user->lang['CAT_' . $cat])) ? $user->lang['CAT_' . $cat] : preg_replace('#_#', ' ', $cat);
				$cat = strtolower($cat);
				$url = $this->module_url . "?mode=$cat";

				if ($this->mode == $cat)
				{
					$template->assign_block_vars('t_block1', array(
						'L_TITLE'		=> $l_cat,
						'S_SELECTED'	=> true,
						'U_TITLE'		=> $url,
					));

					if (is_array($this->module_ary[$this->id]['subs']))
					{
						$subs = $this->module_ary[$this->id]['subs'];
						foreach ($subs as $option)
						{
							$l_option = (!empty($user->lang['SUB_' . $option])) ? $user->lang['SUB_' . $option] : preg_replace('#_#', ' ', $option);
							$option = strtolower($option);
							$url = $this->module_url . '?mode=' . $this->mode . "&amp;sub=$option";

							$template->assign_block_vars('l_block1', array(
								'L_TITLE'		=> $l_option,
								'S_SELECTED'	=> ($this->sub == $option),
								'U_TITLE'		=> $url,
							));
						}
					}

					if (is_array($this->module_ary[$this->id]['stages']))
					{
						$subs = $this->module_ary[$this->id]['stages'];
						$matched = false;
						foreach ($subs as $option)
						{
							$l_option = (!empty($user->lang['STAGE_' . $option])) ? $user->lang['STAGE_' . $option] : preg_replace('#_#', ' ', $option);
							$option = strtolower($option);
							$matched = ($this->sub == $option) ? true : $matched;

							$template->assign_block_vars('l_block2', array(
								'L_TITLE'		=> $l_option,
								'S_SELECTED'	=> ($this->sub == $option),
								'S_COMPLETE'	=> !$matched,
							));
						}
					}
				}
				else
				{
					$template->assign_block_vars('t_block1', array(
						'L_TITLE'		=> $l_cat,
						'S_SELECTED'	=> false,
						'U_TITLE'		=> $url,
					));
				}
			}
		}
	}

	/**
	* Output an error message
	* If skip is true, return and continue execution, else exit
	*/
	function error($error, $line, $file, $skip = false)
	{
		global $user, $db, $template;

		if ($skip)
		{
			$template->assign_block_vars('checks', array(
				'S_LEGEND'	=> true,
				'LEGEND'	=> $user->lang['INST_ERR'],
			));

			$template->assign_block_vars('checks', array(
				'TITLE'		=> basename($file) . ' [ ' . $line . ' ]',
				'RESULT'	=> '<b style="color:red">' . $error . '</b>',
			));

			return;
		}

		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
		echo '<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">';
		echo '<head>';
		echo '<meta http-equiv="content-type" content="text/html; charset=utf-8" />';
		echo '<title>' . $user->lang['INST_ERR_FATAL'] . '</title>';
		echo '<link href="../adm/style/admin.css" rel="stylesheet" type="text/css" media="screen" />';
		echo '</head>';
		echo '<body id="errorpage">';
		echo '<div id="wrap">';
		echo '	<div id="page-header">';
		echo '	</div>';
		echo '	<div id="page-body">';
		echo '		<div id="acp">';
		echo '		<div class="panel">';
		echo '			<span class="corners-top"><span></span></span>';
		echo '			<div id="content">';
		echo '				<h1>' . $user->lang['INST_ERR_FATAL'] . '</h1>';
		echo '		<p>' . $user->lang['INST_ERR_FATAL'] . "</p>\n";
		echo '		<p>' . basename($file) . ' [ ' . $line . " ]</p>\n";
		echo '		<p><b>' . $error . "</b></p>\n";
		echo '			</div>';
		echo '			<span class="corners-bottom"><span></span></span>';
		echo '		</div>';
		echo '		</div>';
		echo '	</div>';
		echo '	<div id="page-footer">';
		echo '		Powered by phpBB &copy; 2000, 2002, 2005, 2007 <a href="http://www.phpbb.com/">phpBB Group</a>';
		echo '	</div>';
		echo '</div>';
		echo '</body>';
		echo '</html>';

		if (!empty($db) && is_object($db))
		{
			$db->sql_close();
		}

		exit_handler();
	}

	/**
	* Output an error message for a database related problem
	* If skip is true, return and continue execution, else exit
	*/
	function db_error($error, $sql, $line, $file, $skip = false)
	{
		global $user, $db, $template;

		if ($skip)
		{
			$template->assign_block_vars('checks', array(
				'S_LEGEND'	=> true,
				'LEGEND'	=> $user->lang['INST_ERR_FATAL'],
			));

			$template->assign_block_vars('checks', array(
				'TITLE'		=> basename($file) . ' [ ' . $line . ' ]',
				'RESULT'	=> '<b style="color:red">' . $error . '</b><br />&#187; SQL:' . $sql,
			));

			return;
		}

		$template->set_filenames(array(
			'body' => 'install_error.html')
		);
		$this->page_header();
		$this->generate_navigation();

		$template->assign_vars(array(
			'MESSAGE_TITLE'		=> $user->lang['INST_ERR_FATAL_DB'],
			'MESSAGE_TEXT'		=> '<p>' . basename($file) . ' [ ' . $line . ' ]</p><p>SQL : ' . $sql . '</p><p><b>' . $error . '</b></p>',
		));

		// Rollback if in transaction
		if ($db->transaction)
		{
			$db->sql_transaction('rollback');
		}

		$this->page_footer();
	}

	/**
	* Generate the relevant HTML for an input field and the associated label and explanatory text
	*/
	function input_field($name, $type, $value='', $options='')
	{
		global $user;
		$tpl_type = explode(':', $type);
		$tpl = '';

		switch ($tpl_type[0])
		{
			case 'text':
			case 'password':
				$size = (int) $tpl_type[1];
				$maxlength = (int) $tpl_type[2];

				$tpl = '<input id="' . $name . '" type="' . $tpl_type[0] . '"' . (($size) ? ' size="' . $size . '"' : '') . ' maxlength="' . (($maxlength) ? $maxlength : 255) . '" name="' . $name . '" value="' . $value . '" />';
			break;

			case 'textarea':
				$rows = (int) $tpl_type[1];
				$cols = (int) $tpl_type[2];

				$tpl = '<textarea id="' . $name . '" name="' . $name . '" rows="' . $rows . '" cols="' . $cols . '">' . $value . '</textarea>';
			break;

			case 'radio':
				$key_yes	= ($value) ? ' checked="checked" id="' . $name . '"' : '';
				$key_no		= (!$value) ? ' checked="checked" id="' . $name . '"' : '';

				$tpl_type_cond = explode('_', $tpl_type[1]);
				$type_no = ($tpl_type_cond[0] == 'disabled' || $tpl_type_cond[0] == 'enabled') ? false : true;

				$tpl_no = '<label><input type="radio" name="' . $name . '" value="0"' . $key_no . ' class="radio" /> ' . (($type_no) ? $user->lang['NO'] : $user->lang['DISABLED']) . '</label>';
				$tpl_yes = '<label><input type="radio" name="' . $name . '" value="1"' . $key_yes . ' class="radio" /> ' . (($type_no) ? $user->lang['YES'] : $user->lang['ENABLED']) . '</label>';

				$tpl = ($tpl_type_cond[0] == 'yes' || $tpl_type_cond[0] == 'enabled') ? $tpl_yes . '&nbsp;&nbsp;' . $tpl_no : $tpl_no . '&nbsp;&nbsp;' . $tpl_yes;
			break;

			case 'select':
				eval('$s_options = ' . str_replace('{VALUE}', $value, $options) . ';');
				$tpl = '<select id="' . $name . '" name="' . $name . '">' . $s_options . '</select>';
			break;

			case 'custom':
				eval('$tpl = ' . str_replace('{VALUE}', $value, $options) . ';');
			break;

			default:
			break;
		}

		return $tpl;
	}

	/*
	* The following are used perform various operations
	* while installing/updating/removing the tracker
	*/

	/**
	* Add new permission
	*/
	function add_permissions($options, $mode = '')
	{
		switch ($mode)
		{
			default:
				$auth_admin = new auth_admin();
			break;
		}
		$auth_admin->acl_add_option($options);
	}


	function add_roles($roles, $mode = '')
	{
		global $db;

		$options_table = $roles_table = $roles_data_table = '';

		switch ($mode)
		{
			default:
				$options_table = ACL_OPTIONS_TABLE;
				$roles_table = ACL_ROLES_TABLE;
				$roles_data_table = ACL_ROLES_DATA_TABLE;
			break;
		}

		$roles_array = $sql_ary = array();
		foreach ($roles as $role)
		{
			$sql = "SELECT role_id FROM $roles_table
				WHERE role_name = '" . $db->sql_escape($role['role_name']) . "'";
			$db->sql_query($sql);
			$role_id = $db->sql_fetchfield('role_id');

			if ($role_id)
			{
				continue;
			}

			$sql = "SELECT MAX(role_order) AS max FROM $roles_table
				WHERE role_type = '" . $db->sql_escape($role['role_type']) . "'";
			$db->sql_query($sql);
			$role_order = $db->sql_fetchfield('max');
			$role_order = (!$role_order) ? 1 : $role_order + 1;


			$sql = 'INSERT INTO ' . $roles_table . ' ' . $db->sql_build_array('INSERT', array(
				'role_name'			=> $role['role_name'],
				'role_description'	=> $role['role_description'],
				'role_type'			=> $role['role_type'],
				'role_order'		=> $role_order,
			));
			$db->sql_query($sql);
			$role_id = $db->sql_nextid();

			$sql = "SELECT auth_option_id FROM $options_table
				WHERE " . $db->sql_in_set('auth_option', $role['data']['options']);

			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$sql_ary[] = array(
					'role_id'			=> $role_id,
					'auth_option_id'	=> $row['auth_option_id'],
					'auth_setting'		=> $role['data']['access'],
				);
			}
			$db->sql_freeresult($result);
		}
		$db->sql_multi_insert($roles_data_table, $sql_ary);
	}

	/**
	* Add permission options to roles
	* Takes array or roles and permissions options
	*/
	function update_roles($roles, $options, $mode = '')
	{
		global $db;

		$options_table = $roles_table = $roles_data_table = '';

		switch ($mode)
		{
			default:
				$options_table = ACL_OPTIONS_TABLE;
				$roles_table = ACL_ROLES_TABLE;
				$roles_data_table = ACL_ROLES_DATA_TABLE;
			break;
		}

		$roles_array = array();
		foreach ($roles as $role)
		{
			$sql = "SELECT role_id
				FROM $roles_table
				WHERE role_name = '" . $db->sql_escape($role) . "'";
			$result = $db->sql_query($sql);
			$role_id = $db->sql_fetchfield('role_id');
			$db->sql_freeresult($result);

			$roles_array[$role] = $role_id;
		}
		unset($role);

		$options_array = array();
		foreach ($options as $option)
		{
			$sql = "SELECT auth_option_id
				FROM $options_table
				WHERE auth_option = '" . $db->sql_escape($option) . "'";
			$result = $db->sql_query($sql);
			$auth_option_id = $db->sql_fetchfield('auth_option_id');
			$db->sql_freeresult($result);

			$options_array[$option] = $auth_option_id;
		}
		unset($option);

		$sql_ary = array();
		foreach ($roles_array as $role)
		{
			if ($role)
			{
				foreach ($options_array as $option)
				{
					$sql_ary[] = array(
						'role_id'			=> $role,
						'auth_option_id'	=> $option,
						'auth_setting'		=> true,
					);
				}
			}
		}
		$db->sql_multi_insert($roles_data_table, $sql_ary);
	}

	function remove_roles($roles, $mode)
	{
		global $cache, $db;

		$options_table = $roles_table = $roles_data_table = '';

		switch ($mode)
		{
			default:
				$roles_table = ACL_ROLES_TABLE;
				$roles_data_table = ACL_ROLES_DATA_TABLE;
			break;
		}

		foreach ($roles as $role)
		{
			$sql = "SELECT role_id FROM $roles_data
				WHERE role_name = '" . $db->sql_escape($role) . "'";
			$db->sql_query($sql);
			$role_id = $db->sql_fetchfield('role_id');

			if (!$role_id)
			{
				continue;
			}

			$db->sql_query("DELETE FROM $roles_data_table WHERE role_id = " . (int) $role_id);
			$db->sql_query("DELETE FROM $roles_table WHERE role_id = " . (int) $role_id);
		}

		switch ($mode)
		{
			default:
				$auth_admin = new auth_admin();
				$cache->destroy('_acl_options');
			break;
		}

		$auth_admin->acl_clear_prefetch();
	}

	/**
	* Removes permissions from phpBB or phpBB Tracker permissions
	* Completely removes a permission options from
	* all related tables
	*/
	function remove_permissions($options, $mode = '')
	{
		global $db, $cache;

		$options_table = '';

		switch ($mode)
		{
			default:
				$options_table = ACL_OPTIONS_TABLE;
				$tables = array(ACL_OPTIONS_TABLE, ACL_GROUPS_TABLE, ACL_USERS_TABLE, ACL_ROLES_DATA_TABLE);
			break;
		}

		$auth_option_id = array();
		if (!empty($options['local']))
		{
			foreach ($options['local'] as $local)
			{
				$sql = "SELECT auth_option_id
					FROM $options_table
				WHERE auth_option = '" . $db->sql_escape($local) . "'";

				$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result))
				{
					$auth_option_id[] = $row['auth_option_id'];
				}
				$db->sql_freeresult($result);
			}
		}

		if (!empty($options['global']))
		{
			foreach ($options['global'] as $global)
			{
				$sql = "SELECT auth_option_id
					FROM $options_table
				WHERE auth_option = '" . $db->sql_escape($global) . "'";

				$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result))
				{
					$auth_option_id[] = $row['auth_option_id'];
				}
				$db->sql_freeresult($result);
			}
		}

		// We now have a list of ids we need to remove from the auth tables...
		if (!empty($auth_option_id))
		{
			foreach ($tables as $table)
			{
				$sql = "DELETE FROM $table
					WHERE " . $db->sql_in_set('auth_option_id', array_map('intval', $auth_option_id));
				$db->sql_query($sql);
			}

			switch ($mode)
			{
				default:
					$db->sql_return_on_error(true);
					$auth_admin = new auth_admin();
					$cache->destroy('_acl_options');
					$auth_admin->acl_clear_prefetch();
					$db->sql_return_on_error(false);
				break;
			}
		}
	}

	/**
	* Creates modules
	* Creates parents and adds modules
	* Correct format is in config.php file in install directory
	*/
	function create_modules($parent_module_data, $module_data)
	{
		global $phpbb_root_path, $phpEx, $db;
		$_module = new acp_modules();
		$_module->module_class = $parent_module_data['module_class'];

		$db->sql_error_triggered = false;

		// If the module class is acp we add it to the MODS tab in the ACP
		if ($parent_module_data['module_class'] == 'acp')
		{
			$sql = 'SELECT module_id
				FROM ' . MODULES_TABLE . "
				WHERE module_langname = 'ACP_CAT_DOT_MODS'";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			// Create MODS tab if missing and get id again
			if(!$row)
			{
				$parent_tab = array(
					'module_basename' 	=> '',
					'module_enabled'	=> '1',
					'module_display' 	=> '1',
					'parent_id' 		=> '0',
					'module_class' 		=> 'acp',
					'module_langname' 	=> 'ACP_CAT_DOT_MODS',
					'module_mode' 		=> '',
					'module_auth' 		=> '',
				);

				$_module->update_module_data($parent_tab, true);
				add_log('admin', 'LOG_MODULE_ADD', $_module->lang_name($parent_tab['module_langname']));

				$sql = 'SELECT module_id
					FROM ' . MODULES_TABLE . "
					WHERE module_langname = 'ACP_CAT_DOT_MODS'";
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
			}

			$parent_module_data['parent_id'] = $row['module_id'];
		}

		// Add category
		$_module->update_module_data($parent_module_data, true);
		$_module->remove_cache_file();

		// Check for last sql error happened
		if ($db->sql_error_triggered)
		{
			$db->sql_error_triggered = false;
			$error = $db->sql_error($db->sql_error_sql);
			$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
		}
		else
		{
			add_log('admin', 'LOG_MODULE_ADD', $_module->lang_name($parent_module_data['module_langname']));
		}

		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . "
			WHERE module_langname = '" . $db->sql_escape($parent_module_data['module_langname']) . "'
				AND module_class = '" . $db->sql_escape($parent_module_data['module_class']) . "'";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		for ($i = 0, $count = sizeof($module_data);$i < $count; $i++)
		{
			$module_data[$i]['parent_id'] = $row['module_id'];
			$_module->update_module_data($module_data[$i], true);
			$_module->remove_cache_file();

			// Check for last sql error happened
			if ($db->sql_error_triggered)
			{
				$db->sql_error_triggered = false;
				$error = $db->sql_error($db->sql_error_sql);
				$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
			}
			else
			{
				add_log('admin', 'LOG_MODULE_ADD', $_module->lang_name($module_data[$i]['module_langname']));
			}
		}

		return;
	}

	/**
	* Adds modules to an existing parent
	*/
	function add_modules($parent_module_langname, $parent_module_class, $module_data)
	{
		global $phpbb_root_path, $phpEx, $db;
		$_module = new acp_modules();
		$_module->module_class = $parent_module_class;

		$db->sql_error_triggered = false;

		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . "
			WHERE module_langname = '" . $db->sql_escape($parent_module_langname) . "'
				AND module_class = '" . $db->sql_escape($parent_module_class) . "'";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if ($row)
		{
			for ($i = 0, $count = sizeof($module_data);$i < $count; $i++)
			{
				$module_data[$i]['parent_id'] = $row['module_id'];
				$_module->update_module_data($module_data[$i], true);
				$_module->remove_cache_file();

				// Check for last sql error happened
				if ($db->sql_error_triggered)
				{
					$db->sql_error_triggered = false;
					$error = $db->sql_error($db->sql_error_sql);
					$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
				}
				else
				{
					add_log('admin', 'LOG_MODULE_ADD', $_module->lang_name($module_data[$i]['module_langname']));
				}
			}
		}

		return;
	}

	/**
	* Remove module data
	* Expects module_data to be an array of module_basename's to remove
	* Expects parent_module_data to be an array of module_langname's to remove
	*/
	function remove_modules($module_data)
	{
		global $db;
		$_module = new acp_modules();

		$db->sql_error_triggered = false;

		if (!empty($module_data['modules']))
		{
			$sql = 'SELECT module_id, module_class
				FROM ' . MODULES_TABLE . '
				WHERE ' . $db->sql_in_set('module_basename', $module_data['modules']);
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$_module->module_class = $row['module_class'];
				$_module->delete_module($row['module_id']);
				// Check for last sql error happened
				if ($db->sql_error_triggered)
				{
					$db->sql_error_triggered = false;
					$error = $db->sql_error($db->sql_error_sql);
					$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
				}
			}
			$db->sql_freeresult($result);
		}

		if (!empty($module_data['parents']))
		{
			// Needs to be ordered descending so that we can remove the parent module (tab) last
			$sql = 'SELECT module_id, module_class
				FROM ' . MODULES_TABLE . '
				WHERE ' . $db->sql_in_set('module_langname', $module_data['parents']) . '
				ORDER BY module_id DESC';
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$_module->module_class = $row['module_class'];
				$_module->delete_module($row['module_id']);
				// Check for last sql error happened
				if ($db->sql_error_triggered)
				{
					$db->sql_error_triggered = false;
					$error = $db->sql_error($db->sql_error_sql);
					$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
				}
			}
			$db->sql_freeresult($result);
		}

		return;
	}

	/**
	* Remove single module
	* Expects module_data to be an array of module_basename, module_langname and module_mode to remove
	*/
	function remove_module($module_data)
	{
		global $db;
		$_module = new acp_modules();

		$db->sql_error_triggered = false;

		if (!empty($module_data))
		{
			$sql = 'SELECT module_id, module_class
				FROM ' . MODULES_TABLE . "
				WHERE module_basename = '" . $db->sql_escape($module_data['module_basename']) . "'
					AND module_langname = '" . $db->sql_escape($module_data['module_langname']) . "'
					AND module_mode = '" . $db->sql_escape($module_data['module_mode']) . "'";
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$_module->module_class = $row['module_class'];
				$_module->delete_module($row['module_id']);
				// Check for last sql error happened
				if ($db->sql_error_triggered)
				{
					$db->sql_error_triggered = false;
					$error = $db->sql_error($db->sql_error_sql);
					$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
				}
			}
			$db->sql_freeresult($result);
		}

		return;
	}
	
	/**
	* Load schema table files and display run queries
	*/
	function load_tables($prefix = '')
	{
		global $user, $db, $dbms, $table_prefix, $template;

		$available_dbms = get_available_dbms($dbms);
		// If mysql is chosen, we need to adjust the schema filename slightly to reflect the correct version. ;)
		if ($dbms == 'mysql')
		{
			if (version_compare((isset($db->mysql_version)) ? $db->mysql_version : $db->sql_server_info(true), '4.1.3', '>='))
			{
				$available_dbms[$dbms]['SCHEMA'] .= '_41';
			}
			else
			{
				$available_dbms[$dbms]['SCHEMA'] .= '_40';
			}
		}

		// Ok we have the db info go ahead and read in the relevant schema
		// and work on building the table
		$dbms_schema = 'schemas/tracker/' . $prefix . $available_dbms[$dbms]['SCHEMA'] . '_schema.sql';

		// How should we treat this schema?
		$remove_remarks = $available_dbms[$dbms]['COMMENTS'];
		$delimiter = $available_dbms[$dbms]['DELIM'];

		$sql_query = @file_get_contents($dbms_schema);
		$sql_query = preg_replace('#phpbb_#i', $table_prefix, $sql_query);
		$remove_remarks($sql_query);

		$sql_query = split_sql_file($sql_query, $delimiter);

		$sql_results = '';
		foreach ($sql_query as $sql)
		{
			if (!$db->sql_query($sql))
			{
				$error = $db->sql_error();
				$this->db_error($error['message'], $sql, __LINE__, __FILE__, true);
			}
			else
			{
				$sql_results .= preg_replace('/\t(AND|OR)(\W)/', "\$1\$2", htmlspecialchars(preg_replace('/[\s]*[\n\r\t]+[\n\r\s\t]*/', "\n", $sql))) . "\n\n";
			}
		}

		$template->assign_block_vars('checks', array(
			'S_LEGEND'	=> true,
		));

		$template->assign_block_vars('checks', array(
			'TITLE'		=> $user->lang['INST_SQL_RESULTS'],
			'RESULT'	=> '<textarea rows="10" cols="10">' . trim($sql_results) . '</textarea>',
		));

		unset($sql_query, $sql_results);
	}

	/**
	* Load schema data files and display run queries
	*/
	function load_data($file)
	{
		global $user, $db, $dbms, $table_prefix, $template;

		// Ok tables have been built, let's fill in the basic information
		$sql_query = file_get_contents($file);

		// Deal with any special comments
		switch ($dbms)
		{
			case 'mssql':
			case 'mssql_odbc':
				$sql_query = preg_replace('#\# MSSQL IDENTITY (phpbb_[a-z_]+) (ON|OFF) \##s', 'SET IDENTITY_INSERT \1 \2;', $sql_query);
			break;

			case 'postgres':
				$sql_query = preg_replace('#\# POSTGRES (BEGIN|COMMIT) \##s', '\1; ', $sql_query);
			break;
		}

		// Change prefix
		$sql_query = preg_replace('#phpbb_#i', $table_prefix, $sql_query);

		// Change language strings...
		$sql_query = preg_replace_callback('#\{L_([A-Z0-9\-_]*)\}#s', 'adjust_language_keys_callback', $sql_query);

		// Since there is only one schema file we know the comment style and are able to remove it directly with remove_remarks
		remove_remarks($sql_query);
		$sql_query = split_sql_file($sql_query, ';');

		$sql_results = '';
		foreach ($sql_query as $sql)
		{
			if (!$db->sql_query($sql))
			{
				$error = $db->sql_error();
				$this->db_error($error['message'], $sql, __LINE__, __FILE__, true);
			}
			else
			{
				$sql_results .= preg_replace('/\t(AND|OR)(\W)/', "\$1\$2", htmlspecialchars(preg_replace('/[\s]*[\n\r\t]+[\n\r\s\t]*/', "\n", $sql))) . "\n\n";
			}
		}

		$template->assign_block_vars('checks', array(
			'S_LEGEND'	=> true,
		));

		$template->assign_block_vars('checks', array(
			'TITLE'		=> $user->lang['INST_SQL_RESULTS'],
			'RESULT'	=> '<textarea rows="10" cols="10">' . trim($sql_results) . '</textarea>',
		));

		unset($sql_query, $sql_results);
	}

	/**
	* Format version strings in correctly to compare
	*/
	function format_version($version)
	{
		$find = array('rc', ' ');
		$replace = array('RC', '.');

		return str_replace($find, $replace, strtolower($version));
	}

	/**
	* Set config value. Creates missing config entry.
	*/
	function set_config($config_name, $config_value)
	{
		global $db, $cache;

		$sql = 'UPDATE ' . TRACKER_CONFIG_TABLE . "
			SET config_value = '" . $db->sql_escape($config_value) . "'
			WHERE config_name = '" . $db->sql_escape($config_name) . "'";
		$db->sql_query($sql);

		if (!$db->sql_affectedrows())
		{
			$sql = 'INSERT INTO ' . TRACKER_CONFIG_TABLE . ' ' . $db->sql_build_array('INSERT', array(
				'config_name'	=> $config_name,
				'config_value'	=> $config_value));
			$db->sql_query($sql);
		}

		// Destroy the cache of the config
		// because the values have changed
		$cache->destroy('_tracker');
	}
	
		/**
	* Remove config value.
	*/
	function remove_config($config_name)
	{
		global $db, $cache;

		$sql = 'DELETE FROM ' . TRACKER_CONFIG_TABLE . "
			WHERE config_name = '" . $db->sql_escape($config_name) . "'";
		$db->sql_query($sql);

		// Destroy the cache of the config
		// because the table has changed
		$cache->destroy('_tracker');
	}

	/*
	* Borrowed from UMIL
	*/
	function multicall($function, $params)
	{
		if (is_array($params) && !empty($params))
		{
			foreach ($params as $param)
			{
				if (!is_array($param))
				{
					call_user_func(array($this, $function), $param);
				}
				else
				{
					call_user_func_array(array($this, $function), $param);
				}
			}
			return true;
		}
		return false;
	}

	/**
	* Cache purge
	* Borrowed from UMIL
	*/
	function cache_purge($type = '', $style_id = 0)
	{
		global $db, $auth, $cache, $phpbb_root_path, $phpEx;
		if ($this->multicall(__FUNCTION__, $type))
		{
			return;
		}
		$style_id = (int) $style_id;
		$type = (string) $type;
		switch ($type)
		{
			case 'auth' :
				$cache->destroy('_acl_options');
				$auth->acl_clear_prefetch();
				return;
			break;

			case 'imageset' :
				if ($style_id == 0)
				{
					$return = array();
					$sql = 'SELECT imageset_id
						FROM ' . STYLES_IMAGESET_TABLE;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						$return[] = $this->cache_purge('imageset', $row['imageset_id']);
					}
					$db->sql_freeresult($result);
					return implode('<br /><br />', $return);
				}
				else
				{
					$sql = 'SELECT *
						FROM ' . STYLES_IMAGESET_TABLE . '
						WHERE imageset_id = ' . (int) $style_id;
					$result = $db->sql_query($sql);
					$imageset_row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);
					if (!$imageset_row)
					{
						return;
					}
					$sql_ary = array();
					$cfg_data_imageset = parse_cfg_file("{$phpbb_root_path}styles/{$imageset_row['imageset_path']}/imageset/imageset.cfg");
					$sql = 'DELETE FROM ' . STYLES_IMAGESET_DATA_TABLE . '
						WHERE imageset_id = ' . (int) $style_id;
					$result = $db->sql_query($sql);
					foreach ($cfg_data_imageset as $image_name => $value)
					{
						if (strpos($value, '*') !== false)
						{
							if (substr($value, -1, 1) === '*')
							{
								list($image_filename, $image_height) = explode('*', $value);
								$image_width = 0;
							}
							else
							{
								list($image_filename, $image_height, $image_width) = explode('*', $value);
							}
						}
						else
						{
							$image_filename = $value;
							$image_height = $image_width = 0;
						}
						if (strpos($image_name, 'img_') === 0 && $image_filename)
						{
							$image_name = substr($image_name, 4);
							$sql_ary[] = array(
								'image_name'		=> (string) $image_name,
								'image_filename'	=> (string) $image_filename,
								'image_height'		=> (int) $image_height,
								'image_width'		=> (int) $image_width,
								'imageset_id'		=> (int) $style_id,
								'image_lang'		=> '',
							);
						}
					}
					$sql = 'SELECT lang_dir
						FROM ' . LANG_TABLE;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						if (@file_exists("{$phpbb_root_path}styles/{$imageset_row['imageset_path']}/imageset/{$row['lang_dir']}/imageset.cfg"))
						{
							$cfg_data_imageset_data = parse_cfg_file("{$phpbb_root_path}styles/{$imageset_row['imageset_path']}/imageset/{$row['lang_dir']}/imageset.cfg");
							foreach ($cfg_data_imageset_data as $image_name => $value)
							{
								if (strpos($value, '*') !== false)
								{
									if (substr($value, -1, 1) === '*')
									{
										list($image_filename, $image_height) = explode('*', $value);
										$image_width = 0;
									}
									else
									{
										list($image_filename, $image_height, $image_width) = explode('*', $value);
									}
								}
								else
								{
									$image_filename = $value;
									$image_height = $image_width = 0;
								}
								if (strpos($image_name, 'img_') === 0 && $image_filename)
								{
									$image_name = substr($image_name, 4);
									$sql_ary[] = array(
										'image_name'		=> (string) $image_name,
										'image_filename'	=> (string) $image_filename,
										'image_height'		=> (int) $image_height,
										'image_width'		=> (int) $image_width,
										'imageset_id'		=> (int) $style_id,
										'image_lang'		=> (string) $row['lang_dir'],
									);
								}
							}
						}
					}
					$db->sql_freeresult($result);
					$db->sql_multi_insert(STYLES_IMAGESET_DATA_TABLE, $sql_ary);
					$cache->destroy('sql', STYLES_IMAGESET_DATA_TABLE);
					return;
				}
			break;

			case 'template' :
				if ($style_id == 0)
				{
					$return = array();
					$sql = 'SELECT template_id
						FROM ' . STYLES_TEMPLATE_TABLE;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						$return[] = $this->cache_purge('template', $row['template_id']);
					}
					$db->sql_freeresult($result);
					return implode('<br /><br />', $return);
				}
				else
				{
					$sql = 'SELECT *
						FROM ' . STYLES_TEMPLATE_TABLE . '
						WHERE template_id =  ' . (int) $style_id;
					$result = $db->sql_query($sql);
					$template_row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);
					if (!$template_row)
					{
						return;
					}

					if ($template_row['template_storedb'] && file_exists("{$phpbb_root_path}styles/{$template_row['template_path']}/template/"))
					{
						$filelist = array('' => array());
						$sql = 'SELECT template_filename, template_mtime
							FROM ' . STYLES_TEMPLATE_DATA_TABLE . '
							WHERE template_id = ' . (int) $style_id;
						$result = $db->sql_query($sql);
						while ($row = $db->sql_fetchrow($result))
						{
								if (($slash_pos = strrpos($row['template_filename'], '/')) === false)
								{
									$filelist[''][] = $row['template_filename'];
								}
								else
								{
									$filelist[substr($row['template_filename'], 0, $slash_pos + 1)][] = substr($row['template_filename'], $slash_pos + 1, strlen($row['template_filename']) - $slash_pos - 1);
								}
						}
						$db->sql_freeresult($result);
						$includes = array();
						foreach ($filelist as $pathfile => $file_ary)
						{
							foreach ($file_ary as $file)
							{
								if (!($fp = @fopen("{$phpbb_root_path}styles/{$template_row['template_path']}$pathfile$file", 'r')))
								{
									return;
								}
								$template_data = fread($fp, filesize("{$phpbb_root_path}styles/{$template_row['template_path']}$pathfile$file"));
								fclose($fp);
								if (preg_match_all('#<!-- INCLUDE (.*?\.html) -->#is', $template_data, $matches))
								{
									foreach ($matches[1] as $match)
									{
										$includes[trim($match)][] = $file;
									}
								}
							}
						}
						foreach ($filelist as $pathfile => $file_ary)
						{
							foreach ($file_ary as $file)
							{
								if (strpos($file, 'index.') === 0)
								{
									continue;
								}
								$sql_ary = array(
									'template_id'			=> (int) $style_id,
									'template_filename'		=> "$pathfile$file",
									'template_included'		=> (isset($includes[$file])) ? implode(':', $includes[$file]) . ':' : '',
									'template_mtime'		=> (int) filemtime("{$phpbb_root_path}styles/{$template_row['template_path']}$pathfile$file"),
									'template_data'			=> (string) file_get_contents("{$phpbb_root_path}styles/{$template_row['template_path']}$pathfile$file"),
								);
								$sql = 'UPDATE ' . STYLES_TEMPLATE_DATA_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
									WHERE template_id = ' . (int) $style_id . "
										AND template_filename = '" . $db->sql_escape("$pathfile$file") . "'";
								$db->sql_query($sql);
							}
						}
						unset($filelist);
					}
					$cache->purge();
					return;
				}
			break;

			case 'theme' :
				if ($style_id == 0)
				{
					$return = array();
					$sql = 'SELECT theme_id
						FROM ' . STYLES_THEME_TABLE;
					$result = $db->sql_query($sql);
					while ($row = $db->sql_fetchrow($result))
					{
						$return[] = $this->cache_purge('theme', $row['theme_id']);
					}
					$db->sql_freeresult($result);
					return implode('<br /><br />', $return);
				}
				else
				{
					$sql = 'SELECT *
						FROM ' . STYLES_THEME_TABLE . '
						WHERE theme_id = ' . (int) $style_id;
					$result = $db->sql_query($sql);
					$theme_row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);
					if (!$theme_row)
					{
						return;
					}

					if ($theme_row['theme_storedb'] && file_exists("{$phpbb_root_path}styles/{$theme_row['theme_path']}/theme/stylesheet.css"))
					{
						$stylesheet = file_get_contents($phpbb_root_path . 'styles/' . $theme_row['theme_path'] . '/theme/stylesheet.css');
						$matches = array();
						preg_match_all('/@import url\(["\'](.*)["\']\);/i', $stylesheet, $matches);
						if (sizeof($matches))
						{
							foreach ($matches[0] as $idx => $match)
							{
								if (!file_exists("{$phpbb_root_path}styles/{$theme_row['theme_path']}/theme/{$matches[1][$idx]}"))
								{
									continue;
								}
								$content = trim(file_get_contents("{$phpbb_root_path}styles/{$theme_row['theme_path']}/theme/{$matches[1][$idx]}"));
								$stylesheet = str_replace($match, $content, $stylesheet);
							}
						}
						$db_theme_data = str_replace('./', 'styles/' . $theme_row['theme_path'] . '/theme/', $stylesheet);
						$sql_ary = array(
							'theme_mtime'	=> (int) filemtime("{$phpbb_root_path}styles/{$theme_row['theme_path']}/theme/stylesheet.css"),
							'theme_data'	=> $db_theme_data,
						);
						$sql = 'UPDATE ' . STYLES_THEME_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . "
							WHERE theme_id = $style_id";
						$db->sql_query($sql);
						$cache->destroy('sql', STYLES_THEME_TABLE);
					}
					return;
				}
			break;

			default:
				$cache->purge();
				return;
			break;
		}
	}
}
?>