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
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

// Report all errors, except notices
error_reporting(E_ALL);
@set_time_limit(0);

// If we are on PHP >= 6.0.0 we do not need some code
if (version_compare(PHP_VERSION, '6.0.0-dev', '>='))
{
	/**
	* @ignore
	*/
	define('STRIP', false);
}
else
{
	set_magic_quotes_runtime(0);

	// Be paranoid with passed vars
	if (@ini_get('register_globals') == '1' || strtolower(@ini_get('register_globals')) == 'on' || !function_exists('ini_get'))
	{
		deregister_globals();
	}

	define('STRIP', (get_magic_quotes_gpc()) ? true : false);
}

if (!file_exists($phpbb_root_path . 'config.' . $phpEx))
{
	die("<p>The config.$phpEx file could not be found.</p><p><a href=\"{$phpbb_root_path}install/index.$phpEx\">Click here to install phpBB</a></p>");
}

require($phpbb_root_path . 'config.' . $phpEx);

if (!defined('PHPBB_INSTALLED'))
{
	// Redirect the user to the installer
	// We have to generate a full HTTP/1.1 header here since we can't guarantee to have any of the information
	// available as used by the redirect function
	$server_name = (!empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : getenv('SERVER_NAME');
	$server_port = (!empty($_SERVER['SERVER_PORT'])) ? (int) $_SERVER['SERVER_PORT'] : (int) getenv('SERVER_PORT');
	$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 1 : 0;

	$script_name = (!empty($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
	if (!$script_name)
	{
		$script_name = (!empty($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
	}

	// Replace any number of consecutive backslashes and/or slashes with a single slash
	// (could happen on some proxy setups and/or Windows servers)
	$script_path = trim(dirname($script_name)) . '/install/index.' . $phpEx;
	$script_path = preg_replace('#[\\\\/]{2,}#', '/', $script_path);

	$url = (($secure) ? 'https://' : 'http://') . $server_name;

	if ($server_port && (($secure && $server_port <> 443) || (!$secure && $server_port <> 80)))
	{
		$url .= ':' . $server_port;
	}

	$url .= $script_path;
	header('Location: ' . $url);
	exit;
}

// Load Extensions
if (!empty($load_extensions))
{
	$load_extensions = explode(',', $load_extensions);

	foreach ($load_extensions as $extension)
	{
		@dl(trim($extension));
	}
}

// Include files
require($phpbb_root_path . 'includes/acm/acm_' . $acm_type . '.' . $phpEx);
require($phpbb_root_path . 'includes/cache.' . $phpEx);
require($phpbb_root_path . 'includes/template.' . $phpEx);
require($phpbb_root_path . 'includes/session.' . $phpEx);
require($phpbb_root_path . 'includes/auth.' . $phpEx);

require($phpbb_root_path . 'includes/functions.' . $phpEx);
require($phpbb_root_path . 'includes/functions_content.' . $phpEx);

require($phpbb_root_path . 'includes/constants.' . $phpEx);
require($phpbb_root_path . 'includes/db/' . $dbms . '.' . $phpEx);
require($phpbb_root_path . 'includes/db/db_tools.' . $phpEx);
require($phpbb_root_path . 'includes/utf/utf_tools.' . $phpEx);

// Instantiate some basic classes
$user		= new user();
$auth		= new auth();
$template		= new template();
$cache		= new cache();
$db			= new $sql_db();

// Connect to DB
$db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, defined('PHPBB_DB_NEW_LINK') ? PHPBB_DB_NEW_LINK : false);

// We do not need this any longer, unset for safety purposes
unset($dbpasswd);

// Grab global variables, re-cache if necessary
$config = $cache->obtain_config();

// Add own hook handler
require($phpbb_root_path . 'includes/hooks/index.' . $phpEx);
$phpbb_hook = new phpbb_hook(array('exit_handler', 'phpbb_user_session_handler', 'append_sid', array('template', 'display')));

foreach ($cache->obtain_hooks() as $hook)
{
	@include($phpbb_root_path . 'includes/hooks/' . $hook . '.' . $phpEx);
}

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

// is the user logged in?
//This might be looked at later if people have problems, but I guess only the founder
//should be using ftp and installing scripts anyway.
if ($user->data['user_type'] != USER_FOUNDER)
{
	die('You are not authorized to use this script.');
}

$table_prefix = (!empty($table_prefix) ? $table_prefix : 'phpbb_');
require('config.' . $phpEx);
$mode = request_var('mode', '');
$db->sql_return_on_error(true);

$install_mod = new install_mod();
$phpbb_db_tools = new phpbb_db_tools($db);
$install_mod->install_header($CFG['mod_title']);

switch ($mode)
{
	case 'uninstall':	
		echo '<h1>' . $CFG['mod_title'] . ' Uninstallation</h1>';
		echo '<p>Table Prefix :: ' . $table_prefix . '<br />'; 
		echo 'Database Type :: ' . $db->sql_layer . '</p>'; 
		
		echo '<br /><h1>Removing mod database tables and data...</h1>';
		$install_mod->load_data($CFG['remove_data_file']);
		
		if (isset($CFG['remove_schema_changes']))
		{
			$phpbb_db_tools->perform_schema_changes($CFG['remove_schema_changes']);
		}
		
		echo '<br /><h1>Removing mod permissions...</h1>';
		// Ok tables have been built, let's fill in the basic information
		$install_mod->load_data($CFG['remove_permissions_file']);
		
		echo '<br /><h1>Removing Mod modules and clearing module cache...</h1>';		
		$install_mod->remove_modules($CFG['parent_module_remove'], $CFG['module_remove']);

		echo '<br /><h1>Clearing permission cache...</h1>';
		$install_mod->clear_permission_cache();
		
		if ($CFG['clear_cache_uninstall'])
		{
			echo '<br /><h1>Clearing cache...</h1>';
			$install_mod->clear_cache();
		}	
		
		echo '<br /><h1>' . $CFG['mod_title'] . ' has been uninstalled</h1><br />';
		echo '<h1>Remove ' . $CFG['mod_dir'] . ' directory from your server</h1>';
		
		$install_mod->install_footer();
		exit;		
	break;
	
	case 'install':
		echo '<h1>' . $CFG['mod_title'] . ' Installation</h1>';
		echo '<p>Table Prefix :: ' . $table_prefix . '<br />'; 
		echo 'Database Type :: ' . $db->sql_layer . '</p>'; 
		
		echo '<br /><h1>Creating Mod database tables...</h1>';
		$install_mod->load_tables();
		
		echo '<br /><h1>Inserting Mod data...</h1>';		
		// Ok tables have been built, let's fill in the basic information
		$install_mod->load_data($CFG['data_file']);
		if (isset($CFG['schema_changes']))
		{
			$phpbb_db_tools->perform_schema_changes($CFG['schema_changes']);
		}
	
		echo '<br /><h1>Inserting Mod permissions and clearing permission cache...</h1>';	
		// Add permissions
		$install_mod->add_permissions($CFG['permission_options']);
		
		echo '<br /><h1>Inserting Mod modules and clearing module cache...</h1>';
		//Create Mod Modules...
		foreach($CFG['mod_modules'] as $modules)
		{
			$install_mod->create_modules($modules['parent_module_data'], $modules['module_data']);
		}
		
		if ($CFG['clear_cache_install'])
		{
			echo '<br /><h1>Clearing cache...</h1>';
			$install_mod->clear_cache();
		}
		
		echo '<br /><h1> ' . $CFG['mod_title'] . ' has been installed</h1><br />';
		echo '<h1>Remove ' . $CFG['mod_dir'] . ' directory from your server</h1><br />';		
				
		$install_mod->install_footer();				
		exit;
	break;
	
	/*case 'update':
		require($phpbb_root_path . 'includes/tracker/tracker_class.' . $phpEx);
		
		if ($CFG['clear_cache_update'])
		{
			echo '<br /><h1>Clearing cache...</h1>';
			$install_mod->clear_cache();
		}
		
		$tracker = new tracker();				
		if (version_compare($install_mod->format_version($tracker->config['version']), $install_mod->format_version($CFG['mod_version']), '<'))
		{
			switch ($tracker->config['version'])
			{
				case '0.1.0':
					echo '<br /><h1>Updating database from version 0.1.0 to 0.2.0...</h1>';
				break;				
			
				default:
				break;
			}
			
			//Set arcade version config value to latest version
			$arcade->set_config('version', $CFG['mod_version']);			
			
			if ($CFG['clear_cache_update'])
			{
				echo '<br /><h1>Clearing cache...</h1>';
				$install_mod->clear_cache();
			}
			
			echo '<br /><h1> ' . $CFG['mod_title'] . ' has been updated to the latest version</h1><br />';
			echo '<h1>Remove ' . $CFG['mod_dir'] . ' directory from your server</h1><br />';
		}
		else
		{		
			echo '<br /><h1> The latest version of ' . $CFG['mod_title'] . ' is already installed.</h1><br />';
			echo '<h1>Remove ' . $CFG['mod_dir'] . ' directory from your server</h1><br />';
		}
		
		$install_mod->install_footer();				
		exit;
	break;*/

	default:
	break;
}

$install_mod->install_form();
$install_mod->install_footer();
exit;

/*
* Mod install class
*/
class install_mod
{
	function install_mod()
	{
		global $phpbb_root_path, $phpEx, $user;
		
		require($phpbb_root_path . 'includes/functions_module.' . $phpEx);
		require($phpbb_root_path . 'includes/acp/acp_modules.' . $phpEx);
		require($phpbb_root_path . 'includes/acp/auth.' . $phpEx);
		require($phpbb_root_path . 'includes/functions_install.' . $phpEx);
		
		$user->add_lang('acp/modules');

		$module_info = new p_master();
		$module_info->add_mod_info('acp');
		$module_info->add_mod_info('mcp');
		$module_info->add_mod_info('ucp');
	}
	
	function install_header($title, $dir = '', $lang = '')
	{
		global $phpbb_root_path;
		
	header('Content-type: text/html; charset=UTF-8');
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml" dir="' . $dir . '" lang="' . $lang . '" xml:lang="' . $lang . '">
	<head>

	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta http-equiv="content-language" content="' . $lang . '" />
	<meta http-equiv="content-style-type" content="text/css" />
	<meta http-equiv="imagetoolbar" content="no" />
	
	<style type="text/css" media="screen">		
		blockquote {
			background: #ebebeb none 6px 8px no-repeat;
			border: 1px solid #dbdbdb;
			font-size: 0.95em;
			margin: 0.5em 1px 0 25px;
			overflow: hidden;
			padding: 5px;
			background-color: #EBEADD;
			border-color:#DBDBCE;
		}
	</style>

	<title>' . $title . '</title>

	<link href="' . $phpbb_root_path . 'adm/style/admin.css" rel="stylesheet" type="text/css" media="screen" />
	
	<script type="text/javascript">
	<!--
	function togglemenu(id)
	{
		if (document.getElementById(id))
		{
			if (document.getElementById(id).style.display == \'none\')
			{
				document.getElementById(id).style.display = \'inline\';
			}
			else
			{
				document.getElementById(id).style.display = \'none\';
			}
		}
	}
	-->
	</script>

	</head>

	<body>
	<div id="wrap">
		<div id="page-header">&nbsp;</div>

		<div id="page-body">
			<div id="acp">
			<div class="panel">
				<span class="corners-top"><span></span></span>
					<div id="content">
						<div id="main">';

	}
	
	function install_form()
	{
		global $CFG;
		
		echo '<h1>' . $CFG['mod_title'] . ' Installation Options</h1>';

		//<option value="update">Update to latest version of ' . $CFG['mod_title'] . '</option>
		
		echo '	<form action="' . $_SERVER['PHP_SELF'] . '" method="post">
				<p>This script will install, uninstall or upgrade the tables for the ' . $CFG['mod_title'] . '.<br /><a href="install_check.php">Check Installation</a></p>
				<p><b>Backup data tables before going on!</b></p>
				<p><select name="mode">
					<option value="">---- Install Options ----</option>
					<option value="install">First Time Install of ' . $CFG['mod_title'] . ' ' . $CFG['mod_version'] . '</option>
					<option value="">---- Uninstall Options ----</option>
					<option value="uninstall">Uninstall ' . $CFG['mod_title'] . '</option>
					</select></p> 
				<p><input type="submit" value="Submit" class="button2"></p>
				<p><b>Once you have finished with this script, delete it from your server!</b></p>
			</form>';
	}

	function install_footer()
	{
		global $CFG;
		
						echo '</div>
					</div>
				<span class="corners-bottom"><span></span></span>
			</div>
			</div>
		</div>
		
		<div id="page-footer">
			' . $CFG['mod_copyright'] . '
			Powered by phpBB &copy; 2000, 2002, 2005, 2007 <a href="http://www.phpbb.com/">phpBB Group</a>
			 
		</div>
	</div>

	</body>
	</html>';

		garbage_collection();
	}
	
	function clear_cache()
	{
		global $cache;
		
		$cache->purge();
	}
	
	function clear_permission_cache()
	{
		global $cache;
		
		$auth_admin = new auth_admin();
		$cache->destroy('_acl_options');
		$auth_admin->acl_clear_prefetch();
	}

	function add_permissions($options)
	{
		global $cache;
		
		$auth_admin = new auth_admin();
		$auth_admin->acl_add_option($options);		
	}
	
	function db_error($error, $sql, $line, $file)
	{
		global $db;

		echo '<p style="color: red;">' . basename($file) . ' [ ' . $line . ' ]</p><p style="color: red;">SQL : <textarea style="font-family:\'Courier New\',monospace;width:99%" rows="5" cols="10">' . preg_replace('/\t(AND|OR)(\W)/', "\$1\$2", htmlspecialchars(preg_replace('/[\s]*[\n\r\t]+[\n\r\s\t]*/', "\n", $sql))) . '</textarea></p><p style="color: red;"><b>' . $error . '</b></p>';
		// Rollback if in transaction
		if ($db->transaction)
		{
			$db->sql_transaction('rollback');
		}
	}
	
	function create_modules($parent_module_data, $module_data)
	{
		global $phpbb_root_path, $phpEx, $db;
		$_module = &new acp_modules();
		$_module->module_class = $parent_module_data['module_class'];
		
		//If the module class is acp we add it to the MODS tab in the ACP
		if ($parent_module_data['module_class'] == 'acp')
		{
			$sql = 'SELECT module_id
				FROM ' . MODULES_TABLE . '
				WHERE module_langname = "ACP_CAT_DOT_MODS"';
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			$parent_module_data['parent_id'] = $row['module_id'];
		}
		
		// Add category
		$_module->update_module_data($parent_module_data, true);
		$_module->remove_cache_file();	

		// Check for last sql error happened
		if ($db->sql_error_triggered)
		{
			$error = $db->sql_error($db->sql_error_sql);
			$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
		}
		else
		{	
			add_log('admin', 'LOG_MODULE_ADD', $_module->lang_name($parent_module_data['module_langname']));
		}
		
		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . '
			WHERE module_langname = "' . $parent_module_data['module_langname'] . '"
				AND module_class = "' . $parent_module_data['module_class'] .'"';
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
	
	function update_modules($parent_module_langname, $parent_module_class, $module_data)
	{
		global $phpbb_root_path, $phpEx, $db;
		$_module = &new acp_modules();
		$_module->module_class = $parent_module_class;
		
		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . '
			WHERE module_langname = "' . $parent_module_langname . '"
				AND module_class = "' . $parent_module_class .'"';
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
	
	function remove_modules($parent_module_data, $module_data)
	{
		global $db;
		$_module = &new acp_modules();
		
		if (!empty($module_data))
		{
			$sql = 'SELECT module_id, module_class
				FROM ' . MODULES_TABLE . '
				WHERE ' . $db->sql_in_set('module_basename', $module_data);
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$_module->module_class = $row['module_class'];
				$_module->delete_module($row['module_id']);
				// Check for last sql error happened
				if ($db->sql_error_triggered)
				{
					$error = $db->sql_error($db->sql_error_sql);
					$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
				}	
			}
			$db->sql_freeresult($result);
		}
		
		if (!empty($parent_module_data))
		{
			$sql = 'SELECT module_id, module_class
				FROM ' . MODULES_TABLE . '
				WHERE ' . $db->sql_in_set('module_langname', $parent_module_data);
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				$_module->module_class = $row['module_class'];
				$_module->delete_module($row['module_id']);
				// Check for last sql error happened
				if ($db->sql_error_triggered)
				{
					$error = $db->sql_error($db->sql_error_sql);
					$this->db_error($error['message'], $db->sql_error_sql, __LINE__, __FILE__);
				}	
			}
			$db->sql_freeresult($result);
		}
		
		return;
	}
	
	function load_tables()
	{
		global $db, $dbms, $table_prefix;
		
		$available_dbms = get_available_dbms($dbms);
		// If mysql is chosen, we need to adjust the schema filename slightly to reflect the correct version. ;)
		if ($dbms == 'mysql')
		{
			if (version_compare($db->mysql_version, '4.1.3', '>='))
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
		$dbms_schema = 'schemas/' . $available_dbms[$dbms]['SCHEMA'] . '_schema.sql';

		// How should we treat this schema?
		$remove_remarks = $available_dbms[$dbms]['COMMENTS'];
		$delimiter = $available_dbms[$dbms]['DELIM'];

		$sql_query = @file_get_contents($dbms_schema);

		$sql_query = preg_replace('#phpbb_#i', $table_prefix, $sql_query);

		$remove_remarks($sql_query);

		$sql_query = split_sql_file($sql_query, $delimiter);

		echo '<blockquote>';
		echo '	<div style="cursor:pointer;cursor:hand;" onclick="if (this.getElementsByTagName(\'div\')[0].style.display != \'block\') { this.getElementsByTagName(\'div\')[0].style.display = \'block\';} else { this.getElementsByTagName(\'div\')[0].style.display = \'none\'; }">';
		echo '		<cite>Results: (click to show/hide)</cite>';
		echo '		<div style="display: none;"><br />';			
		foreach ($sql_query as $sql)
		{
			//$sql = trim(str_replace('|', ';', $sql));
			if (!$db->sql_query($sql))
			{
				$error = $db->sql_error();
				$this->db_error($error['message'], $sql, __LINE__, __FILE__);
			}
			else
			{
				echo '<p style="color: green;"><textarea style="font-family:\'Courier New\',monospace;width:99%" rows="5" cols="10">' . preg_replace('/\t(AND|OR)(\W)/', "\$1\$2", htmlspecialchars(preg_replace('/[\s]*[\n\r\t]+[\n\r\s\t]*/', "\n", $sql))) . '</textarea></p>';
			}
		}
		echo '		</div>';
		echo '	</div>';
		echo '</blockquote>';
		unset($sql_query);
	}

	function load_data($file)
	{
		global $db, $dbms, $table_prefix;
		
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

		echo '<blockquote>';
		echo '	<div style="cursor:pointer;cursor:hand;" onclick="if (this.getElementsByTagName(\'div\')[0].style.display != \'block\') { this.getElementsByTagName(\'div\')[0].style.display = \'block\';} else { this.getElementsByTagName(\'div\')[0].style.display = \'none\'; }">';
		echo '		<cite>Results: (click to show/hide)</cite>';
		echo '		<div style="display: none;"><br />';
		foreach ($sql_query as $sql)
		{
			//$sql = trim(str_replace('|', ';', $sql));
			if (!$db->sql_query($sql))
			{
				$error = $db->sql_error();
				$this->db_error($error['message'], $sql, __LINE__, __FILE__);
			}
			else
			{
				echo '<p style="color: green;"><textarea style="font-family:\'Courier New\',monospace;width:99%" rows="5" cols="10">' . preg_replace('/\t(AND|OR)(\W)/', "\$1\$2", htmlspecialchars(preg_replace('/[\s]*[\n\r\t]+[\n\r\s\t]*/', "\n", $sql))) . '</textarea></p>';
			}
		}
		echo '		</div>';
		echo '	</div>';
		echo '</blockquote>';
		unset($sql_query);
	}
	
	function format_version($version)
	{
		return str_replace(' ', '.', $version);
	}
}

/*
* Remove variables created by register_globals from the global scope
* Thanks to Matt Kavanagh
*/
function deregister_globals()
{
	$not_unset = array(
		'GLOBALS'	=> true,
		'_GET'		=> true,
		'_POST'		=> true,
		'_COOKIE'	=> true,
		'_REQUEST'	=> true,
		'_SERVER'	=> true,
		'_SESSION'	=> true,
		'_ENV'		=> true,
		'_FILES'	=> true,
		'phpEx'		=> true,
		'phpbb_root_path'	=> true
	);

	// Not only will array_merge and array_keys give a warning if
	// a parameter is not an array, array_merge will actually fail.
	// So we check if _SESSION has been initialised.
	if (!isset($_SESSION) || !is_array($_SESSION))
	{
		$_SESSION = array();
	}

	// Merge all into one extremely huge array; unset this later
	$input = array_merge(
		array_keys($_GET),
		array_keys($_POST),
		array_keys($_COOKIE),
		array_keys($_SERVER),
		array_keys($_SESSION),
		array_keys($_ENV),
		array_keys($_FILES)
	);

	foreach ($input as $varname)
	{
		if (isset($not_unset[$varname]))
		{
			// Hacking attempt. No point in continuing unless it's a COOKIE
			if ($varname !== 'GLOBALS' || isset($_GET['GLOBALS']) || isset($_POST['GLOBALS']) || isset($_SERVER['GLOBALS']) || isset($_SESSION['GLOBALS']) || isset($_ENV['GLOBALS']) || isset($_FILES['GLOBALS']))
			{
				exit;
			}
			else
			{
				$cookie = &$_COOKIE;
				while (isset($cookie['GLOBALS']))
				{
					foreach ($cookie['GLOBALS'] as $registered_var => $value)
					{
						if (!isset($not_unset[$registered_var]))
						{
							unset($GLOBALS[$registered_var]);
						}
					}
					$cookie = &$cookie['GLOBALS'];
				}
			}
		}

		unset($GLOBALS[$varname]);
	}

	unset($input);
}
?>