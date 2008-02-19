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
* @package module_install
*/
class acp_tracker_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_tracker',
			'title'		=> 'ACP_TRACKER',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'settings'		=> array('title' => 'ACP_TRACKER_SETTINGS', 		'auth' => 'acl_a_tracker', 'cat' => array('ACP_TRACKER')),
				'attachments'	=> array('title' => 'ACP_TRACKER_ATTACHMENTS', 		'auth' => 'acl_a_tracker', 'cat' => array('ACP_TRACKER')),
				'project'		=> array('title' => 'ACP_TRACKER_PROJECT', 			'auth' => 'acl_a_tracker', 'cat' => array('ACP_TRACKER')),
				'component'		=> array('title' => 'ACP_TRACKER_COMPONENT', 		'auth' => 'acl_a_tracker', 'cat' => array('ACP_TRACKER')),
				'version'		=> array('title' => 'ACP_TRACKER_VERSION', 			'auth' => 'acl_a_tracker', 'cat' => array('ACP_TRACKER')),
				'severity'		=> array('title' => 'ACP_TRACKER_SEVERITY', 		'auth' => 'acl_a_tracker', 'cat' => array('ACP_TRACKER')),
				'priority'		=> array('title' => 'ACP_TRACKER_PRIORITY', 		'auth' => 'acl_a_tracker', 'cat' => array('ACP_TRACKER')),
			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>