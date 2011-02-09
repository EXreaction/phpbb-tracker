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
* API for tracker
* @package tracker
*/
class tracker_api
{
	public $config				= array();
	public $projects			= array();
	public $types				= array();
	public $status				= array();

	// Attachments
	public $attachment_data 		= array();
	public $attachment_data_post 	= array();
	public $attachment_data_ticket 	= array();
	public $filename_data 			= array();
	public $warn_msg 				= array();

	public $severity	= array(
		1	=> 'TRACKER_SEVERITY1',
		2	=> 'TRACKER_SEVERITY2',
		3	=> 'TRACKER_SEVERITY3',
		4	=> 'TRACKER_SEVERITY4',
		5	=> 'TRACKER_SEVERITY5',
	);
	public $priority	= array(
		1	=> 'TRACKER_PRIORITY1',
		2	=> 'TRACKER_PRIORITY2',
		3	=> 'TRACKER_PRIORITY3',
		4	=> 'TRACKER_PRIORITY4',
		5	=> 'TRACKER_PRIORITY5',
	);

	public $cache;

	public $can_manage = false;

	protected $url_builder = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $phpbb_root_path, $phpEx;

		$this->cache = new tracker_cache();

		$this->config		= $this->cache->obtain_tracker_config();
		$this->project_cats = $this->cache->obtain_tracker_project_cats();
		$this->projects		= $this->cache->obtain_tracker_projects();
		$this->types 		= include($phpbb_root_path . 'tracker/includes/types.' . $phpEx);
	}

	/**
	 * Set an URL builder function
	 */
	public function set_url_builder($callback)
	{
		$this->url_builder = $callback;
	}

	public function build_url($mode = NULL, $args = NULL)
	{
		if (!$this->url_builder)
		{
			trigger_error('NO_URL_BUILDER');
		}

		return call_user_func($this->url_builder, $mode, $args);
	}

	/**
	* Set config value. Creates missing config entry.
	*/
	public function set_config($config_name, $config_value)
	{
		global $db, $cache;

		$sql = 'UPDATE ' . TRACKER_CONFIG_TABLE . "
			SET config_value = '" . $db->sql_escape($config_value) . "'
			WHERE config_name = '" . $db->sql_escape($config_name) . "'";
		$db->sql_query($sql);

		if (!$db->sql_affectedrows() && !isset($this->config[$config_name]))
		{
			$sql = 'INSERT INTO ' . TRACKER_CONFIG_TABLE . ' ' . $db->sql_build_array('INSERT', array(
				'config_name'	=> $config_name,
				'config_value'	=> $config_value,
			));
			$db->sql_query($sql);
		}

		$this->config[$config_name] = $config_value;
		$cache->destroy('_tracker');
	}

	/**
	* Sets current projects status options
	*/
	public function set_type($project_id)
	{
		$this->status = $this->get_type_option('status', $project_id);
	}

	public function set_manage($project_id)
	{
		global $user;

		$this->can_manage = group_memberships($this->projects[$project_id]['project_group'], $user->data['user_id'], true);
	}

	public function get_type_option($mode, $project_id)
	{
		switch ($mode)
		{
			case 'title':
				return $this->set_lang_name($this->types[$this->projects[$project_id]['project_type']]['title']);
			break;

			case 'version':
			case 'component':
			case 'priority':
			case 'severity':
			case 'environment':
				return $this->types[$this->projects[$project_id]['project_type']]['show_' . $mode];
			break;

			case 'status':
				return $this->types[$this->projects[$project_id]['project_type']]['status'];
			break;

			default:
				trigger_error('NO_MODE');
			break;
		}
	}

	public function delete_orphan($attach_ids, &$errors)
	{
		global $db, $phpbb_root_path, $user;

		$sql = 'SELECT * FROM ' . TRACKER_ATTACHMENTS_TABLE . '
			WHERE ' . $db->sql_in_set('attach_id', $attach_ids);
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if ($this->remove_attachment($row))
			{
				add_log('admin', 'LOG_TRACKER_DELETE_ORPHAN', $row['real_filename']);
			}
			else
			{
				$errors[] = sprintf($user->lang['TRACKER_ERROR_REMOVING_ORPHAN'], $row['real_filename'], $row['physical_filename']);
			}
		}
		$db->sql_freeresult($result);
	}

	public function delete_extra_files($filedata, &$errors)
	{
		global $phpbb_root_path, $user;

		foreach ($filedata as $item)
		{
			if (@unlink($phpbb_root_path . $this->config['attachment_path'] . '/' . $item))
			{
				add_log('admin', 'LOG_TRACKER_DELETE_EXTRA', $item);
			}
			else
			{
				$errors[] = sprintf($user->lang['TRACKER_ERROR_REMOVING_FILE'], $item);
			}
		}
	}

	public function get_project_name($project_cat_id, $project_id, $type_id = false)
	{
		$project_name = $this->project_cats[$project_cat_id]['project_name'];
		if ($type_id !== false)
		{
			$project_type = $this->set_lang_name($this->types[$type_id]['title']);
		}
		else
		{
			$project_type = $this->get_type_option('title', $project_id);
		}

		return $project_name . ' - ' . $project_type;
	}

	/**
	* Add a project category to the tracker
	* @param array $data array containing data to insert into project cat table
	*/
	public function add_project_cat($data)
	{
		global $db, $cache;

		$sql = 'INSERT INTO ' . TRACKER_PROJECT_CATS_TABLE . ' ' .
			$db->sql_build_array('INSERT', $data);
		$db->sql_query($sql);

		$cache->destroy('_tracker_projects');
		$cache->destroy('_tracker_project_cats');
	}

	/**
	* Update an existing project category in the tracker
	* @param array $data array containing data to update in the project cat table
	* @param int $id project cat id of project cat to update in the project cat table
	*/
	public function update_project_cat($data, $id)
	{
		global $db, $cache;

		$sql = 'UPDATE ' . TRACKER_PROJECT_CATS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('project_cat_id', $id);
		$db->sql_query($sql);

		$cache->destroy('_tracker_projects');
		$cache->destroy('_tracker_project_cats');
	}

	/**
	* Delete an existing project category from tracker
	* Handles removing other info associated with project cat
	* @param int $id project cat id of project cat to delete from the project cat table
	*/
	public function delete_project_cat($id)
	{
		global $db, $cache;

		$sql = 'SELECT project_id
			FROM ' . TRACKER_PROJECT_TABLE . '
				WHERE project_cat_id = ' . $id;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$this->delete_project($row['project_id']);
		}
		$db->sql_freeresult($result);

		$sql = 'DELETE FROM ' . TRACKER_PROJECT_CATS_TABLE . '
			WHERE project_cat_id = ' . $id;
		$db->sql_query($sql);

		$cache->destroy('_tracker_projects');
		$cache->destroy('_tracker_project_cats');
	}

	/**
	* Display project cat select options
	*/
	public function project_cat_select_options($selected_id)
	{
		global $user, $db;

		$sql = 'SELECT project_cat_id, project_name, project_name_clean
			FROM ' . TRACKER_PROJECT_CATS_TABLE . '
				ORDER BY project_name_clean ASC';
		$result = $db->sql_query($sql);

		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$options = '';
		foreach ($row as $item)
		{
			if ($item['project_cat_id'] == $selected_id)
			{
				$selected = ' selected="selected"';
			}
			else
			{
				$selected = '';
			}

			$options .= '<option value="' . $item['project_cat_id'] . '"' . $selected . '>' . $item['project_name'] .'</option>';
		}

		return $options;

	}

	/**
	* Add a project to the tracker
	* @param array $data array containing data to insert into projects table
	*/
	public function add_project($data)
	{
		global $db, $cache;

		$sql = 'INSERT INTO ' . TRACKER_PROJECT_TABLE . ' ' .
			$db->sql_build_array('INSERT', $data);
		$db->sql_query($sql);

		// Get project id
		$project_id = $db->sql_nextid();

		$cache->destroy('_tracker_projects');

		// Add project members to project subscription
		$members = group_memberships($data['project_group']);
		if (sizeof($members))
		{
			foreach ($members as $item)
			{
				$this->subscribe('subscribe', $project_id, false, $item['user_id']);
			}
		}

		return $project_id;
	}

	/**
	* Update an existing project in the tracker
	* @param array $data array containing data to update in the projects table
	* @param int $id project id of project to update in the projects table
	*/
	public function update_project($data, $id)
	{
		global $db, $cache;

		$sql = 'UPDATE ' . TRACKER_PROJECT_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('project_id', $id);
		$db->sql_query($sql);

		$cache->destroy('_tracker_projects');
	}

	/**
	* Delete an existing project from the bug tracker
	* Handles removing other info associated with project
	* @param int $id project id of project to delete from the projects table
	*/
	public function delete_project($id)
	{
		global $db, $cache;

		$sql = 'SELECT ticket_id
			FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $id;
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$this->delete_ticket($row['ticket_id']);
		}
		$db->sql_freeresult($result);

		$sql = 'DELETE FROM ' . TRACKER_VERSION_TABLE . '
			WHERE project_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_COMPONENTS_TABLE . '
			WHERE project_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_PROJECT_TABLE . '
			WHERE project_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_PROJECT_WATCH_TABLE . '
			WHERE project_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_TICKETS_WATCH_TABLE . '
			WHERE ticket_id = ' . $id;
		$db->sql_query($sql);

		$cache->destroy('_tracker_projects');
	}

	/**
	* Get all projects from database
	* @return array returns an array containing all the projects in the database
	*/
	public function get_projects()
	{
		global $db;

			$sql_array = array(
				'SELECT'	=> 'p.*,
								pc.*',

				'FROM'		=> array(
					TRACKER_PROJECT_TABLE	=> 'p',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(TRACKER_PROJECT_CATS_TABLE => 'pc'),
						'ON'	=> 'p.project_cat_id = pc.project_cat_id',
					),
				),

				'ORDER_BY'	=> 'pc.project_name_clean ASC, p.project_type ASC',
			);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error('TRACKER_NO_PROJECT_EXIST');
		}

		return $row;
	}

	public function get_orphaned()
	{
		global $db;

		$sql_array = array(
			'SELECT'	=> 'a.*,
							u.user_colour,
							u.username',

			'FROM'		=> array(
				TRACKER_ATTACHMENTS_TABLE	=> 'a',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 'a.poster_id = u.user_id',
				),
			),

			'WHERE'		=> 'a.is_orphan = 1 AND a.filetime < ' . (time() - 3*60*60),

			'ORDER_BY'	=>	'real_filename ASC',

		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		return $row;
	}

	public function get_extra_files()
	{
		global $db, $phpbb_root_path;

		$sql = 'SELECT physical_filename
			FROM ' . TRACKER_ATTACHMENTS_TABLE;
		$result = $db->sql_query($sql);

		$valid_files = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$valid_files[$row['physical_filename']] = true;
		}
		$db->sql_freeresult($result);

		$extra_files = array();
		$dir = $phpbb_root_path . $this->config['attachment_path'];
		if ($dh = opendir($dir))
		{
			while (false !== ($file = readdir($dh)))
			{
				if (!in_array($file, array('.', '..', 'index.htm', 'index.html', '.svn'), true))
				{
					if (!isset($valid_files[$file]))
					{
						$extra_files[$file] = $phpbb_root_path . $this->config['attachment_path'] . '/' . $file;
					}
				}
			}
			closedir($dh);
		}

		return $extra_files;
	}

	/**
	* Creates a select drop down of all the projects
	* @param array $data array containing data of all projects
	* @param int $id value of project to exclude from list
	*/
	public function project_select_options($data, $exclude_id = false, $mode = '')
	{
		$s_status_options = '';
		foreach ($data as $project)
		{
			if ($mode != '')
			{
				if (!$this->types[$project['project_type']]['show_' . $mode])
				{
					continue;
				}
			}

			if ($exclude_id)
			{
				if ($project['project_id'] == $exclude_id)
				{
					continue;
				}
			}
			$s_status_options .= '<option value="' . $project['project_id'] . '">' . $project['project_name'] . ' (' . $this->get_type_option('title', $project['project_id']) . ')' . '</option>';
		}
		return $s_status_options;
	}

	/**
	* Sets enable/disable for project
	* @param int $project_id id of project id
	* @param string $action value must be either 'enable' or 'disable'
	*/
	public function set_enabled($type, $id, $action)
	{
		global $db, $cache;

		if (!$id)
		{
			return;
		}

		$type = strtolower($type);
		$table = $column = $id_name = '';
		switch ($type)
		{
			case 'project':
				$table = TRACKER_PROJECT_TABLE;
				$column = 'project_enabled';
				$id_name = 'project_id';
			break;

			case 'version':
				$table = TRACKER_VERSION_TABLE;
				$column = 'version_enabled';
				$id_name = 'version_id';
			break;

			default:
				trigger_error('NO_MODE');
			break;
		}
		$action = strtolower($action);
		if ($action == 'enable')
		{
			$status = TRACKER_PROJECT_ENABLED;
		}
		else if ($action == 'disable')
		{
			$status = TRACKER_PROJECT_DISABLED;
		}
		else
		{
			return;
		}

		$sql = "UPDATE $table
			SET $column = $status
			WHERE $id_name = " . (int) $id;
		$db->sql_query($sql);

		if ($type == 'project')
		{
			$cache->destroy('_tracker_projects');
		}
	}

	/**
	* Add a ticket to the bug tracker
	* @param array $data array containing data to insert into tickets table
	*/
	public function add_ticket($data)
	{
		global $db;

		$data += array(
			'severity_id'	=> TRACKER_SEVERITY_DEFAULT,
			'priority_id'	=> TRACKER_PRIORITY_DEFAULT,
		);

		$sql = 'INSERT INTO ' . TRACKER_TICKETS_TABLE . ' ' .
			$db->sql_build_array('INSERT', $data);
		$db->sql_query($sql);

		$data['ticket_id'] = $db->sql_nextid();
		$this->send_notification($data, TRACKER_EMAIL_NOTIFY);

		return $data['ticket_id'];
	}

	/**
	* Update an existing ticket in the bug tracker
	* @param array $data array containing data to update in the tickets table
	* @param int $id id of ticket to update in the tickets table
	*/
	public function update_ticket($data, $id, $edited = false)
	{
		global $db;

		$edited_sql = '';
		if ($edited)
		{
			$edited_sql = ', edit_count = edit_count + 1';
		}

		$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $data) . $edited_sql . '
			WHERE ' . $db->sql_in_set('ticket_id', $id);
		$db->sql_query($sql);
	}

	/**
	* Update last visit to ticket
	* This updates the last time a project memeber visits the ticket
	* @param int $id id of ticket to update in the tickets table
	*/
	public function update_last_visit($id)
	{
		global $user, $db;

		$data = array(
			'last_visit_user_id'		=> $user->data['user_id'],
			'last_visit_username'		=> $user->data['username'],
			'last_visit_user_colour'	=> $user->data['user_colour'],
			'last_visit_time'			=> time(),
		);

		$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('ticket_id', $id);
		$db->sql_query($sql);
	}

	/**
	* Deletes ticket from database
	* @param int $id id of ticket to delete in the tickets table
	*/
	public function delete_ticket($id)
	{
		global $db;

		$sql = 'DELETE FROM ' . TRACKER_TICKETS_TABLE. '
			WHERE ticket_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_HISTORY_TABLE. '
			WHERE ticket_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_POSTS_TABLE. '
			WHERE ticket_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_TICKETS_WATCH_TABLE . '
			WHERE ticket_id = ' . $id;
		$db->sql_query($sql);
	}

	/**
	* Moves ticket from one project to another
	* Resets certain data of the ticket as
	* things might not match up between projects
	* @param int $project_id id of old project
	* @param int $ticket_id id of ticket
	*/
	public function move_ticket($project_id, $to_project_id, $ticket_id)
	{
		global $auth, $user, $db, $template;
		global $phpEx, $phpbb_root_path;

		if (!isset($this->projects[$project_id]))
		{
			trigger_error('TRACKER_PROJECT_NO_EXIST');
		}

		$data = array(
			'project_id'				=> $to_project_id,
			'component_id'				=> 0,
			'version_id'				=> 0,
			'severity_id'				=> 0,
			'priority_id'				=> 0,
			'ticket_assigned_to'		=> 0,
			'last_visit_time'			=> 0,
			'last_visit_user_id'		=> 0,
			'last_visit_username'		=> '',
			'last_visit_user_colour'	=> '',
			'status_id'					=> TRACKER_NEW_STATUS,
		);

		$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('ticket_id', $ticket_id);
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_HISTORY_TABLE. '
			WHERE ticket_id = ' . $ticket_id;
		$db->sql_query($sql);

		$message = $user->lang['TRACKER_TICKET_MOVED'] . '<br /><br />';
		$message .= sprintf($user->lang['TRACKER_REPLY_RETURN'], '<a href="' . $this->build_url('ticket', $to_project_id, $ticket_id) . '">', '</a>') . '<br /><br />';
		$message .= sprintf($user->lang['TRACKER_MOVED_RETURN'], '<a href="' . $this->build_url('project', $project_id) . '">', '</a>') . '<br /><br />';
		$message .= sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . $this->build_url('project', $to_project_id) . '">', '</a>') . '<br /><br />';
		$message .= sprintf($user->lang['TRACKER_RETURN'], '<a href="' . $this->build_url('index') . '">', '</a>') . '<br /><br />';
		$message .= sprintf($user->lang['RETURN_INDEX'], '<a href="' . $this->build_url('board'). '">', '</a>');

		trigger_error($message);

	}

	/**
	* Sets lock/unlock of ticket
	* @param string $action value must be either 'lock' or 'unlock'
	* @param int $ticket_id id of ticket
	*/
	public function manage_lock($action, $ticket_id)
	{
		global $db;

		$data = array();
		if ($action == 'lock')
		{
			$data['ticket_status'] = TRACKER_TICKET_LOCKED;
		}
		else if ($action == 'unlock')
		{
			$data['ticket_status'] = TRACKER_TICKET_UNLOCKED;
		}
		else
		{
			return;
		}

		$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('ticket_id', $ticket_id);
		$db->sql_query($sql);
	}

	/**
	* Sets hide/unhide of ticket
	* @param string $action value must be either 'hide' or 'unhide'
	* @param int $ticket_id id of ticket
	*/
	public function manage_hide($action, $ticket_id)
	{
		global $db;

		$data = array();
		if ($action == 'hide')
		{
			$data['ticket_hidden'] = TRACKER_TICKET_HIDDEN;
		}
		else if ($action == 'unhide')
		{
			$data['ticket_hidden'] = TRACKER_TICKET_UNHIDDEN;
		}
		else
		{
			return;
		}

		$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('ticket_id', $ticket_id);
		$db->sql_query($sql);
	}

	/**
	* Sets security/unsecurity of ticket
	* @param string $action value must be either 'security' or 'unsecurity'
	* @param int $ticket_id id of ticket
	*/
	public function manage_security($action, $ticket_id)
	{
		global $db;

		$data = array();
		if ($action == 'security')
		{
			$data['ticket_security'] = TRACKER_TICKET_SECURITY;
		}
		else if ($action == 'unsecurity')
		{
			$data['ticket_security'] = TRACKER_TICKET_UNSECURITY;
		}
		else
		{
			return;
		}

		$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('ticket_id', $ticket_id);
		$db->sql_query($sql);
	}

	/**
	* Add a post to the bug tracker also
	* updates last post time in the tickets table
	*/
	public function add_post($data, $id)
	{
		global $db;

		$sql = 'INSERT INTO ' . TRACKER_POSTS_TABLE . ' ' .
			$db->sql_build_array('INSERT', $data);
		$db->sql_query($sql);

		$post_id = $db->sql_nextid();

		$ticket_data = array(
			'last_post_time'		=> $data['post_time'],
			'last_post_user_id'		=> $data['post_user_id'],
		);

		$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $ticket_data) . '
			WHERE ' . $db->sql_in_set('ticket_id', $id);
		$db->sql_query($sql);

		$this->send_notification($data, TRACKER_EMAIL_NOTIFY_COMMENT);

		return $post_id;
	}

	/**
	* Updates a post in the bug tracker also
	* updates edit count of the post
	*/
	public function update_post($data, $id)
	{
		global $db;

		$sql = 'UPDATE ' . TRACKER_POSTS_TABLE. '
			SET ' . $db->sql_build_array('UPDATE', $data) . ',
				edit_count = edit_count + 1
			WHERE ' . $db->sql_in_set('post_id', $id);
		$db->sql_query($sql);
	}

	/**
	* Removes a post from the bug tracker
	* and resyncs the last post data of the ticket
	*/
	public function delete_post($id, $ticket_id = false)
	{
		global $db;

		$sql = 'DELETE FROM ' . TRACKER_POSTS_TABLE. '
			WHERE post_id = ' . $id;
		$db->sql_query($sql);

		if ($ticket_id)
		{
			$sql = 'SELECT post_user_id
						FROM ' . TRACKER_POSTS_TABLE . '
					WHERE ticket_id = ' . $ticket_id . '
					ORDER by post_time DESC';
			$result = $db->sql_query_limit($sql, 1);

			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$new_id = ($row) ? $row['post_user_id'] : 0;

			$sql = 'UPDATE ' . TRACKER_TICKETS_TABLE. '
				SET last_post_user_id = ' . $new_id . '
				WHERE ' . $db->sql_in_set('ticket_id', $ticket_id);
			$db->sql_query($sql);

		}
	}

	/**
	* Add history of action for ticket
	*/
	public function add_history($data)
	{
		global $db;

		$sql = 'INSERT INTO ' . TRACKER_HISTORY_TABLE . ' ' .
			$db->sql_build_array('INSERT', $data);
		$db->sql_query($sql);
	}

	public function is_subscribed($mode, $id)
	{
		global $phpbb_root_path, $phpEx, $user, $config, $db;

		$table = ($mode == 'ticket') ? TRACKER_TICKETS_WATCH_TABLE : TRACKER_PROJECT_WATCH_TABLE;
		$column = ($mode == 'ticket') ? 'ticket_id' : 'project_id';

		$sql = "SELECT user_id FROM $table
			WHERE $column = '$id'
				AND user_id = '" . $user->data['user_id'] . "'";
		$result = $db->sql_query($sql);

		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	public function subscribe($mode, $project_id, $ticket_id, $user_id = false)
	{
		global $phpbb_root_path, $phpEx, $user, $config, $db;

		if ($user_id === false)
		{
			if ($user->data['is_bot'] || !$user->data['is_registered'])
			{
				return;
			}
		}

		$table 		= ($project_id && $ticket_id) ? TRACKER_TICKETS_WATCH_TABLE : TRACKER_PROJECT_WATCH_TABLE;
		$column 	= ($project_id && $ticket_id) ? 'ticket_id' : 'project_id';
		$id 		= ($project_id && $ticket_id) ? $ticket_id : $project_id;
		$user_id 	= (!$user_id) ? $user->data['user_id'] : $user_id;

		if ($mode == 'subscribe')
		{
			// Make sure we do not try to insert it if
			// it already existed for whatever reason
			$sql = "UPDATE $table
				SET $column = '$id'
				WHERE user_id = '$user_id'
					AND $column = '$id'";
			$db->sql_query($sql);

			if (!$db->sql_affectedrows())
			{
				$sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', array(
					'user_id'	=> $user_id,
					$column		=> $id,
				));
				$db->sql_query($sql);
			}
		}
		else
		{
			$sql = "DELETE FROM $table
				WHERE $column = '$id'
					AND user_id = '$user_id'";
			$db->sql_query($sql);
		}
	}

	/**
	* Send email notification to required parties
	*/
	public function send_notification($data, $type, $send_subscription = true)
	{
		global $phpbb_root_path, $phpEx, $user, $config, $db;

		if (!$this->config['send_email'])
		{
			return;
		}

		$sql_array = array(
			'SELECT'	=> 't.*, p.*, pc.*',

			'FROM'		=> array(
				TRACKER_TICKETS_TABLE	=> 't',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(TRACKER_PROJECT_TABLE => 'p'),
					'ON'	=> 't.project_id = p.project_id',
				),
				array(
					'FROM'	=> array(TRACKER_PROJECT_CATS_TABLE => 'pc'),
					'ON'	=> 'p.project_cat_id = pc.project_cat_id',
				),
			),

			'WHERE'		=> 't.ticket_id = ' . $data['ticket_id'],
		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		$data = array_merge($row, $data);

		$subject = $email_template = $email_address = '';
		$email_template_vars = array();
		$board_url = generate_board_url() . '/';

		$send = true;

		switch ($type)
		{
			case TRACKER_EMAIL_NOTIFY:

				$email_template = 'tracker_notify';

				$subject = $this->format_subject($data['ticket_title'], $data['project_id'], $data['ticket_id']);
				$email_address = $user->data['user_email'];
				$email_lang = $user->data['user_lang'];
				strip_bbcode($data['ticket_desc'], $data['ticket_desc_uid']);
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> $board_url . $this->build_url('clean_ticket', array($data['project_id'], $data['ticket_id'])),
					'TICKET_ID'			=> $data['ticket_id'],
					'PROJECT_NAME'		=> htmlspecialchars_decode($data['project_name']),
					'TICKET_TITLE'		=> htmlspecialchars_decode($data['ticket_title']),
					'TICKET_DESC'		=> $this->format_desc($data['ticket_desc']),
					'TRACKER_URL'		=> $board_url . $this->build_url('clean_index'),
					'TRACKER_TYPE'		=> $this->get_type_option('title', $data['project_id']),
					'SITE_NAME'			=> htmlspecialchars_decode($config['sitename']),
				);

			break;

			case TRACKER_EMAIL_NOTIFY_COMMENT:

				$email_template = 'tracker_notify_comment';

				// We don't need to let the user know if they post in there own ticket
				if ($data['ticket_user_id'] == $user->data['user_id'])
				{
					$send = false;
					break;
				}

				// If ticket is hidden then we won't notify anyone who is not project member
				if ($data['ticket_hidden'] == TRACKER_TICKET_HIDDEN)
				{
					if (!group_memberships($this->projects[$data['project_id']]['project_group'], $data['ticket_user_id'], true))
					{
						$send = false;
						break;
					}
				}

				$sql = 'SELECT username, user_email, user_lang
							FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $data['ticket_user_id'];
				$result = $db->sql_query($sql);
				$user_row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$subject = $this->format_subject($data['ticket_title'], $data['project_id'], $data['ticket_id']);
				$email_address = $user_row['user_email'];
				$email_lang = $user_row['user_lang'];
				strip_bbcode($data['post_desc'], $data['post_desc_uid']);
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user_row['username']),
					'CHANGE_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> $board_url . $this->build_url('clean_ticket', array($data['project_id'], $data['ticket_id'])),
					'TICKET_ID'			=> $data['ticket_id'],
					'TICKET_STATUS'		=> $this->set_status($data['status_id']),
					'TICKET_TITLE'		=> htmlspecialchars_decode($data['ticket_title']),
					'TICKET_DESC'		=> $this->format_desc($data['post_desc']),
					'TRACKER_URL'		=> $board_url . $this->build_url('clean_index'),
					'PROJECT_NAME'		=> htmlspecialchars_decode($data['project_name']),
					'TRACKER_TYPE'		=> $this->get_type_option('title', $data['project_id']),
					'SITE_NAME'			=> htmlspecialchars_decode($config['sitename']),
				);

			break;

			case TRACKER_EMAIL_NOTIFY_STATUS_SINGLE:

				$email_template = 'tracker_notify_status_single';

				// If ticket is hidden then we won't notify anyone who is not project member
				if ($data['ticket_hidden'] == TRACKER_TICKET_HIDDEN)
				{
					if (!group_memberships($this->projects[$data['project_id']]['project_group'], $data['ticket_user_id'], true))
					{
						$send = false;
						break;
					}
				}

				$sql = 'SELECT username, user_email, user_lang
							FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $data['ticket_user_id'];
				$result = $db->sql_query($sql);
				$user_row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$field_name = $old_value = $new_value = '';
				switch ($data['history_status'])
				{
					case TRACKER_HISTORY_ASSIGNED_TO:

						$filter_username = $filter_user_id = array();
						$old_name = $new_name = false;
						if ($data['history_old_assigned_to'] == TRACKER_ASSIGNED_TO_GROUP)
						{
							$old_name = $this->set_lang_name($this->projects[$data['project_id']]['group_name']);
						}
						else if ($data['history_old_assigned_to'] > 0)
						{
							$filter_user_id[] = $data['history_old_assigned_to'];
						}
						else
						{
							$old_name = $user->lang['TRACKER_UNASSIGNED'];
						}

						if ($data['history_assigned_to'] == TRACKER_ASSIGNED_TO_GROUP)
						{
							$new_name = $this->set_lang_name($this->projects[$data['project_id']]['group_name']);
						}
						else if ($data['history_assigned_to'] > 0)
						{
							$filter_user_id[] = $data['history_assigned_to'];
						}
						else
						{
							$new_name = $user->lang['TRACKER_UNASSIGNED'];
						}

						if (sizeof($filter_user_id))
						{
							user_get_id_name($filter_user_id, $filter_username);
						}

						$field_name = $user->lang['TRACKER_ASSIGNED_TO'];
						$old_value = ($old_name) ? $old_name : $filter_username[$data['history_old_assigned_to']];
						$new_value = ($new_name) ? $new_name : $filter_username[$data['history_assigned_to']];
					break;

					case TRACKER_HISTORY_STATUS_CHANGED:
						$field_name = $user->lang['TRACKER_STATUS'];
						$old_value = $this->set_status($data['history_old_status']);
						$new_value = $this->set_status($data['history_new_status']);
					break;
				}

				$subject = $this->format_subject($data['ticket_title'], $data['project_id'], $data['ticket_id']);
				$email_address = $user_row['user_email'];
				$email_lang = $user_row['user_lang'];
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user_row['username']),
					'CHANGE_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> $board_url . $this->build_url('clean_ticket', array($data['project_id'], $data['ticket_id'])),
					'TICKET_ID'			=> $data['ticket_id'],
					'TICKET_TITLE'		=> htmlspecialchars_decode($data['ticket_title']),
					'TRACKER_URL'		=> $board_url . $this->build_url('clean_index'),
					'PROJECT_NAME'		=> htmlspecialchars_decode($data['project_name']),
					'TRACKER_TYPE'		=> $this->get_type_option('title', $data['project_id']),
					'SITE_NAME'			=> htmlspecialchars_decode($config['sitename']),
					'FIELD_NAME1'		=> htmlspecialchars_decode($field_name),
					'OLD_VALUE1'		=> htmlspecialchars_decode($old_value),
					'NEW_VALUE1'		=> htmlspecialchars_decode($new_value),
				);
			break;

			case TRACKER_EMAIL_NOTIFY_STATUS_DOUBLE:

				$email_template = 'tracker_notify_status_double';

				// If ticket is hidden then we won't notify anyone who is not project member
				if ($data['ticket_hidden'] == TRACKER_TICKET_HIDDEN)
				{
					if (!group_memberships($this->projects[$data['project_id']]['project_group'], $data['ticket_user_id'], true))
					{
						$send = false;
						break;
					}
				}

				$sql = 'SELECT username, user_email, user_lang
							FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $data['ticket_user_id'];
				$result = $db->sql_query($sql);
				$user_row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$field_name1 = $user->lang['TRACKER_STATUS'];
				$old_value1 = $this->set_status($data['history_old_status']);
				$new_value1 = $this->set_status($data['history_new_status']);

				$filter_username = $filter_user_id = array();
				$old_name = $new_name = false;
				if ($data['history_old_assigned_to'] == TRACKER_ASSIGNED_TO_GROUP)
				{
					$old_name = $this->set_lang_name($this->projects[$data['project_id']]['group_name']);
				}
				else if ($data['history_old_assigned_to'] > 0)
				{
					$filter_user_id[] = $data['history_old_assigned_to'];
				}
				else
				{
					$old_name = $user->lang['TRACKER_UNASSIGNED'];
				}

				if ($data['history_assigned_to'] == TRACKER_ASSIGNED_TO_GROUP)
				{
					$new_name = $this->set_lang_name($this->projects[$data['project_id']]['group_name']);
				}
				else if ($data['history_assigned_to'] > 0)
				{
					$filter_user_id[] = $data['history_assigned_to'];
				}
				else
				{
					$new_name = $user->lang['TRACKER_UNASSIGNED'];
				}

				if (sizeof($filter_user_id))
				{
					user_get_id_name($filter_user_id, $filter_username);
				}

				$field_name2 = $user->lang['TRACKER_ASSIGNED_TO'];
				$old_value2 = ($old_name) ? $old_name : $filter_username[$data['history_old_assigned_to']];
				$new_value2 = ($new_name) ? $new_name : $filter_username[$data['history_assigned_to']];

				$subject = $this->format_subject($data['ticket_title'], $data['project_id'], $data['ticket_id']);
				$email_address = $user_row['user_email'];
				$email_lang = $user_row['user_lang'];
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user_row['username']),
					'CHANGE_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> $board_url . $this->build_url('clean_ticket', array($data['project_id'], $data['ticket_id'])),
					'TICKET_ID'			=> $data['ticket_id'],
					'TICKET_TITLE'		=> htmlspecialchars_decode($data['ticket_title']),
					'TRACKER_URL'		=> $board_url . $this->build_url('clean_index'),
					'PROJECT_NAME'		=> htmlspecialchars_decode($data['project_name']),
					'TRACKER_TYPE'		=> $this->get_type_option('title', $data['project_id']),
					'SITE_NAME'			=> htmlspecialchars_decode($config['sitename']),
					'FIELD_NAME1'		=> htmlspecialchars_decode($field_name1),
					'FIELD_NAME2'		=> htmlspecialchars_decode($field_name2),
					'OLD_VALUE1'		=> htmlspecialchars_decode($old_value1),
					'OLD_VALUE2'		=> htmlspecialchars_decode($old_value2),
					'NEW_VALUE1'		=> htmlspecialchars_decode($new_value1),
					'NEW_VALUE2'		=> htmlspecialchars_decode($new_value2),
				);
			break;

			default:
			break;
		}

		if ($send)
		{
			if (!class_exists('messenger'))
			{
				include($phpbb_root_path . 'includes/functions_messenger.'.$phpEx);
			}

			$messenger = new messenger();

			$messenger->subject($subject);
			$messenger->template($email_template, $email_lang);
			$messenger->to($email_address);

			$messenger->assign_vars($email_template_vars);

			$messenger->send();
			$messenger->save_queue();
		}

		if ($send_subscription)
		{
			$this->send_subscription($data);
		}
	}

	public function send_subscription($data)
	{
		global $phpbb_root_path, $phpEx, $user, $config, $db;

		// Do not notify banned users, the user who triggered the notice
		// or the original user who placed the ticket (they will get a notice anyways)
		$sql = 'SELECT ban_userid
			FROM ' . BANLIST_TABLE . '
			WHERE ban_userid <> 0
				AND ban_exclude <> 1';
		$result = $db->sql_query($sql);

		$sql_ignore_users = ANONYMOUS . ', ' . $user->data['user_id']. ', ' . $data['ticket_user_id'];
		while ($row = $db->sql_fetchrow($result))
		{
			$sql_ignore_users .= ', ' . (int) $row['ban_userid'];
		}
		$db->sql_freeresult($result);

		$members_only = false;
		$members_ids = array();
		if ($this->projects[$data['project_id']]['project_security'] || (isset($data['ticket_hidden']) && $data['ticket_hidden'] == TRACKER_TICKET_HIDDEN) || (isset($data['ticket_security']) && $data['ticket_security'] == TRACKER_TICKET_SECURITY))
		{
			// If ticket it hidden or security ticket/tracker, then we only want to notify team members who have subscribed
			$members = array();
			$members_only = true;
			$members = group_memberships($this->projects[$data['project_id']]['project_group']);

			if (sizeof($members))
			{
				foreach ($members as $item)
				{
					$member_ids[$item['user_id']] = $item['user_id'];
				}
			}
		}

		$user_ids = array();
		$sql_array = array(
			'SELECT'	=> 'u.user_id, u.username, u.user_email, u.user_lang',

			'FROM'		=> array(
				TRACKER_PROJECT_WATCH_TABLE	=> 'p',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 'p.user_id = u.user_id',
				),
			),

			'WHERE'		=> 'p.project_id = ' . $data['project_id'] . '
				AND u.user_id NOT IN('. $sql_ignore_users .')',
		);
		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			// If we need members only and user is not group member skip it
			if ($members_only && !isset($member_ids[$row['user_id']]))
			{
				continue;
			}

			$user_ids[$row['user_id']] = array(
				'user_id'		=> $row['user_id'],
				'username'		=> $row['username'],
				'user_email'	=> $row['user_email'],
				'user_lang'		=> $row['user_lang'],
			);
		}
		$db->sql_freeresult($result);

		$sql_array = array(
			'SELECT'	=> 'u.user_id, u.username, u.user_email, u.user_lang',

			'FROM'		=> array(
				TRACKER_TICKETS_WATCH_TABLE	=> 't',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 't.user_id = u.user_id',
				),
			),

			'WHERE'		=> 't.ticket_id = ' . $data['ticket_id'] . '
				AND u.user_id NOT IN('. $sql_ignore_users .')',
		);
		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if (!isset($user_ids[$row['user_id']]))
			{
				// If we need members only and user is not group member skip it
				if ($members_only && !isset($member_ids[$row['user_id']]))
				{
					continue;
				}

				$user_ids[$row['user_id']] = array(
					'user_id'		=> $row['user_id'],
					'username'		=> $row['username'],
					'user_email'	=> $row['user_email'],
					'user_lang'		=> $row['user_lang'],
				);
			}
		}
		$db->sql_freeresult($result);

		// Alright we should now have all the user we want to notify
		// So we can now get ready to send...
		if (sizeof($user_ids))
		{
			if (!class_exists('messenger'))
			{
				include($phpbb_root_path . 'includes/functions_messenger.'.$phpEx);
			}

			$messenger = new messenger();
			$board_url = generate_board_url() . '/';

			foreach ($user_ids as $users)
			{
				$subject = $this->format_subject($data['ticket_title'], $data['project_id'], $data['ticket_id']);
				$email_address = $users['user_email'];
				strip_bbcode($data['ticket_desc'], $data['ticket_desc_uid']);
				$email_template_vars = array(
					'USERNAME'					=> htmlspecialchars_decode($users['username']),
					'TICKET_URL'				=> $board_url . $this->build_url('clean_ticket', array($data['project_id'], $data['ticket_id'])),
					'TICKET_ID'					=> $data['ticket_id'],
					'PROJECT_UNSUBSCRIBE_URL'	=> $board_url . $this->build_url('clean_unsubscribe_p', array($data['project_id'])),
					'TICKET_UNSUBSCRIBE_URL'	=> $board_url . $this->build_url('clean_unsubscribe_t', array($data['project_id'], $data['ticket_id'])),
					'TICKET_TITLE'				=> htmlspecialchars_decode($data['ticket_title']),
					'TRACKER_URL'				=> $board_url . $this->build_url('clean_index'),
					'PROJECT_NAME'				=> htmlspecialchars_decode($data['project_name']),
					'TRACKER_TYPE'				=> $this->get_type_option('title', $data['project_id']),
					'SITE_NAME'					=> htmlspecialchars_decode($config['sitename']),
				);

				$messenger->subject($subject);
				$messenger->template('tracker_notify_watch', $users['user_lang']);
				$messenger->to($email_address);

				$messenger->assign_vars($email_template_vars);

				$messenger->send();
			}
			$messenger->save_queue();
		}

	}

	public function process_notification($data, $ticket, $send_subscription = true)
	{
		global $user;

		if ($data['priority_id'] != $ticket['priority_id'])
		{
			$history = array(
				'history_time'			=> time(),
				'history_status'		=> TRACKER_HISTORY_PRIORITY_CHANGED,
				'history_user_id'		=> $user->data['user_id'],
				'ticket_id'				=> $ticket['ticket_id'],
				'history_old_priority'	=> $ticket['priority_id'],
				'history_new_priority'	=> $data['priority_id'],
			);

			$this->add_history($history);
			unset($history);
		}

		if ($data['severity_id'] != $ticket['severity_id'])
		{
			$history = array(
				'history_time'			=> time(),
				'history_status'		=> TRACKER_HISTORY_SEVERITY_CHANGED,
				'history_user_id'		=> $user->data['user_id'],
				'ticket_id'				=> $ticket['ticket_id'],
				'history_old_severity'	=> $ticket['severity_id'],
				'history_new_severity'	=> $data['severity_id'],
			);

			$this->add_history($history);
			unset($history);
		}

		$history_ts = false;
		if ($data['status_id'] != $ticket['status_id'])
		{
			$history_status = array(
				'history_time'			=> time(),
				'history_status'		=> TRACKER_HISTORY_STATUS_CHANGED,
				'history_user_id'		=> $user->data['user_id'],
				'ticket_id'				=> $ticket['ticket_id'],
				'history_old_status'	=> $ticket['status_id'],
				'history_new_status'	=> $data['status_id'],
			);

			$this->add_history($history_status);
			$history_ts = true;
		}

		$history_at = false;
		if ($data['ticket_assigned_to'] != $ticket['ticket_assigned_to'])
		{
			$history_data = array(
				'history_time'			=> time(),
				'history_status'		=> TRACKER_HISTORY_ASSIGNED_TO,
				'history_user_id'		=> $user->data['user_id'],
				'ticket_id'				=> $ticket['ticket_id'],
				'history_assigned_to'	=> $data['ticket_assigned_to'],
			);

			$this->add_history($history_data);
			$history_at = true;
		}

		if ($history_at && !$history_ts)
		{
			$history_data['history_old_assigned_to'] = $ticket['ticket_assigned_to'];
			$this->send_notification($history_data, TRACKER_EMAIL_NOTIFY_STATUS_SINGLE, $send_subscription);
		}
		else if ($history_ts && !$history_at)
		{
			$this->send_notification($history_status, TRACKER_EMAIL_NOTIFY_STATUS_SINGLE, $send_subscription);
		}
		else if ($history_at && $history_ts)
		{
			$history_data['history_old_assigned_to'] = $ticket['ticket_assigned_to'];
			$this->send_notification(array_merge($history_data, $history_status), TRACKER_EMAIL_NOTIFY_STATUS_DOUBLE, $send_subscription);
		}
	}

	/**
	* Limits the email notification subject to length
	* specified in the bug tracker constants
	*/
	public function format_subject($subject, $project_id, $ticket_id)
	{
		global $user, $config;

		if (strlen($subject) > TRACKER_SUBJECT_LENGTH)
		{
			$subject = substr($subject, 0, TRACKER_SUBJECT_LENGTH) . '...';
		}

		$subject = sprintf($user->lang['TRACKER_EMAIL_SUBJECT'], $config['sitename'], $this->projects[$project_id]['project_name'], $this->get_type_option('title', $project_id), $ticket_id, $subject);

		return htmlspecialchars_decode($subject);
	}

	/**
	* Tries to make sure the notification email is readable
	*/
	public function format_desc($text)
	{
		$text = str_replace('&nbsp;', ' ', $text);
		$text = str_replace('&#46;', '.', $text);
		$text = str_replace('&#91;', '[', $text);
		$text = str_replace('&#93;', ']', $text);

		return htmlspecialchars_decode($text);
	}

	public function handle_project_items($mode, $type, $data = false, $id = false)
	{
		global $cache, $db;

		$table = '';
		switch ($type)
		{
			case 'component':
				$table = TRACKER_COMPONENTS_TABLE;
			break;

			case 'version':
				$table = TRACKER_VERSION_TABLE;
			break;

			default:
				trigger_error('NO_MODE');
			break;
		}

		switch ($mode)
		{
			case 'add':
				$sql = 'INSERT INTO ' . $table . ' ' .
					$db->sql_build_array('INSERT', $data);
				$db->sql_query($sql);
			break;

			case 'update':
				$sql = 'UPDATE ' . $table . '
					SET ' . $db->sql_build_array('UPDATE', $data) . '
					WHERE ' . $db->sql_in_set($type . '_id', $id);
				$db->sql_query($sql);
			break;

			case 'delete':
				$sql = 'SELECT ticket_id
					FROM ' . TRACKER_TICKETS_TABLE . '
						WHERE ' . $type . '_id = ' . $id;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if ($row)
				{
					trigger_error('TRACKER_' . strtoupper($type) . '_DELETE_ERROR', E_USER_WARNING);
				}

				$sql = 'DELETE FROM ' . $table . '
					WHERE ' . $type . '_id = ' . $id;
				$db->sql_query($sql);
			break;

			default:
				trigger_error('NO_MODE');
			break;
		}
		$cache->destroy('_tracker_projects');
	}

	/**
	* Sets name of given component id
	*/
	public function set_component_name($component_id, $components)
	{
		global $user;

		if (isset($components[$component_id]))
		{
			return $this->set_lang_name($components[$component_id]);
		}
		else
		{
			return $user->lang['TRACKER_UNKNOWN'];
		}
	}

	/**
	* Checks for lang variable if not set then uses the stored string
	*/
	public function set_lang_name($name)
	{
		global $user;

		return (!empty($user->lang[$name])) ? $user->lang[$name] : ((!empty($user->lang['G_'. $name])) ? $user->lang['G_' . $name] : $name);
	}

	/**
	* Format username to display on filter
	*/
	public function format_username($username)
	{
		if (function_exists('tracker_format_username'))
		{
			return tracker_format_username($username);
		}
		else
		{
			return $username . '\'s';
		}
	}

	/**
	* Returns true if status is considered closed
	* Returns false is status is considered open
	*/
	public function is_closed($status_id)
	{
		$closed = true;
		if (isset($this->status[$status_id]) && $this->status[$status_id]['open'] == true)
		{
			$closed = false;
		}

		return $closed;
	}

	/**
	* Returns array of status ids that are considered open
	*/
	public function get_opened()
	{
		$open_status = array();
		foreach ($this->status as $item)
		{
			if ($item['open'] == false)
			{
				continue;
			}
			$open_status[] = $item['id'];
		}

		return $open_status;
	}

	/**
	* Set filter to display on required tickets
	*/
	public function get_filter_sql($type, $version_id, $component_id)
	{
		global $db;

		$filter = '';
		switch ($type)
		{
			case TRACKER_ALL:
			break;

			case TRACKER_ALL_OPENED:
				$filter = ' AND ' . $db->sql_in_set('t.status_id', $this->get_opened());
			break;

			case TRACKER_ALL_CLOSED:
				$filter = ' AND ' . $db->sql_in_set('t.status_id', $this->get_opened(), true);
			break;

			default:
				$filter = ' AND t.status_id = ' . $type;
			break;
		}

		if ($version_id)
		{
			$filter .= ' AND t.version_id = ' . (int) $version_id;
		}

		if ($component_id)
		{
			$filter .= ' AND t.component_id = ' . (int) $component_id;
		}

		return $filter;
	}

	/**
	* Display tracker type select options
	*/
	public function type_select_options($selected_id)
	{
		global $user;

		$options = '';
		foreach ($this->types as $key => $value)
		{
			if ($selected_id == $key)
			{
				$selected = ' selected="selected"';
			}
			else
			{
				$selected = '';
			}

			$options .= '<option value="' . $key . '"' . $selected . '>' . $this->set_lang_name($value['title']) .'</option>';
		}

		return $options;

	}

	public function get_name($mode, $project_id, $id)
	{
		global $db;

		$mode = strtolower($mode);
		$name = '';
		switch ($mode)
		{
			case 'component':
				$name = (isset($this->projects[$project_id]['components'][$id]['component_name'])) ? $this->projects[$project_id]['components'][$id]['component_name'] : false;
			break;

			case 'version':
				$name = (isset($this->projects[$project_id]['versions'][$id]['version_name'])) ? $this->projects[$project_id]['versions'][$id]['version_name'] : false;
			break;

			default:
				trigger_error('NO_MODE');
			break;
		}

		return ($name) ? $this->set_lang_name($name) : false;
	}

	/**
	* Display select options for priorities, severities, components and versions
	*/
	public function select_options($project_id, $mode, $selected_id = false, $version_enabled = true)
	{
		global $db, $user;

		$where = '';
		switch ($mode)
		{
			case 'priority':
			case 'severity':
			break;

			case 'version':
				$table = TRACKER_VERSION_TABLE;
				if ($version_enabled && !$this->can_manage)
				{
					$where = ' AND version_enabled = ' . TRACKER_PROJECT_ENABLED;
				}
			break;

			case 'component':
				$table = TRACKER_COMPONENTS_TABLE;
			break;

			default:
				trigger_error('NO_MODE');
			break;
		}

		$options = '';
		if ($mode == 'component' || $mode == 'version')
		{
			$sql = 'SELECT * from ' . $table . '
				WHERE project_id = ' . $project_id . $where . '
					ORDER BY ' . $mode . '_name ASC';
			$result = $db->sql_query($sql);
			while ($row = $db->sql_fetchrow($result))
			{
				if ($selected_id && $selected_id == $row[$mode . '_id'])
				{
					$selected = ' selected="selected"';
				}
				else
				{
					$selected = '';
				}

				$options .= '<option value="' . $row[$mode . '_id'] . '"' . $selected . '>' . $this->set_lang_name($row[$mode . '_name']) .'</option>';
			}
			$db->sql_freeresult($result);
		}
		else
		{
			$row = ($mode == 'severity') ? $this->severity : $this->priority;
			if (!$selected_id)
			{
				$selected_id = ($mode == 'severity') ? TRACKER_SEVERITY_DEFAULT : TRACKER_PRIORITY_DEFAULT;
			}

			foreach ($row as $key => $value)
			{

				$selected = ($key == $selected_id) ? ' selected="selected">' : '>';
				$options .= '<option value="' . $key . '"' . $selected . $this->set_lang_name($value) .'</option>';
			}
		}

		if ($options)
		{
			$options = '<option value="0">' . $user->lang['TRACKER_SELECT'] .'</option>' . $options;
		}

		return $options;
	}

	/**
	* Returns language string of status type
	*/
	public function set_status($type)
	{
		global $user;

		if (isset($this->status[$type]))
		{
			return $user->lang[$this->status[$type]['name']];
		}
		else
		{
			return $user->lang['TRACKER_UNKNOWN'];
		}
	}

	/**
	* Displays a drop down of all the users in the project
	*/
	public function user_select_options($user_id, $group_id, $project_id)
	{
		global $user;

		$options = '';
		if ($group_id)
		{
			$users = group_memberships($group_id, false);
			foreach ($users as $row)
			{
				if ($user_id && ($user_id == $row['user_id']))
				{
					$selected = ' selected="selected"';
				}
				else
				{
					$selected = '';
				}

				$options .= '<option value="' . $row['user_id'] . '"' . $selected . '>' . $row['username'] .'</option>';
			}
		}

		$options = '<option value="0">' . $user->lang['TRACKER_UNASSIGNED'] .'</option><option value="-1"' . (($user_id == TRACKER_ASSIGNED_TO_GROUP) ? ' selected="selected"' : '') . '>' . $this->set_lang_name($this->projects[$project_id]['group_name']) .'</option>' . $options;

		return $options;
	}

	/**
	* Displays a select drop down of the status option for the bug tracker
	* Set filtered to true to display all fitler options
	*/
	public function status_select_options($status_id = 0, $filtered = false)
	{
		global $user;

		$s_status_options = '';
		foreach ($this->status as $item)
		{
			if ($item['filter'] && !$filtered)
			{
				continue;
			}

			$selected = ($item['id'] == $status_id) ? ' selected="selected">' : '>';
			$s_status_options .= '<option value="' . $item['id'] . '"' . $selected . $user->lang[$item['name']] .'</option>';
		}

		return $s_status_options;
	}

	/*
	* Generates the navigation links at the top of the page
	*/
	public function generate_nav($data, $ticket_id = false, $in_stats = false)
	{
		global $db, $user, $template, $auth;
		global $phpEx, $phpbb_root_path;

		$template->assign_block_vars('navlinks', array(
			'FORUM_NAME'   		=> $data['project_name'],
			'U_VIEW_FORUM'  	=> $this->build_url(($in_stats) ? 'statistics_pc' : 'project_cat', array($data['project_cat_id'])),
		));

		$template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->get_type_option('title', $data['project_id']),
			'U_VIEW_FORUM'	=> $this->build_url(($in_stats) ? 'statistics_p' : 'project', array($data['project_id'])),
		));

		if ($ticket_id)
		{
			$template->assign_block_vars('navlinks', array(
				'FORUM_NAME'  	 => $user->lang['TRACKER_NAV_TICKET'] . $ticket_id,
				'U_VIEW_FORUM'   => $this->build_url('ticket', array($data['project_id'], $ticket_id)),
			));
		}
	}

	/**
	* Returns the total of specified type either 'tickets' or 'posts'
	* Used for pagination
	*/
	public function get_total($type, $project_id = 0, $ticket_id = 0, $where = '')
	{
		global $db, $user;

		switch ($type)
		{
			case 'tickets':
			$sql_array = array(
				'SELECT'	=> 'COUNT(ticket_id) as total',

				'FROM'		=> array(
					TRACKER_TICKETS_TABLE	=> 't',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(TRACKER_PROJECT_TABLE => 'p'),
						'ON'	=> 't.project_id = p.project_id',
					),
				),

				'WHERE'		=> $where,
			);
			$sql = $db->sql_build_query('SELECT', $sql_array);
			break;

			case 'posts':
				$sql = 'SELECT COUNT(post_id) as total
					FROM ' . TRACKER_POSTS_TABLE . '
					WHERE ticket_id = ' . $ticket_id;
			break;

			default:
				trigger_error('NO_MODE');
			break;
		}

		$result = $db->sql_query($sql);

		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		return $row['total'];
	}

	/**
	* Returns the information at the edit history of the post/ticket
	* Follows phpbb3 $config['display_last_edited'] variable
	*/
	public function fetch_edited_by($row, $type)
	{
		global $config, $user, $db;

		$user->add_lang('viewtopic');
		$l_edited_by = '';
		if ($type == 'ticket')
		{
			if (($row['edit_count'] && $config['display_last_edited']) || $row['edit_reason'])
			{
				$l_edit_time_total = ($row['edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];

				if (!$row['edit_user'] || $row['edit_user'] == $row['ticket_user_id'])
				{
					$display_username = get_username_string('full', $row['ticket_user_id'], $row['ticket_username'], $row['ticket_user_colour']);
				}
				else
				{
					$sql = 'SELECT username, user_colour
						FROM ' . USERS_TABLE . '
						WHERE user_id = ' . (int) $row['edit_user'];
					$result = $db->sql_query($sql);
					$user_row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					$display_username = get_username_string('full', $row['edit_user'], $user_row['username'], $user_row['user_colour']);
				}
				$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['edit_time'], false, true), $row['edit_count']);
			}
		}
		else if ($type == 'post')
		{
			if (($row['edit_count'] && $config['display_last_edited']) || $row['edit_reason'])
			{
				$l_edit_time_total = ($row['edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];

				if (!$row['edit_user'] || $row['edit_user'] == $row['post_user_id'])
				{
					$display_username = get_username_string('full', $row['post_user_id'], $row['username'], $row['user_colour']);
				}
				else
				{
					$sql = 'SELECT username, user_colour
						FROM ' . USERS_TABLE . '
						WHERE user_id = ' . (int) $row['edit_user'];
					$result = $db->sql_query($sql);
					$user_row = $db->sql_fetchrow($result);
					$db->sql_freeresult($result);

					$display_username = get_username_string('full', $row['edit_user'], $user_row['username'], $user_row['user_colour']);
				}
				$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['edit_time']), $row['edit_count']);
			}
		}

		return $l_edited_by;
	}

	/**
	* Checks whether a user is allowed to edit posts/tickets
	* Follows phpbb3 built in handling of edit time limits
	*/
	public function check_edit($edit_time, $user_id, $bool = true)
	{
		global $user, $auth, $config;

		if ($auth->acl_get('a_tracker') || $auth->acl_get('u_tracker_edit_global') || ($auth->acl_get('u_tracker_edit_all') && $this->can_manage))
		{
			return true;
		}

		if (!($edit_time > time() - ($config['edit_time'] * 60) || !$config['edit_time']))
		{
			if ($bool)
			{
				return false;
			}
			trigger_error('TRACKER_CANNOT_EDIT_TIME');
		}

		if ($user->data['user_id'] != $user_id || !$auth->acl_get('u_tracker_edit'))
		{
			if ($bool)
			{
				return false;
			}
			trigger_error('TRACKER_USER_CANNOT_EDIT');
		}

		return true;

	}

	/**
	* Return a link and coloured string of the username or group assigned to the project
	* If nobody is assigned it returns the un-assigned language variable
	*/
	public function get_assigned_to($project_id, $user_id, $username, $user_colour, $mode = false)
	{
		global $user, $phpbb_root_path, $phpEx;

		$string = '';
		if ($user_id > 0)
		{
			$string = get_username_string('full', $user_id, $username, $user_colour);
			if ($mode == 'history')
			{
				$string = sprintf($user->lang['TRACKER_HISTORY_ASSIGNED_TO'], $string);
			}
		}
		else if($user_id == TRACKER_ASSIGNED_TO_GROUP)
		{
			$string = '<a style="color:#' . $this->projects[$project_id]['group_colour'] . '" href="' . $this->build_url('memberlist_group', array($this->projects[$project_id]['project_group'])) . '" class="username-coloured">' . $this->set_lang_name($this->projects[$project_id]['group_name']) . '</a>';
			if ($mode == 'history')
			{
				$string = sprintf($user->lang['TRACKER_HISTORY_ASSIGNED_TO_GROUP'], $string);
			}
		}
		else
		{
			$string = $user->lang['TRACKER_UNASSIGNED'];
			if ($mode == 'history')
			{
				$string = $user->lang['TRACKER_HISTORY_UNASSIGNED'];
			}
		}

		return $string;

	}

	/**
	* Check is specific ticket exists
	*/
	public function check_ticket_exists($id)
	{
		global $user, $db, $auth;

		$sql_array = array(
			'SELECT'	=> 't.ticket_title, t.ticket_status, p.project_group',

			'FROM'		=> array(
				TRACKER_TICKETS_TABLE	=> 't',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(TRACKER_PROJECT_TABLE => 'p'),
					'ON'	=> 't.project_id = p.project_id',
				),
			),

			'WHERE'		=> 't.ticket_id = ' . $id,
		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error('TRACKER_TICKET_NO_EXIST');
		}

		if ($auth->acl_get('u_tracker_edit_global') || ($this->can_manage && $auth->acl_get('u_tracker_edit_all')))
		{
			return true;
		}

		if ($row['ticket_status'] == TRACKER_TICKET_LOCKED && !$this->can_manage)
		{
			trigger_error('TRACKER_TICKET_LOCKED_MESSAGE');
		}

		return true;
	}

	/**
	* Check is specific post exists
	*/
	public function check_post_exists($id)
	{
		global $user, $db;

		$sql = 'SELECT post_desc
					FROM ' . TRACKER_POSTS_TABLE . '
				WHERE post_id = ' . $id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error('TRACKER_POST_NO_EXIST');
		}

		return true;
	}

	/**
	* Method is no longer used as we can now
	* use the projects property of the class
	* to check for existence
	*/
	public function check_project_exists($id)
	{
		global $user, $db;

		$sql = 'SELECT project_name
					FROM ' . TRACKER_PROJECT_TABLE . '
				WHERE project_id = ' . $id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error('TRACKER_PROJECT_NO_EXIST');
		}

		return true;
	}

	/**
	* Function used to quickly show the contents of variables
	*/
	public function debug($exit = false, $var = false)
	{
		if ($var !== false)
		{
			echo '<pre>';
			var_export($var);
			echo '</pre>';
		}
		else
		{
			$debug = array(
				'POST'		=> $_POST,
				'GET'		=> $_GET,
				'REQUEST'	=> $_REQUEST,
				'COOKIE'	=> $_COOKIE,
				'SERVER'	=> $_SERVER,
			);


			foreach ($debug as $key => $value)
			{
				echo '<p><b>' . $key . '</b>';
				echo '<pre>';
				var_export($value);
				echo '</pre>';
				echo '</p>';
			}
		}

		if ($exit)
		{
			exit;
		}
	}

	/*
	* Attachment functions
	* Most of this code is forked from User Blog mod (EXreaction, Lithium Studios)
	* which is forked from the attachment code in phpBB3.
	*/
	/**
	* Updates some attachment data
	*/
	public function update_attachment_data($ticket_id, $post_id = 0)
	{
		global $auth, $config, $db;

		if (!$this->config['allow_attachments'] || !$auth->acl_get('u_tracker_attach'))
		{
			return;
		}

		$ticket_id = (int) $ticket_id;
		$post_id = (int) $post_id;

		$attach_ids = array();

		if (sizeof($this->attachment_data))
		{
			foreach($this->attachment_data as $attach_row)
			{
				$sql = 'UPDATE ' . TRACKER_ATTACHMENTS_TABLE . "
					SET attach_comment = '" . $db->sql_escape($attach_row['attach_comment']) . "',
						is_orphan = 0,
							ticket_id = {$ticket_id},
								post_id = {$post_id}
									WHERE attach_id = " . (int) $attach_row['attach_id'];
				$db->sql_query($sql);
			}
		}
	}

	/**
	* Generate inline attachment entry
	*/
	public function posting_gen_attachment_entry($attachment_data, &$filename_data)
	{
		global $template, $config;

		if (!$this->config['allow_attachments'])
		{
			return;
		}

		$template->assign_vars(array(
			'S_SHOW_ATTACH_BOX'	=> true)
		);

		if (sizeof($attachment_data))
		{
			$template->assign_vars(array(
				'S_HAS_ATTACHMENTS'	=> true)
			);

			ksort($attachment_data);

			foreach ($attachment_data as $count => $attach_row)
			{
				$hidden = '';
				$attach_row['real_filename'] = basename($attach_row['real_filename']);

				foreach ($attach_row as $key => $value)
				{
					$hidden .= '<input type="hidden" name="attachment_data[' . $count . '][' . $key . ']" value="' . $value . '" />';
				}

				$download_link = $this->build_url('download', array(intval($attach_row['attach_id']), ''));

				$template->assign_block_vars('attach_row', array(
					'FILENAME'			=> basename($attach_row['real_filename']),
					'A_FILENAME'		=> addslashes(basename($attach_row['real_filename'])),
					'FILE_COMMENT'		=> $attach_row['attach_comment'],
					'ATTACH_ID'			=> $attach_row['attach_id'],
					'S_IS_ORPHAN'		=> $attach_row['is_orphan'],
					'ASSOC_INDEX'		=> $count,

					'U_VIEW_ATTACHMENT'	=> $download_link,
					'S_HIDDEN'			=> $hidden)
				);
			}
		}

		$template->assign_vars(array(
			'FILESIZE'		=> $config['max_filesize'])
		);

		return sizeof($attachment_data);
	}

	/**
	* Get Attachment Data
	*
	* Grabs attachment data for trackers and replies.
	*
	* @param int|array $ticket_ids An array of ticket_ids to look up
	* @param int|array|bool $post_ids An array of post_ids to look up
	*/
	public function get_attachment_data($ticket_ids, $post_ids = false)
	{
		global $auth, $config, $db;

		if (!$this->config['allow_attachments'] || !$auth->acl_get('u_tracker_download'))
		{
			return;
		}

		if (!is_array($ticket_ids))
		{
			$ticket_ids = array($ticket_ids);
		}

		if (!is_array($post_ids) && $post_ids !== false)
		{
			$post_ids = array($post_ids);
		}

		$post_sql = ($post_ids !== false) ? ' OR ' . $db->sql_in_set('post_id', $post_ids) : '';

		$sql = 'SELECT * FROM ' . TRACKER_ATTACHMENTS_TABLE . '
			WHERE ' . $db->sql_in_set('ticket_id', $ticket_ids) .
				$post_sql . '
					ORDER BY attach_id DESC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if ($row['post_id'] != 0)
			{
				$this->attachment_data_post[$row['post_id']][] = $row;
			}
			else if ($row['ticket_id'] != 0)
			{
				$this->attachment_data_ticket[$row['ticket_id']][] = $row;
			}
		}
		$db->sql_freeresult($result);
	}

	/**
	* Parse Attachments
	*/
	public function parse_attachments($form_name, $submit, $preview, $refresh, &$text)
	{
		global $config, $auth, $user, $phpbb_root_path, $phpEx, $db;

		if (!$this->config['allow_attachments'] || !$auth->acl_get('u_tracker_attach'))
		{
			return;
		}

		$error = array();
		
		$num_attachments = sizeof($this->attachment_data);
		$this->filename_data['filecomment'] = utf8_normalize_nfc(request_var('filecomment', '', true));
		$upload_file = (isset($_FILES[$form_name]) && $_FILES[$form_name]['name'] != 'none' && trim($_FILES[$form_name]['name'])) ? true : false;

		$add_file		= (isset($_POST['add_file'])) ? true : false;
		$delete_file	= (isset($_POST['delete_file'])) ? true : false;

		// First of all adjust comments if changed
		$actual_comment_list = utf8_normalize_nfc(request_var('comment_list', array(''), true));

		foreach ($actual_comment_list as $comment_key => $comment)
		{
			if (!isset($this->attachment_data[$comment_key]))
			{
				continue;
			}

			if ($this->attachment_data[$comment_key]['attach_comment'] != $actual_comment_list[$comment_key])
			{
				$this->attachment_data[$comment_key]['attach_comment'] = $actual_comment_list[$comment_key];
			}
		}

		if ($submit && $upload_file)
		{
			if ($num_attachments < $this->config['max_attachments'] || $auth->acl_get('a_'))
			{
				$filedata = $this->upload_attachment($form_name, false, '');
				$error = $filedata['error'];

				if ($filedata['post_attach'] && !sizeof($error))
				{
					$sql_ary = array(
						'physical_filename'	=> $filedata['physical_filename'],
						'attach_comment'	=> $this->filename_data['filecomment'],
						'real_filename'		=> $filedata['real_filename'],
						'extension'			=> $filedata['extension'],
						'mimetype'			=> $filedata['mimetype'],
						'filesize'			=> $filedata['filesize'],
						'filetime'			=> $filedata['filetime'],
						'thumbnail'			=> $filedata['thumbnail'],
						'is_orphan'			=> 1,
						'poster_id'			=> $user->data['user_id'],
					);

					$db->sql_query('INSERT INTO ' . TRACKER_ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));
					unset($sql_ary);

					$new_entry = array(
						'attach_id'		=> $db->sql_nextid(),
						'is_orphan'		=> 1,
						'real_filename'	=> $filedata['real_filename'],
						'attach_comment'=> $this->filename_data['filecomment'],
					);

					$this->attachment_data = array_merge(array(0 => $new_entry), $this->attachment_data);
					$text = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "'[attachment='.(\\1 + 1).']\\2[/attachment]'", $text);

					$this->filename_data['filecomment'] = '';

					// This Variable is set to false here, because Attachments are entered into the
					// Database in two modes, one if the id_list is 0 and the second one if post_attach is true
					// Since post_attach is automatically switched to true if an Attachment got added to the filesystem,
					// but we are assigning an id of 0 here, we have to reset the post_attach variable to false.
					//
					// This is very relevant, because it could happen that the post got not submitted, but we do not
					// know this circumstance here. We could be at the posting page or we could be redirected to the entered
					// post. :)
					$filedata['post_attach'] = false;
				}
			}
			else
			{
				$error[] = sprintf($user->lang['TOO_MANY_ATTACHMENTS'], $this->config['max_attachments']);
			}
		}

		if ($preview || $refresh || sizeof($error))
		{
			// Perform actions on temporary attachments
			if ($delete_file)
			{
				include_once($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

				$index = array_keys(request_var('delete_file', array(0 => 0)));
				$index = (!empty($index)) ? $index[0] : false;

				if ($index !== false && !empty($this->attachment_data[$index]))
				{
					// delete selected attachment
					if ($this->attachment_data[$index]['is_orphan'])
					{
						$sql = 'SELECT attach_id, physical_filename, thumbnail
							FROM ' . TRACKER_ATTACHMENTS_TABLE . '
							WHERE attach_id = ' . (int) $this->attachment_data[$index]['attach_id'] . '
								AND is_orphan = 1
								AND poster_id = ' . $user->data['user_id'];
						$result = $db->sql_query($sql);
						$row = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);

						if ($row)
						{
							phpbb_unlink($row['physical_filename'], 'file');

							if ($row['thumbnail'])
							{
								phpbb_unlink($row['physical_filename'], 'thumbnail');
							}

							$db->sql_query('DELETE FROM ' . TRACKER_ATTACHMENTS_TABLE . ' WHERE attach_id = ' . (int) $this->attachment_data[$index]['attach_id']);
						}
					}
					else
					{
						$sql = 'SELECT attach_id, physical_filename, thumbnail
							FROM ' . TRACKER_ATTACHMENTS_TABLE . '
							WHERE attach_id = ' . (int) $this->attachment_data[$index]['attach_id'];
						$result = $db->sql_query($sql);
						$row = $db->sql_fetchrow($result);
						$db->sql_freeresult($result);

						if ($row)
						{
							phpbb_unlink($row['physical_filename'], 'file');

							if ($row['thumbnail'])
							{
								phpbb_unlink($row['physical_filename'], 'thumbnail');
							}

							$db->sql_query('DELETE FROM ' . TRACKER_ATTACHMENTS_TABLE . ' WHERE attach_id = ' . (int) $this->attachment_data[$index]['attach_id']);
						}
					}

					unset($this->attachment_data[$index]);
					$text = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "(\\1 == \$index) ? '' : ((\\1 > \$index) ? '[attachment=' . (\\1 - 1) . ']\\2[/attachment]' : '\\0')", $text);

					// Reindex Array
					$this->attachment_data = array_values($this->attachment_data);
				}
			}
			else if (($add_file || $preview) && $upload_file)
			{
				if ($num_attachments < $this->config['max_attachments'] || $auth->acl_get('a_'))
				{
					$filedata = $this->upload_attachment($form_name, false, '');
					$error = array_merge($error, $filedata['error']);

					if (!sizeof($error))
					{
						$sql_ary = array(
							'physical_filename'	=> $filedata['physical_filename'],
							'attach_comment'	=> $this->filename_data['filecomment'],
							'real_filename'		=> $filedata['real_filename'],
							'extension'			=> $filedata['extension'],
							'mimetype'			=> $filedata['mimetype'],
							'filesize'			=> $filedata['filesize'],
							'filetime'			=> $filedata['filetime'],
							'thumbnail'			=> $filedata['thumbnail'],
							'is_orphan'			=> 1,
							'poster_id'			=> $user->data['user_id'],
						);

						$db->sql_query('INSERT INTO ' . TRACKER_ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));

						$new_entry = array(
							'attach_id'		=> $db->sql_nextid(),
							'is_orphan'		=> 1,
							'real_filename'	=> $filedata['real_filename'],
							'attach_comment'=> $this->filename_data['filecomment'],
						);

						$this->attachment_data = array_merge(array(0 => $new_entry), $this->attachment_data);
						$text = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "'[attachment='.(\\1 + 1).']\\2[/attachment]'", $text);
						$this->filename_data['filecomment'] = '';
					}
				}
				else
				{
					$error[] = sprintf($user->lang['TOO_MANY_ATTACHMENTS'], $this->config['max_attachments']);
				}
			}
		}

		foreach ($error as $error_msg)
		{
			$this->warn_msg[] = $error_msg;
		}
		unset($error);
	}

	/**
	* Get Attachment Data
	*/
	public function get_submitted_attachment_data($check_user_id = false)
	{
		global $user, $db, $config, $auth;

		if (!$this->config['allow_attachments'] || !$auth->acl_get('u_tracker_attach'))
		{
			return;
		}

		$this->filename_data['filecomment'] = utf8_normalize_nfc(request_var('filecomment', '', true));
		$attachment_data = (isset($_POST['attachment_data'])) ? $_POST['attachment_data'] : array();
		$this->attachment_data = array();

		$check_user_id = ($check_user_id === false) ? $user->data['user_id'] : $check_user_id;

		if (!sizeof($attachment_data))
		{
			return;
		}

		$not_orphan = $orphan = array();

		foreach ($attachment_data as $pos => $var_ary)
		{
			if ($var_ary['is_orphan'])
			{
				$orphan[(int) $var_ary['attach_id']] = $pos;
			}
			else
			{
				$not_orphan[(int) $var_ary['attach_id']] = $pos;
			}
		}

		// Regenerate already posted attachments
		if (sizeof($not_orphan))
		{
			// Get the attachment data, based on the poster id...
			$sql = 'SELECT attach_id, is_orphan, real_filename, attach_comment
				FROM ' . TRACKER_ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', array_keys($not_orphan)) . '
					AND poster_id = ' . $check_user_id;
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$pos = $not_orphan[$row['attach_id']];
				$this->attachment_data[$pos] = $row;
				set_var($this->attachment_data[$pos]['attach_comment'], $_POST['attachment_data'][$pos]['attach_comment'], 'string', true);

				unset($not_orphan[$row['attach_id']]);
			}
			$db->sql_freeresult($result);
		}

		if (sizeof($not_orphan))
		{
			trigger_error('NO_ACCESS_ATTACHMENT', E_USER_ERROR);
		}

		// Regenerate newly uploaded attachments
		if (sizeof($orphan))
		{
			$sql = 'SELECT attach_id, is_orphan, real_filename, attach_comment
				FROM ' . TRACKER_ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', array_keys($orphan)) . '
					AND poster_id = ' . $user->data['user_id'] . '
					AND is_orphan = 1';
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$pos = $orphan[$row['attach_id']];
				$this->attachment_data[$pos] = $row;
				set_var($this->attachment_data[$pos]['attach_comment'], $_POST['attachment_data'][$pos]['attach_comment'], 'string', true);

				unset($orphan[$row['attach_id']]);
			}
			$db->sql_freeresult($result);
		}

		if (sizeof($orphan))
		{
			trigger_error('NO_ACCESS_ATTACHMENT', E_USER_ERROR);
		}

		ksort($this->attachment_data);
	}

	/**
	* Upload Attachment - filedata is generated here
	* Uses upload class
	*/
	public function upload_attachment($form_name, $local = false, $local_storage = '', $local_filedata = false)
	{
		global $auth, $user, $config, $db, $phpbb_root_path, $phpEx, $cache;

		if (!$this->config['allow_attachments'] || !$auth->acl_get('u_tracker_attach'))
		{
			return;
		}

		$filedata = array(
			'error'	=> array()
		);

		if (!class_exists('fileupload'))
		{
			include($phpbb_root_path . 'includes/functions_upload.' . $phpEx);
		}

		$upload = new fileupload();
		
		if ($config['check_attachment_content'])
		{
			$upload->set_disallowed_content(explode('|', $config['mime_triggers']));
		}

		if (!$local)
		{
			$filedata['post_attach'] = ($upload->is_valid($form_name)) ? true : false;
		}
		else
		{
			$filedata['post_attach'] = true;
		}

		if (!$filedata['post_attach'])
		{
			$filedata['error'][] = $user->lang['NO_UPLOAD_FORM_FOUND'];
			return $filedata;
		}

		$extensions = $cache->obtain_attach_extensions(TRACKER_EXTENSION_ID);
		$upload->set_allowed_extensions(array_keys($extensions['_allowed_']));

		$file = ($local) ? $upload->local_upload($local_storage, $local_filedata) : $upload->form_upload($form_name);

		if ($file->init_error)
		{
			$filedata['post_attach'] = false;
			return $filedata;
		}

		$cat_id = (isset($extensions[$file->get('extension')]['display_cat'])) ? $extensions[$file->get('extension')]['display_cat'] : ATTACHMENT_CATEGORY_NONE;

		// Make sure the image category only holds valid images...
		if ($cat_id == ATTACHMENT_CATEGORY_IMAGE && !$file->is_image())
		{
			$file->remove();

			// If this error occurs a user tried to exploit an IE Bug by renaming extensions
			// Since the image category is displaying content inline we need to catch this.
			trigger_error($user->lang['ATTACHED_IMAGE_NOT_IMAGE']);
		}

		// Do we have to create a thumbnail?
		$filedata['thumbnail'] = ($cat_id == ATTACHMENT_CATEGORY_IMAGE && $config['img_create_thumbnail']) ? 1 : 0;

		// Check Image Size, if it is an image
		if (!$auth->acl_get('a_') && $cat_id == ATTACHMENT_CATEGORY_IMAGE)
		{
			$file->upload->set_allowed_dimensions(0, 0, $config['img_max_width'], $config['img_max_height']);
		}

		// Admins and mods are allowed to exceed the allowed filesize
		if (!$auth->acl_get('a_'))
		{
			if (!empty($extensions[$file->get('extension')]['max_filesize']))
			{
				$allowed_filesize = $extensions[$file->get('extension')]['max_filesize'];
			}
			else
			{
				$allowed_filesize = $config['max_filesize'];
			}

			$file->upload->set_max_filesize($allowed_filesize);
		}

		$file->clean_filename('unique', $user->data['user_id'] . '_');

		// Are we uploading an image *and* this image being within the image category? Only then perform additional image checks.
		$no_image = ($cat_id == ATTACHMENT_CATEGORY_IMAGE) ? false : true;

		if (!$file->move_file($this->config['attachment_path'], false, $no_image))
		{
			$file->error[] = sprintf($user->lang[$file->upload->error_prefix . 'GENERAL_UPLOAD_ERROR'], $this->config['attachment_path']);
		}

		if (sizeof($file->error))
		{
			$file->remove();
			$filedata['error'] = array_merge($filedata['error'], $file->error);
			$filedata['post_attach'] = false;

			return $filedata;
		}

		$filedata['filesize'] = $file->get('filesize');
		$filedata['mimetype'] = $file->get('mimetype');
		$filedata['extension'] = $file->get('extension');
		$filedata['physical_filename'] = $file->get('realname');
		$filedata['real_filename'] = $file->get('uploadname');
		$filedata['filetime'] = time();

		// Check our complete quota
		if ($config['attachment_quota'])
		{
			if ($config['upload_dir_size'] + $file->get('filesize') > $config['attachment_quota'])
			{
				$filedata['error'][] = $user->lang['ATTACH_QUOTA_REACHED'];
				$filedata['post_attach'] = false;

				$file->remove();

				return $filedata;
			}
		}

		// Check free disk space
		if ($free_space = @disk_free_space($phpbb_root_path . $this->config['attachment_path']))
		{
			if ($free_space <= $file->get('filesize'))
			{
				$filedata['error'][] = $user->lang['ATTACH_QUOTA_REACHED'];
				$filedata['post_attach'] = false;

				$file->remove();

				return $filedata;
			}
		}

		// Create Thumbnail
		if ($filedata['thumbnail'])
		{
			$source = $file->get('destination_file');
			$destination = $file->get('destination_path') . '/thumb_' . $file->get('realname');

			if (!create_thumbnail($source, $destination, $file->get('mimetype')))
			{
				$filedata['thumbnail'] = 0;
			}
		}

		return $filedata;
	}

	/**
	* General attachment parsing
	*
	* @param string &$message The post/private message
	* @param array &$attachments The attachments to parse for (inline) display. The attachments array will hold templated data after parsing.
	* @param array &$update_count The attachment counts to be updated - will be filled
	* @param bool $preview If set to true the attachments are parsed for preview. Within preview mode the comments are fetched from the given $attachments array and not fetched from the database.
	*/
	public function parse_attachments_for_view(&$message, &$attachments, &$update_count, $preview = false)
	{
		global $template, $user, $config, $phpbb_root_path, $auth, $cache;

		if (!sizeof($attachments) || !$auth->acl_get('u_tracker_download'))
		{
			return;
		}

		$compiled_attachments = array();

		if (!isset($template->filename['attachment_tpl']))
		{
			$template->set_filenames(array(
				'attachment_tpl'	=> 'attachment.html')
			);
		}

		$extensions = $cache->obtain_attach_extensions(TRACKER_EXTENSION_ID);

		// Look for missing attachment information...
		$attach_ids = array();
		foreach ($attachments as $pos => $attachment)
		{
			// If is_orphan is set, we need to retrieve the attachments again...
			if (!isset($attachment['extension']) && !isset($attachment['physical_filename']))
			{
				$attach_ids[(int) $attachment['attach_id']] = $pos;
			}
		}

		// Grab attachments (security precaution)
		if (sizeof($attach_ids))
		{
			global $db;

			$new_attachment_data = array();

			$sql = 'SELECT *
				FROM ' . TRACKER_ATTACHMENTS_TABLE . '
				WHERE ' . $db->sql_in_set('attach_id', array_keys($attach_ids));
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				if (!isset($attach_ids[$row['attach_id']]))
				{
					continue;
				}

				// If we preview attachments we will set some retrieved values here
				if ($preview)
				{
					$row['attach_comment'] = $attachments[$attach_ids[$row['attach_id']]]['attach_comment'];
				}

				$new_attachment_data[$attach_ids[$row['attach_id']]] = $row;
			}
			$db->sql_freeresult($result);

			$attachments = $new_attachment_data;
			unset($new_attachment_data);
		}

		ksort($attachments);


		foreach ($attachments as $attachment)
		{
			if (!sizeof($attachment))
			{
				continue;
			}

			// We need to reset/empty the _file block var, because this function might be called more than once
			$template->destroy_block_vars('_file');

			$block_array = array();
			
			// Some basics...
			$attachment['extension'] = strtolower(trim($attachment['extension']));
			$filename = $phpbb_root_path . $this->config['attachment_path'] . '/' . basename($attachment['physical_filename']);
			$thumbnail_filename = $phpbb_root_path . $this->config['attachment_path'] . '/thumb_' . basename($attachment['physical_filename']);

			$upload_icon = '';

			if (isset($extensions[$attachment['extension']]))
			{
				if ($user->img('icon_topic_attach', '') && !$extensions[$attachment['extension']]['upload_icon'])
				{
					$upload_icon = $user->img('icon_topic_attach', '');
				}
				else if ($extensions[$attachment['extension']]['upload_icon'])
				{
					$upload_icon = '<img src="' . $phpbb_root_path . $config['upload_icons_path'] . '/' . trim($extensions[$attachment['extension']]['upload_icon']) . '" alt="" />';
				}
			}

			$filesize = $attachment['filesize'];
			$size_lang = ($filesize >= 1048576) ? $user->lang['MB'] : ( ($filesize >= 1024) ? $user->lang['KB'] : $user->lang['BYTES'] );
			$filesize = ($filesize >= 1048576) ? round((round($filesize / 1048576 * 100) / 100), 2) : (($filesize >= 1024) ? round((round($filesize / 1024 * 100) / 100), 2) : $filesize);

			$comment = str_replace("\n", '<br />', censor_text($attachment['attach_comment']));

			$block_array += array(
				'UPLOAD_ICON'		=> $upload_icon,
				'FILESIZE'			=> $filesize,
				'SIZE_LANG'			=> $size_lang,
				'DOWNLOAD_NAME'		=> basename($attachment['real_filename']),
				'COMMENT'			=> $comment,
			);

			$denied = false;

			if (!isset($extensions['_allowed_'][$attachment['extension']]))
			{
				$denied = true;

				$block_array += array(
					'S_DENIED'			=> true,
					'DENIED_MESSAGE'	=> sprintf($user->lang['EXTENSION_DISABLED_AFTER_POSTING'], $attachment['extension'])
				);
			}

			if (!$denied)
			{
				$l_downloaded_viewed = $download_link = '';
				$display_cat = $extensions[$attachment['extension']]['display_cat'];

				if ($display_cat == ATTACHMENT_CATEGORY_IMAGE)
				{
					if ($attachment['thumbnail'])
					{
						$display_cat = ATTACHMENT_CATEGORY_THUMB;
					}
					else
					{
						if ($config['img_display_inlined'])
						{
							if ($config['img_link_width'] || $config['img_link_height'])
							{
								$dimension = @getimagesize($filename);

								// If the dimensions could not be determined or the image being 0x0 we display it as a link for safety purposes
								if ($dimension === false || empty($dimension[0]) || empty($dimension[1]))
								{
									$display_cat = ATTACHMENT_CATEGORY_NONE;
								}
								else
								{
									$display_cat = ($dimension[0] <= $config['img_link_width'] && $dimension[1] <= $config['img_link_height']) ? ATTACHMENT_CATEGORY_IMAGE : ATTACHMENT_CATEGORY_NONE;
								}
							}
						}
						else
						{
							$display_cat = ATTACHMENT_CATEGORY_NONE;
						}
					}
				}

				// Make some descisions based on user options being set.
				if (($display_cat == ATTACHMENT_CATEGORY_IMAGE || $display_cat == ATTACHMENT_CATEGORY_THUMB) && !$user->optionget('viewimg'))
				{
					$display_cat = ATTACHMENT_CATEGORY_NONE;
				}

				if ($display_cat == ATTACHMENT_CATEGORY_FLASH && !$user->optionget('viewflash'))
				{
					$display_cat = ATTACHMENT_CATEGORY_NONE;
				}

				$download_link = $this->build_url('download', array(intval($attachment['attach_id']), ''));

				switch ($display_cat)
				{
					// Images
					case ATTACHMENT_CATEGORY_IMAGE:
						$l_downloaded_viewed = 'VIEWED_COUNT';

						$inline_link = $this->build_url('download', array(intval($attachment['attach_id'])));
						$download_link = $this->build_url('download_type', array(intval($attachment['attach_id']), 'view'));

						$block_array += array(
							'S_IMAGE'		=> true,
							'U_INLINE_LINK'		=> $inline_link,
						);

						$update_count[] = $attachment['attach_id'];
					break;

					// Images, but display Thumbnail
					case ATTACHMENT_CATEGORY_THUMB:
						$l_downloaded_viewed = 'VIEWED_COUNT';

						$thumbnail_link = $this->build_url('download_thumb', array(intval($attachment['attach_id']), true));
						$download_link = $this->build_url('download_thumb_type', array(intval($attachment['attach_id']), true, 'view'));

						$block_array += array(
							'S_THUMBNAIL'		=> true,
							'THUMB_IMAGE'		=> $thumbnail_link,
						);
					break;

					// Windows Media Streams
					case ATTACHMENT_CATEGORY_WM:
						$l_downloaded_viewed = 'VIEWED_COUNT';

						// Giving the filename directly because within the wm object all variables are in local context making it impossible
						// to validate against a valid session (all params can differ)
						// $download_link = $filename;

						$block_array += array(
							'U_FORUM'		=> generate_board_url(),
							'ATTACH_ID'		=> $attachment['attach_id'],
							'S_WM_FILE'		=> true,
						);

						// Viewed/Heared File ... update the download count
						$update_count[] = $attachment['attach_id'];
					break;

					// Real Media Streams
					case ATTACHMENT_CATEGORY_RM:
					case ATTACHMENT_CATEGORY_QUICKTIME:
						$l_downloaded_viewed = 'VIEWED_COUNT';

						$block_array += array(
							'S_RM_FILE'			=> ($display_cat == ATTACHMENT_CATEGORY_RM) ? true : false,
							'S_QUICKTIME_FILE'	=> ($display_cat == ATTACHMENT_CATEGORY_QUICKTIME) ? true : false,
							'U_FORUM'			=> generate_board_url(),
							'ATTACH_ID'			=> $attachment['attach_id'],
						);

						// Viewed/Heared File ... update the download count
						$update_count[] = $attachment['attach_id'];
					break;

					// Macromedia Flash Files
					case ATTACHMENT_CATEGORY_FLASH:
						list($width, $height) = @getimagesize($filename);

						$l_downloaded_viewed = 'VIEWED_COUNT';

						$block_array += array(
							'S_FLASH_FILE'	=> true,
							'WIDTH'			=> $width,
							'HEIGHT'		=> $height,
							'U_VIEW_LINK'	=> $this->build_url('download_flash', array(intval($attachment['attach_id']), '')),
						);

						// Viewed/Heared File ... update the download count
						$update_count[] = $attachment['attach_id'];
					break;

					default:
						$l_downloaded_viewed = 'DOWNLOAD_COUNT';

						$block_array += array(
							'S_FILE'		=> true,
						);
					break;
				}

				$l_download_count = (!isset($attachment['download_count']) || $attachment['download_count'] == 0) ? $user->lang[$l_downloaded_viewed . '_NONE'] : (($attachment['download_count'] == 1) ? sprintf($user->lang[$l_downloaded_viewed], $attachment['download_count']) : sprintf($user->lang[$l_downloaded_viewed . 'S'], $attachment['download_count']));

				$block_array += array(
					'U_DOWNLOAD_LINK'		=> $download_link,
					'L_DOWNLOAD_COUNT'		=> $l_download_count
				);
			}

			$template->assign_block_vars('_file', $block_array);

			$compiled_attachments[] = $template->assign_display('attachment_tpl');
		}

		$attachments = $compiled_attachments;
		unset($compiled_attachments);

		$tpl_size = sizeof($attachments);

		$unset_tpl = array();

		preg_match_all('#<!\-\- ia([0-9]+) \-\->(.*?)<!\-\- ia\1 \-\->#', $message, $matches, PREG_PATTERN_ORDER);

		$replace = array();
		foreach ($matches[0] as $num => $capture)
		{
			// Flip index if we are displaying the reverse way
			$index = ($config['display_order']) ? ($tpl_size-($matches[1][$num] + 1)) : $matches[1][$num];

			$replace['from'][] = $matches[0][$num];
			$replace['to'][] = (isset($attachments[$index])) ? $attachments[$index] : sprintf($user->lang['MISSING_INLINE_ATTACHMENT'], $matches[2][array_search($index, $matches[1])]);

			$unset_tpl[] = $index;
		}

		if (isset($replace['from']))
		{
			$message = str_replace($replace['from'], $replace['to'], $message);
		}

		$unset_tpl = array_unique($unset_tpl);

		// Needed to let not display the inlined attachments at the end of the post again
		foreach ($unset_tpl as $index)
		{
			unset($attachments[$index]);
		}
	}

	public function remove_attachment($filedata)
	{
		global $db, $phpbb_root_path;

		$sql = 'DELETE FROM ' . TRACKER_ATTACHMENTS_TABLE. '
			WHERE attach_id = ' . $filedata['attach_id'];
		$db->sql_query($sql);

		if (isset($filedata['physical_filename']))
		{
			$filename = basename($filedata['physical_filename']);
			return @unlink($phpbb_root_path . $this->config['attachment_path'] . '/' . $filename);
		}

		return true;
	}
}

?>