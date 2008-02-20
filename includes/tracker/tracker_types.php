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
if (!defined('IN_PHPBB'))
{
	exit;
}

/*
* Tracker types defined below
*/

$tracker_types[0] = array(
    'id' 				=> 'bug',
    'title' 			=> 'BUG_TRACKER',
    'show_version' 		=> true,
    'show_component' 	=> true,
    'show_priority' 	=> true,
    'show_severity'		=> true,
	'show_environment' 	=> true,
    'status' 			=> $status_type[0],
);

$tracker_types[1] = array(
    'id' 				=> 'feature',
    'title' 			=> 'FEATURE_TRACKER',
    'show_version' 		=> true,
    'show_component'	=> true,
    'show_priority' 	=> true,
    'show_severity' 	=> false,
	'show_environment' 	=> false,
    'status' 			=> $status_type[1],
);

$tracker_types[2] = array(
    'id' 				=> 'issue',
    'title' 			=> 'ISSUE_TRACKER',
    'show_version' 		=> true,
    'show_component' 	=> true,
    'show_priority' 	=> true,
    'show_severity' 	=> true,
	'show_environment' 	=> true,	
    'status' 			=> $status_type[2],
);

?>