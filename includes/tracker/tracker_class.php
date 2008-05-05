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

/**
* Class for display of the tracker
* @package tracker
*/
class tracker
{
	public $api;

	public function __construct($in_tracker = true)
	{
		global $template, $user;
		global $phpbb_root_path, $phpEx;

		// Do not change order of following includes
		include($phpbb_root_path . 'includes/tracker/tracker_api.' . $phpEx);
		include($phpbb_root_path . 'includes/tracker/tracker_cache.' . $phpEx);
		include($phpbb_root_path . 'includes/tracker/tracker_constants.' . $phpEx);
		include($phpbb_root_path . 'includes/tracker/tracker_status.' . $phpEx);
		include($phpbb_root_path . 'includes/tracker/tracker_types.' . $phpEx);

		$this->api = new tracker_api();

		// Add language vars to array
		$user->add_lang('mods/tracker');

		$template->assign_vars(array(
			'S_IN_TRACKER'				=> $in_tracker,
			'U_TRACKER' 				=> append_sid("{$phpbb_root_path}tracker.$phpEx"),
			'U_TRACKER_STATS'			=> append_sid("{$phpbb_root_path}tracker.$phpEx", 'mode=statistics'),
		));
	}
}

?>