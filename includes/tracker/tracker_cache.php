<?php
/**
*
* @package tracker
* @version $Id: tracker_cache.php 114 2008-05-05 20:07:38Z evil3 $
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
* Class for grabbing/handling cached entries for the tracker, extends cache.php
* @package acm
*/
class tracker_cache extends cache
{
	/**
	* Obtain tracker config
	*/
	public function obtain_tracker_config()
	{
		if (($config = $this->get('_tracker')) === false)
		{
			global $db;

			// tracker Config
			$sql = 'SELECT *
				FROM ' . TRACKER_CONFIG_TABLE;
			$result = $db->sql_query($sql);

			$config = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$config[$row['config_name']] = $row['config_value'];
			}
			$db->sql_freeresult($result);

			$this->put('_tracker', $config);
		}

		return $config;
	}

	/**
	* Obtain tracker projects
	* The array returned is indexed by the project id
	* this allows you to simply use isset on the array
	* index to check if a project exists
	*/
	public function obtain_tracker_projects()
	{
		if (($projects = $this->get('_tracker_projects')) === false)
		{
			global $db;

			// tracker projects

			$sql_array = array(
				'SELECT'	=> 'p.*,
								g.group_name,
								g.group_colour',

				'FROM'		=> array(
					TRACKER_PROJECT_TABLE	=> 'p',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(GROUPS_TABLE => 'g'),
						'ON'	=> 'p.project_group = g.group_id',
					),
				),

				'ORDER_BY'	=> 'project_name_clean ASC, project_type ASC',
			);

			$sql = $db->sql_build_query('SELECT', $sql_array);
			$result = $db->sql_query($sql);

			$projects = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$projects[$row['project_id']] = array(
					'project_id'			=> $row['project_id'],
					'project_name'			=> $row['project_name'],
					'project_name_clean'	=> $row['project_name_clean'],
					'project_desc'			=> $row['project_desc'],
					'project_enabled'		=> $row['project_enabled'],
					'project_type'			=> $row['project_type'],
					'project_security'		=> $row['project_security'],
					'ticket_security'		=> $row['ticket_security'],
					'project_group'			=> $row['project_group'],
					'show_php'				=> $row['show_php'],
					'lang_php'				=> $row['lang_php'],
					'show_dbms'				=> $row['show_dbms'],
					'lang_dbms'				=> $row['lang_dbms'],
					'group_name'			=> $row['group_name'],
					'group_colour'			=> $row['group_colour'],
				);
			}
			$db->sql_freeresult($result);

			$this->put('_tracker_projects', $projects);
		}

		return $projects;
	}
}

?>