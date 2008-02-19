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
* DO NOT REMOVE Status types TRACKER_ALL, 
* TRACKER_ALL_OPENED, TRACKER_ALL CLOSED, TRACKER_NEW
*/

$tracker_types = array (
  0 => 
  array (
    'id' => 'bug',
    'title' => 'BUG_TRACKER',
    'show_version' => true,
    'show_component' => true,
    'show_priority' => true,
    'show_severity' => true,
	'show_environment' => true,
    'ticket_security' => false,
    'status' => 
    array (
      0 => 
      array (
        'id' => 0,
        'name' => 'TRACKER_ALL',
        'filter' => true,
        'open' => true,
      ),
      1 => 
      array (
        'id' => 1,
        'name' => 'TRACKER_ALL_OPENED',
        'filter' => true,
        'open' => true,
      ),
      2 => 
      array (
        'id' => 2,
        'name' => 'TRACKER_ALL_CLOSED',
        'filter' => true,
        'open' => true,
      ),
      3 => 
      array (
        'id' => 3,
        'name' => 'TRACKER_NEW',
        'filter' => false,
        'open' => true,
      ),
      4 => 
      array (
        'id' => 4,
        'name' => 'TRACKER_NOT_A_BUG',
        'filter' => false,
        'open' => false,
      ),
      5 => 
      array (
        'id' => 5,
        'name' => 'TRACKER_SUPPORT_REQUEST',
        'filter' => false,
        'open' => false,
      ),
      6 => 
      array (
        'id' => 6,
        'name' => 'TRACKER_DUPLICATE',
        'filter' => false,
        'open' => false,
      ),
      7 => 
      array (
        'id' => 7,
        'name' => 'TRACKER_ALREADY_FIXED',
        'filter' => false,
        'open' => false,
      ),
      8 => 
      array (
        'id' => 8,
        'name' => 'TRACKER_REVIEWED',
        'filter' => false,
        'open' => true,
      ),
      9 => 
      array (
        'id' => 9,
        'name' => 'TRACKER_REVIEW_LATER',
        'filter' => false,
        'open' => true,
      ),
      10 => 
      array (
        'id' => 10,
        'name' => 'TRACKER_AWAITING_INFO',
        'filter' => false,
        'open' => true,
      ),
      11 => 
      array (
        'id' => 11,
        'name' => 'TRACKER_AWAITING_INPUT',
        'filter' => false,
        'open' => true,
      ),
      12 => 
      array (
        'id' => 12,
        'name' => 'TRACKER_PENDING',
        'filter' => false,
        'open' => true,
      ),
      13 => 
      array (
        'id' => 13,
        'name' => 'TRACKER_WILL_NOT_FIX',
        'filter' => false,
        'open' => false,
      ),
      14 => 
      array (
        'id' => 14,
        'name' => 'TRACKER_FIX_IN_PROGRESS',
        'filter' => false,
        'open' => true,
      ),
      15 => 
      array (
        'id' => 15,
        'name' => 'TRACKER_FIX_COMPLETED_CVS',
        'filter' => false,
        'open' => false,
      ),
      16 => 
      array (
        'id' => 16,
        'name' => 'TRACKER_FIX_COMPLETED_SVN',
        'filter' => false,
        'open' => false,
      ),
      17 => 
      array (
        'id' => 17,
        'name' => 'TRACKER_FIX_COMPLETED',
        'filter' => false,
        'open' => false,
      ),
      18 => 
      array (
        'id' => 18,
        'name' => 'TRACKER_UNREPRODUCABLE',
        'filter' => false,
        'open' => false,
      ),
    ),
  ),
  1 => 
  array (
    'id' => 'feature',
    'title' => 'FEATURE_TRACKER',
    'show_version' => true,
    'show_component' => true,
    'show_priority' => true,
    'show_severity' => false,
	'show_environment' => false,
    'ticket_security' => false,
    'status' => 
    array (
      0 => 
      array (
        'id' => 0,
        'name' => 'TRACKER_ALL',
        'filter' => true,
        'open' => true,
      ),
      1 => 
      array (
        'id' => 1,
        'name' => 'TRACKER_ALL_OPENED',
        'filter' => true,
        'open' => true,
      ),
      2 => 
      array (
        'id' => 2,
        'name' => 'TRACKER_ALL_CLOSED',
        'filter' => true,
        'open' => true,
      ),
      3 => 
      array (
        'id' => 3,
        'name' => 'TRACKER_NEW',
        'filter' => false,
        'open' => true,
      ),
      4 => 
      array (
        'id' => 4,
        'name' => 'TRACKER_NOT_A_BUG',
        'filter' => false,
        'open' => false,
      ),
      5 => 
      array (
        'id' => 5,
        'name' => 'TRACKER_SUPPORT_REQUEST',
        'filter' => false,
        'open' => false,
      ),
      6 => 
      array (
        'id' => 6,
        'name' => 'TRACKER_DUPLICATE',
        'filter' => false,
        'open' => false,
      ),
      7 => 
      array (
        'id' => 7,
        'name' => 'TRACKER_ALREADY_FIXED',
        'filter' => false,
        'open' => false,
      ),
      8 => 
      array (
        'id' => 8,
        'name' => 'TRACKER_REVIEWED',
        'filter' => false,
        'open' => true,
      ),
      9 => 
      array (
        'id' => 9,
        'name' => 'TRACKER_REVIEW_LATER',
        'filter' => false,
        'open' => true,
      ),
      10 => 
      array (
        'id' => 10,
        'name' => 'TRACKER_AWAITING_INFO',
        'filter' => false,
        'open' => true,
      ),
      11 => 
      array (
        'id' => 11,
        'name' => 'TRACKER_AWAITING_INPUT',
        'filter' => false,
        'open' => true,
      ),
      12 => 
      array (
        'id' => 12,
        'name' => 'TRACKER_PENDING',
        'filter' => false,
        'open' => true,
      ),
      13 => 
      array (
        'id' => 13,
        'name' => 'TRACKER_WILL_NOT_FIX',
        'filter' => false,
        'open' => false,
      ),
      14 => 
      array (
        'id' => 14,
        'name' => 'TRACKER_FIX_IN_PROGRESS',
        'filter' => false,
        'open' => true,
      ),
      15 => 
      array (
        'id' => 15,
        'name' => 'TRACKER_FIX_COMPLETED_CVS',
        'filter' => false,
        'open' => false,
      ),
    ),
  ),
  2 => 
  array (
    'id' => 'security',
    'title' => 'SECURITY_TRACKER',
    'show_version' => true,
    'show_component' => true,
    'show_priority' => true,
    'show_severity' => true,
	'show_environment' => true,	
    'ticket_security' => true,
    'status' => 
    array (
      0 => 
      array (
        'id' => 0,
        'name' => 'TRACKER_ALL',
        'filter' => true,
        'open' => true,
      ),
      1 => 
      array (
        'id' => 1,
        'name' => 'TRACKER_ALL_OPENED',
        'filter' => true,
        'open' => true,
      ),
      2 => 
      array (
        'id' => 2,
        'name' => 'TRACKER_ALL_CLOSED',
        'filter' => true,
        'open' => true,
      ),
      3 => 
      array (
        'id' => 3,
        'name' => 'TRACKER_NEW',
        'filter' => false,
        'open' => true,
      ),
      4 => 
      array (
        'id' => 4,
        'name' => 'TRACKER_NOT_A_BUG',
        'filter' => false,
        'open' => false,
      ),
      7 => 
      array (
        'id' => 7,
        'name' => 'TRACKER_ALREADY_FIXED',
        'filter' => false,
        'open' => false,
      ),
      8 => 
      array (
        'id' => 8,
        'name' => 'TRACKER_REVIEWED',
        'filter' => false,
        'open' => true,
      ),
      9 => 
      array (
        'id' => 9,
        'name' => 'TRACKER_REVIEW_LATER',
        'filter' => false,
        'open' => true,
      ),
      10 => 
      array (
        'id' => 10,
        'name' => 'TRACKER_AWAITING_INFO',
        'filter' => false,
        'open' => true,
      ),
      11 => 
      array (
        'id' => 11,
        'name' => 'TRACKER_AWAITING_INPUT',
        'filter' => false,
        'open' => true,
      ),
      12 => 
      array (
        'id' => 12,
        'name' => 'TRACKER_PENDING',
        'filter' => false,
        'open' => true,
      ),
      13 => 
      array (
        'id' => 13,
        'name' => 'TRACKER_WILL_NOT_FIX',
        'filter' => false,
        'open' => false,
      ),
      14 => 
      array (
        'id' => 14,
        'name' => 'TRACKER_FIX_IN_PROGRESS',
        'filter' => false,
        'open' => true,
      ),
      15 => 
      array (
        'id' => 15,
        'name' => 'TRACKER_FIX_COMPLETED_CVS',
        'filter' => false,
        'open' => false,
      ),
      16 => 
      array (
        'id' => 16,
        'name' => 'TRACKER_FIX_COMPLETED_SVN',
        'filter' => false,
        'open' => false,
      ),
      17 => 
      array (
        'id' => 17,
        'name' => 'TRACKER_FIX_COMPLETED',
        'filter' => false,
        'open' => false,
      ),
      18 => 
      array (
        'id' => 18,
        'name' => 'TRACKER_UNREPRODUCABLE',
        'filter' => false,
        'open' => false,
      ),
    ),
  ),
);

?>