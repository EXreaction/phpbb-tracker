<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: functions_install.php 132 2008-05-18 00:53:30Z evil3 $
* @copyright (c) 2007, 2008 phpbbmodders
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
 * Adds user-defined module to ACP (or MCP, etc). Useful for install scripts
 * John Wells, based on original phpBB code in acp_modules.
 *
 * @param array $module_data -- array containing module_basename, module_mode, module_auth, module_enabled, module_display, parent_id, module_langname and module_class
 * @param array $error Store all errors in there
 * @return mixed module id
 */
function add_module(&$module_data, &$error)
{
	global $phpbb_root_path, $phpEx;

	// better than include_once
	if (!class_exists('acp_modules'))
	{
		include($phpbb_root_path . 'includes/acp/acp_modules.' . $phpEx);
	}

	$_module = &new acp_modules();
	$_module->module_class = $module_data['module_class'];

	$module_id = module_exists($module_data['module_langname'], $module_data['parent_id']);

	if ($module_id)
	{
		$module_data['module_id'] = $module_id;
	}

	// Adjust auth row if not category
	if ($module_data['module_basename'] && $module_data['module_mode'])
	{
		$fileinfo = $_module->get_module_infos($module_data['module_basename']);
		$module_data['module_auth'] = $fileinfo[$module_data['module_basename']]['modes'][$module_data['module_mode']]['auth'];
	}

	$error = $_module->update_module_data($module_data, true);

	$_module->remove_cache_file();

	if (sizeof($error))
	{
		return false;
	}

	return $module_data['module_id'];
}

/**
 * Determines if a module already exists, and returns the module ID if it does.
 * More than one module with the same name and parent could exist, but this function just returns the first one it finds.
 * The alternatives are to delete duplicates, or throw up an error, neither of which is really better behaviour.
 * John Wells
 *
 * @param string $module_name -- module name (or language key)
 * @param integer $parent -- the id of the parent entity
 * @return mixed module_exists
 */
function module_exists($module_name, $parent = 0)
{
	global $db;

	$sql = 'SELECT module_id FROM ' . MODULES_TABLE . '
		WHERE parent_id = ' . intval($parent) . '
			AND module_langname = \'' . $db->sql_escape($module_name) . '\'';
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	// there could be a duplicate module, but screw it
	if (!$row || empty($row['module_id']))
	{
		return false;
	}

	return $row['module_id'];
}

/**
 * Install a single module into existing categories
 * and create categories if they don't exist
 *
 * @param string $module_class The module class, like acp/mcp/ucp
 * @param string $module_name The modules filename minus extension and class_
 * @param array $error Passed by reference array for errors
 * @param mixed $main_category Only used for ACP if there's a category above the one stored in the file
 * @return mixed module_ids Array of module ids added
 */
function install_module($module_class, $module_name, &$error, $main_category = false)
{
	global $phpbb_root_path, $phpEx;

	$class_name = $module_name . '_info';
	$module_filename = "{$phpbb_root_path}includes/$module_class/info/$module_name.$phpEx";

	if (!class_exists($class_name))
	{
		include($module_filename);
	}

	$module_info = call_user_func(array($class_name, 'module'));

	$module_ids = array();

	if ($main_category)
	{
		if (!$module_main_cat = module_exists($main_category))
		{
			$module_data = array(
				'module_basename'	=> '',
				'module_mode'		=> '',
				'module_auth'		=> '',
				'module_enabled'	=> 1,
				'module_display'	=> 1,
				'parent_id'			=> 0,
				'module_langname'	=> $main_category,
				'module_class'		=> $module_class,
			);

			$module_main_cat = add_module($module_data, $error);
		}
	}

	foreach ($module_info['modes'] as $mode => $mode_data)
	{
		foreach ($mode_data['cat'] as $category)
		{
			if (!$module_cat = module_exists($category))
			{
				$module_data = array(
					'module_basename'	=> '',
					'module_mode'		=> '',
					'module_auth'		=> '',
					'module_enabled'	=> 1,
					'module_display'	=> 1,
					'parent_id'			=> ($main_category) ? $module_main_cat : 0,
					'module_langname'	=> $category,
					'module_class'		=> $module_class,
				);

				$module_cat = add_module($module_data, $error);
			}

			$module_data = array(
				'module_basename'	=> str_replace("{$module_class}_", '', $module_info['filename']),
				'module_mode'		=> $mode,
				'module_auth'		=> $mode_data['auth'],
				'module_enabled'	=> 1,
				'module_display'	=> 1,
				'parent_id'			=> $module_cat,
				'module_langname'	=> $mode_data['title'],
				'module_class'		=> $module_class,
			);

			$module_ids[] = add_module($module_data, $error);
		}
	}

	return $module_ids;
}

/**
 * simplification of auth_admin::acl_add()
 *
 * @param array $local_perms
 * @param array $global_perms
 */
