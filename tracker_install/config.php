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
* Some config values so the script can be used for different mods.
* Currently only setup to add parent modules to .MODS tab in ACP 
* then add the mods modules to this new parent.
* EDIT VALUES BELOW
*/

$CFG['mod_title'] = 'phpBB Tracker';
$CFG['mod_version'] = '0.1.0';
$CFG['mod_copyright'] = 'Powered by phpBB Tracker 0.1.0 BETA &copy; 2008 <a href="http://www.jeffrusso.net">JRSweets</a><br />';
$CFG['mod_dir'] = 'tracker_install';
$CFG['clear_cache_install'] = true;
$CFG['clear_cache_uninstall'] = true;
$CFG['clear_cache_update'] = true;
$CFG['data_file'] = 'schemas/schema_data.sql';
$CFG['remove_data_file'] = 'schemas/remove_schema_data.sql';
$CFG['remove_permissions_file'] = 'schemas/remove_permissions_data.sql';
$CFG['parent_module_remove'] = array(
	0	=> 'ACP_TRACKER',
);
$CFG['module_remove'] = array(
	0	=> 'tracker',
	
);
$CFG['permission_options'] = (array(
	'local'		=> array(),    
	'global'	=> array('u_tracker_view', 'u_tracker_post', 'u_tracker_edit', 'u_tracker_attach', 'u_tracker_download', 'a_tracker'),
));

//ACP Modules
$CFG['mod_modules'][0]['parent_module_data'] = array(
	'module_basename' 	=> '', 
	'module_enabled'	=> '1', 
	'module_display' 	=> '1', 
	'parent_id' 		=> '0', 
	'module_class' 		=> 'acp', 
	'module_langname' 	=> 'ACP_TRACKER', 
	'module_mode' 		=> '', 
	'module_auth' 		=> '' 
);


//ACP Modules
$CFG['mod_modules'][0]['module_data'][0] = array(
	'module_basename'	=> 'tracker', 
	'module_enabled' 	=> '1', 
	'module_display' 	=> '1', 
	'module_class' 		=> 'acp', 
	'module_langname' 	=> 'ACP_TRACKER_SETTINGS', 
	'module_mode' 		=> 'settings', 
	'module_auth' 		=> 'acl_a_tracker'
);
$CFG['mod_modules'][0]['module_data'][1] = array(
	'module_basename'	=> 'tracker', 
	'module_enabled' 	=> '1', 
	'module_display' 	=> '1', 
	'module_class' 		=> 'acp', 
	'module_langname' 	=> 'ACP_TRACKER_ATTACHMENTS', 
	'module_mode' 		=> 'attachments', 
	'module_auth' 		=> 'acl_a_tracker'
);			
$CFG['mod_modules'][0]['module_data'][2] = array(
	'module_basename'	=> 'tracker', 
	'module_enabled' 	=> '1', 
	'module_display' 	=> '1', 
	'module_class' 		=> 'acp', 
	'module_langname' 	=> 'ACP_TRACKER_PROJECT', 
	'module_mode' 		=> 'project', 
	'module_auth' 		=> 'acl_a_tracker'
);
$CFG['mod_modules'][0]['module_data'][3] = array(
	'module_basename'	=> 'tracker', 
	'module_enabled' 	=> '1', 
	'module_display' 	=> '1', 
	'module_class' 		=> 'acp', 
	'module_langname' 	=> 'ACP_TRACKER_COMPONENT', 
	'module_mode' 		=> 'component', 
	'module_auth' 		=> 'acl_a_tracker'
);
$CFG['mod_modules'][0]['module_data'][4] = array(
	'module_basename'	=> 'tracker', 
	'module_enabled' 	=> '1', 
	'module_display' 	=> '1', 
	'module_class' 		=> 'acp', 
	'module_langname' 	=> 'ACP_TRACKER_VERSION', 
	'module_mode' 		=> 'version', 
	'module_auth' 		=> 'acl_a_tracker'
);
?>