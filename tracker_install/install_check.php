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
define('IN_PHPBB', 1);
$phpEx = substr(strrchr(__FILE__, '.'), 1);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
include($phpbb_root_path . 'common.'.$phpEx);

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

//Empty cache...
$cache->purge();

$tracker = new install_check();
$tracker->install_header("phpBB Tracker Installation Checking...");
$tracker->check_tables();
$tracker->check_alter_db();
$tracker->check_files();
$tracker->check_edits();
$tracker->check_modules();
$tracker->check_permissions();
$tracker->display_done();
$tracker->install_footer();
exit;

class install_check
{
	var $install_tables = array();
	var $install_files = array();
	var $install_edits = array();
	var $install_permissions = array();
	var $install_modules = array();
	var $install_alter_db = array();
	var $no_errors = true;
	
	function install_check()
	{
		global $phpbb_root_path, $phpEx, $user, $table_prefix;
		
		require($phpbb_root_path . 'includes/functions_install.' . $phpEx);
				
		define('TRACKER_CONFIG_TABLE',			$table_prefix . 'tracker_config');
		define('TRACKER_ATTACHMENTS_TABLE',		$table_prefix . 'tracker_attachments');
		define('TRACKER_PROJECT_TABLE',			$table_prefix . 'tracker_project');
		define('TRACKER_TICKETS_TABLE',			$table_prefix . 'tracker_tickets');
		define('TRACKER_POSTS_TABLE',			$table_prefix . 'tracker_posts');
		define('TRACKER_COMPONENTS_TABLE',		$table_prefix . 'tracker_components');
		define('TRACKER_HISTORY_TABLE', 		$table_prefix . 'tracker_history');
		define('TRACKER_VERSION_TABLE', 		$table_prefix . 'tracker_version');
		define('TRACKER_SEVERITY_TABLE', 		$table_prefix . 'tracker_severity');
		define('TRACKER_PRIORITY_TABLE', 		$table_prefix . 'tracker_priority');
		
		$this->install_tables = array(
			TRACKER_CONFIG_TABLE,
			TRACKER_ATTACHMENTS_TABLE,
			TRACKER_PROJECT_TABLE, 
			TRACKER_TICKETS_TABLE, 
			TRACKER_POSTS_TABLE, 
			TRACKER_COMPONENTS_TABLE, 
			TRACKER_HISTORY_TABLE, 
			TRACKER_VERSION_TABLE,
			TRACKER_SEVERITY_TABLE,
			TRACKER_PRIORITY_TABLE,			
		);
		
		$this->install_files = array(
			'tracker.php',
			'adm/style/acp_tracker.html',
			'includes/acp/acp_tracker.php',
			'includes/acp/info/acp_tracker.php',
			'includes/tracker/tracker_class.php',
			'includes/tracker/tracker_constants.php',
			'language/en/acp/permissions_tracker.php',
			'language/en/email/tracker_notify.txt',
			'language/en/email/tracker_notify_comment.txt',
			'language/en/email/tracker_notify_status_double.txt',
			'language/en/email/tracker_notify_status_single.txt',
			'language/en/mods/tracker.php',
			'language/en/mods/info_acp_tracker.php',
			'styles/prosilver/template/tracker/tracker_index_body.html',
			'styles/prosilver/template/tracker/tracker_tickets_add_body.html',
			'styles/prosilver/template/tracker/tracker_tickets_body.html',
			'styles/prosilver/template/tracker/tracker_tickets_view_body.html',
			'styles/prosilver/template/tracker/tracker_header.html',
			'styles/prosilver/template/tracker/tracker_move.html',
			'styles/subsilver2/template/tracker/tracker_breadcrumbs.html',
			'styles/subsilver2/template/tracker/tracker_index_body.html',
			'styles/subsilver2/template/tracker/tracker_tickets_add_body.html',
			'styles/subsilver2/template/tracker/tracker_tickets_body.html',
			'styles/subsilver2/template/tracker/tracker_tickets_view_body.html',
			'styles/subsilver2/template/tracker/tracker_header.html',
			'styles/subsilver2/template/tracker/tracker_move.html',
		);
		
		$this->install_edits['styles/prosilver/template/overall_header.html'] = array(
			'<!-- IF S_IN_TRACKER -->',
			'<li class="icon-home"><a href="{U_INDEX}" accesskey="h">{L_INDEX}</a> <strong>&#8249;</strong> <a href="{U_TRACKER}">{L_TRACKER}</a><!-- BEGIN navlinks --> <strong>&#8249;</strong> <a href="{navlinks.U_VIEW_FORUM}">{navlinks.FORUM_NAME}</a><!-- END navlinks --><!-- IF TRACKER_TICKET_ID --> <strong>&#8249;</strong> <a href="{U_VIEW_TRACKER_TICKET}">{L_TRACKER_NAV_TICKET}{TRACKER_TICKET_ID}</a><!-- ENDIF --></li>',
			'<!-- INCLUDE tracker/tracker_header.html -->',
		);
		
		$this->install_edits['styles/subsilver2/template/overall_header.html'] = array(
			'<!-- IF S_IN_TRACKER -->',
			'<!-- INCLUDE tracker/tracker_breadcrumbs.html -->',
			'<!-- INCLUDE tracker/tracker_header.html -->',
		);
		
		$this->install_permissions = array(
			'u_tracker_view', 
			'u_tracker_attach',
			'u_tracker_download',
			'u_tracker_post', 
			'u_tracker_edit', 
			'a_tracker',
		);
		
		$this->install_modules['acp'] = array(
			'tracker' 	=> array('settings', 'project', 'component', 'version', 'severity', 'priority'),
		);
		
		$this->install_modules['ucp'] = array(
			//'tracker' => array('settings'),
		);
		
		$this->install_alter_db = array(
			//USERS_TABLE		=> array('user_allow_pm'),
		);
		
	}
	