function add_permissions($local_perms = array(), $global_perms = array())
{
	static $auth_admin = false;

	if (!$auth_admin)
	{
		// if it's not set, include auth_admin and create an object
		if (!class_exists('auth_admin'))
		{
			global $phpbb_root_path, $phpEx;
			include($phpbb_root_path . 'includes/acp/auth.' . $phpEx);
		}
		$auth_admin = new auth_admin();
	}

	// now let's add 'em
	$auth_admin->acl_add_option(array(
		'local'		=> (!empty($local_perms) && is_array($local_perms)) ? $local_perms : array(),
		'global'	=> (!empty($global_perms) && is_array($global_perms)) ? $global_perms : array(),
	));
}

/**
 * Clean up after installing something
 */
function phpbb_cleanup()
{
	global $cache, $auth;

	$cache->purge();
	$auth->acl_clear_prefetch();
}

/**
 * Load a schema (and execute)
 *
 * @param string $install_path Path to folder containing schema files
 * @param mixed $install_dbms Alternative database system than $dbms
 */
function load_schema($install_path = '', $install_dbms = false)
{
	global $db;
	global $table_prefix;

	if ($install_dbms === false)
	{
		global $dbms;
		$install_dbms = $dbms;
	}

	static $available_dbms = false;

	if (!$available_dbms)
	{
		if (!function_exists('get_available_dbms'))
		{
			global $phpbb_root_path, $phpEx;
			include($phpbb_root_path . 'includes/functions_install.' . $phpEx);
		}

		$available_dbms = get_available_dbms($install_dbms);

		if ($install_dbms == 'mysql')
		{
			if (version_compare($db->mysql_version, '4.1.3', '>='))
			{
				$available_dbms[$install_dbms]['SCHEMA'] .= '_41';
			}
			else
			{
				$available_dbms[$install_dbms]['SCHEMA'] .= '_40';
			}
		}
	}

	$remove_remarks = $available_dbms[$install_dbms]['COMMENTS'];
	$delimiter = $available_dbms[$install_dbms]['DELIM'];

	$dbms_schema = $install_path . $available_dbms[$install_dbms]['SCHEMA'] . '_schema.sql';

	if (file_exists($dbms_schema))
	{
		$sql_query = @file_get_contents($dbms_schema);
		$sql_query = preg_replace('#phpbb_#i', $table_prefix, $sql_query);

		$remove_remarks($sql_query);

		$sql_query = split_sql_file($sql_query, $delimiter);

		foreach ($sql_query as $sql)
		{
			$db->sql_query($sql);
		}
		unset($sql_query);
	}

	if (file_exists($install_path . 'schema_data.sql'))
	{
		$sql_query = file_get_contents($install_path . 'schema_data.sql');

		switch ($install_dbms)
		{
			case 'mssql':
			case 'mssql_odbc':
				$sql_query = preg_replace('#\# MSSQL IDENTITY (phpbb_[a-z_]+) (ON|OFF) \##s', 'SET IDENTITY_INSERT \1 \2;', $sql_query);
			break;

			case 'postgres':
				$sql_query = preg_replace('#\# POSTGRES (BEGIN|COMMIT) \##s', '\1; ', $sql_query);
			break;
		}

		$sql_query = preg_replace('#phpbb_#i', $table_prefix, $sql_query);
		$sql_query = preg_replace_callback('#\{L_([A-Z0-9\-_]*)\}#s', 'adjust_language_keys_callback', $sql_query);

		remove_remarks($sql_query);

		$sql_query = split_sql_file($sql_query, ';');

		foreach ($sql_query as $sql)
		{
			$db->sql_query($sql);
		}
		unset($sql_query);
	}
}

/**
 * Automatically process sql, adding config values,
 * adding modules, performing schema changes
 *
 * @param array $install_ary
 * @param array $error
 */
function process_install($install_ary, &$error)
{
	global $db;
	static $db_tools = false;

	if (!$db_tools)
	{
		if (!class_exists('phpbb_db_tools'))
		{
			global $phpbb_root_path, $phpEx;
			include($phpbb_root_path . 'includes/db/db_tools.' . $phpEx);
		}

		// create the db_tools object
		$db_tools = new phpbb_db_tools($db);
	}

	// initialisation
	foreach (array('sql', 'config', 'modules', 'schema_changes') as $key)
	{
		if (!isset($install_ary[$key]))
		{
			$install_ary[$key] = array();
		}
	}

	// queries
	foreach ($install_ary['sql'] as $sql)
	{
		$db->sql_query($sql);
	}

	// config entries
	foreach ($install_ary['config'] as $config_name => $config_value)
	{
		set_config($config_name, $config_value);
	}

	// modules
	foreach ($install_ary['modules'] as $module_class => $modules)
	{
		foreach ($modules as $module)
		{
			install_module($module_class, $module, $error);
		}
	}

	foreach ($install_ary['schema_changes'] as $type => $changes)
	{
		switch ($type)
		{
			case 'sql_column_add':
			case 'sql_column_change':
			case 'sql_column_remove':
			case 'sql_create_index':
			case 'sql_create_primary_key':
			case 'sql_create_unique_index':
			case 'sql_index_drop':

				foreach ($changes as $change_data)
				{
					call_user_func_array(array($db_tools, $type), $change_data);
				}

				break;
		}
	}
}

?>