	function check_tables()
	{
		global $db, $table_prefix;
		
		echo '<h3>Checking if database tables exist...</h3>';	
		$error = array();
		$tables = get_tables($db);
		foreach ($this->install_tables as $table_name)
		{
			if (!in_array($table_name, $tables))
			{
				$error[] = $table_name;
			}
		}
		unset($tables);
		
		if (sizeof($error))
		{
			$this->no_errors = false;
			$this->display_error('The following tables were not found:', $error);
		}
		else
		{
			$this->display_success('All tables found');
		}
	}
	
	function check_alter_db()
	{
		global $phpbb_root_path, $db;
		
		$db->sql_return_on_error(true);
		
		echo '<h3>Checking other database data...</h3>';
		$error = array();		
		foreach ($this->install_alter_db as $key => $value)
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
					$error[] = $column;
				}
				unset($row);
			}
			
			if (sizeof($error))
			{
				$this->no_errors = false;
				$this->display_error('The following columns are missing from the ' . $table . ' table:', $error);
			}
			else
			{
				$this->display_success($table . ' table correctly altered');
			}
			unset($error);			
		}
		
		$db->sql_return_on_error(false);
	}
	
	function check_files()
	{
		global $phpbb_root_path;
		
		echo '<h3>Checking if files exist...</h3>';
		$error = array();
		foreach($this->install_files as $file)
		{
			if (!file_exists($phpbb_root_path . $file))
			{
				$error[] = 'phpbb_root_path/' . $file;
			}
		}

		if (sizeof($error))
		{
			$this->no_errors = false;
			$this->display_error('The following files were not found:', $error);
		}
		else
		{
			$this->display_success('All files found');
		}		
	}
	
	function check_edits()
	{
		global $phpbb_root_path;
		
		echo '<h3>Checking file edits...</h3>';
		$error = array();		
		foreach($this->install_edits as $key => $value)
		{	
			$file = $key;
			foreach ($value as $edit)
			{
				$content = file_get_contents($phpbb_root_path . $file);
				if (strpos($content, $edit) === false)
				{
					$error[] = 'phpbb_root_path/' . $file;
					break;
				}
			}
		}
		
		if (sizeof($error))
		{
			$this->no_errors = false;
			$this->display_error('The following files do not seem to be edited:', $error);
		}
		else
		{
			$this->display_success('All files edited');
		}			
	}
	
	function check_modules()
	{
		global $phpbb_root_path, $db;
		
		echo '<h3>Checking modules...</h3>';
		$error = array();		
		
		foreach($this->install_modules['acp'] as $key => $value)
		{	
			$module_basename = $key;
			foreach ($value as $module_mode)
			{
				$sql = 'SELECT parent_id
					FROM ' . MODULES_TABLE . '
					WHERE module_basename = "' . $module_basename . '"
						AND module_class = "acp"
						AND module_mode = "' . $module_mode . '"';
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
				
				if (!$row)
				{
					$error[] = 'ACP Module: module_basename = ' . $module_basename . ', module_mode = ' . $module_mode;
				}
				unset($row);
			}
		}
		
		foreach($this->install_modules['ucp'] as $key => $value)
		{	
			$module_basename = $key;
			foreach ($value as $module_mode)
			{
				$sql = 'SELECT parent_id
					FROM ' . MODULES_TABLE . '
					WHERE module_basename = "' . $module_basename . '" 
						AND module_class = "ucp"
						AND module_mode = "' . $module_mode . '"';
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
				
				if (!$row)
				{
					$error[] = 'UCP Module: module_basename = ' . $module_basename . ', module_mode = ' . $module_mode;
				}
				unset($row);
			}
		}
		
		if (sizeof($error))
		{
			$this->no_errors = false;
			$this->display_error('The following modules do not seem to be installed:', $error);
		}
		else
		{
			$this->display_success('All modules installed');
		}			
	}
	
	function check_permissions()
	{
		global $phpbb_root_path, $db;
		
		echo '<h3>Checking permissions data...</h3>';
		$error = array();		
		foreach ($this->install_permissions as $value)
		{
			$sql = 'SELECT auth_option_id
				FROM ' . ACL_OPTIONS_TABLE . '
				WHERE auth_option = "' . $value . '"';
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			if (!$row)
			{
				$error[] = $value;
			}
			unset($row);
		}
		
		if (sizeof($error))
		{
			$this->no_errors = false;
			$this->display_error('The following permissions do not exist:', $error);
		}
		else
		{
			$this->display_success('All permissions exist');
		}	
	}
	
	function display_success($text)
	{
		echo '<p style="color: green;"><b>' . $text . '</b></p>';
	}
	
	function display_error($title, $error)
	{
		echo '<p style="color: red; font-size: 1.05em; font-family: \'Trebuchet MS\', Helvetica, sans-serif;"><strong>'. $title .'</strong><br />' . implode('<br />', $error) . '</p>';
	}
	
	function display_done()
	{
		if ($this->no_errors)
		{
			echo '<h2 style="color: green;">There were no errors found with the installation.</h2>';
		}
		else
		{
			echo '<h2 style="color: red;">There were errors found with the installation.</h2>';
		}
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
						<div id="main"><h1>' . $title . '</h1>';

	}

	function install_footer()
	{
		
						echo '</div>
					</div>
				<span class="corners-bottom"><span></span></span>
			</div>
			</div>
		</div>
		
		<div id="page-footer">
			Powered by phpBB Tracker &copy; 2008 <a href="http://www.jeffrusso.net">JRSweets</a><br />
			Powered by phpBB &copy; 2000, 2002, 2005, 2007 <a href="http://www.phpbb.com/">phpBB Group</a>
			 
		</div>
	</div>

	</body>
	</html>';

		garbage_collection();
	}
}

?>
