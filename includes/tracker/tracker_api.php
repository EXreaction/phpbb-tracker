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
	public $config		= array();
	public $projects	= array();
	public $extensions	= array();
	public $types		= array();
	public $status		= array();

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

	public function __construct()
	{
		global $phpbb_root_path, $phpEx;

		$this->cache = new tracker_cache();

		$this->config		= $this->cache->obtain_tracker_config();
		$this->projects		= $this->cache->obtain_tracker_projects();
		$this->extensions	= $this->cache->obtain_attach_extensions(TRACKER_EXTENSION_ID);

		$this->types = include($phpbb_root_path . 'includes/tracker/tracker_types.' . $phpEx);
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

	public function add_attachment($form_name, &$errors)
	{
		global $auth, $phpbb_root_path, $cache, $config, $db, $user, $phpEx;

		// Init upload class
		$user->add_lang(array('posting', 'viewtopic'));

		if (!$config['allow_attachments'])
		{
			$errors[] = $user->lang['ATTACHMENT_public functionALITY_DISABLED'];
			return;
		}

		if (!class_exists('fileupload'))
		{
			include($phpbb_root_path . 'includes/functions_upload.' . $phpEx);
		}

		$upload = new fileupload();

		$upload->set_allowed_extensions(array_keys($this->extensions['_allowed_']));

		if (!empty($_FILES[$form_name]['name']))
		{
			$file = $upload->form_upload($form_name);
		}
		else
		{
			$errors[] = $user->lang['NO_UPLOAD_FORM_FOUND'];
			return;
		}

		$cat_id = (isset($this->extensions[$file->get('extension')]['display_cat'])) ? $this->extensions[$file->get('extension')]['display_cat'] : ATTACHMENT_CATEGORY_NONE;

		// Make sure the image category only holds valid images...
		if ($cat_id == ATTACHMENT_CATEGORY_IMAGE && !$file->is_image())
		{
			$file->remove();

			// If this error occurs a user tried to exploit an IE Bug by renaming extensions
			// Since the image category is displaying content inline we need to catch this.
			trigger_error($user->lang['ATTACHED_IMAGE_NOT_IMAGE']);
		}


		// Check Image Size, if it is an image
		if (!$auth->acl_get('a_tracker') && $cat_id == ATTACHMENT_CATEGORY_IMAGE)
		{
			$file->upload->set_allowed_dimensions(0, 0, $config['img_max_width'], $config['img_max_height']);
		}

		// Admins are allowed to exceed the allowed filesize
		if (!$auth->acl_get('a_tracker'))
		{
			if (!empty($this->extensions[$file->get('extension')]['max_filesize']))
			{
				$allowed_filesize = $this->extensions[$file->get('extension')]['max_filesize'];
			}
			else
			{
				$allowed_filesize = $config['max_filesize'];
			}

			$file->upload->set_max_filesize($allowed_filesize);
		}

		$file->clean_filename('unique', $user->data['user_id'] . '_');

		// Move file and overwrite any existing image
		$file->move_file($this->config['attachment_path'], true, true, 0755);

		if (sizeof($file->error))
		{
			$file->remove();
			$errors = array_merge($errors, $file->error);
			return array();
		}

		$filedata = array(
			'poster_id'			=> $user->data['user_id'],
			'filesize'			=> $file->get('filesize'),
			'mimetype'			=> $file->get('mimetype'),
			'extension'			=> $file->get('extension'),
			'physical_filename' => $file->get('realname'),
			'real_filename'		=> $file->get('uploadname'),
			'filetime'			=> time(),
		);

		$sql = 'INSERT INTO ' . TRACKER_ATTACHMENTS_TABLE . ' ' .
			$db->sql_build_array('INSERT', $filedata);
		$db->sql_query($sql);

		$filedata['attach_id'] = $db->sql_nextid();

		return $filedata;
	}

	public function posting_gen_attachment_data($filedata)
	{
		global $template, $user, $cache, $phpbb_root_path, $phpEx;

		$user->add_lang('posting');

		$template->assign_var('S_HAS_ATTACHMENTS', true);

		$hidden = '';
		$filedata['real_filename'] = basename($filedata['real_filename']);

		foreach ($filedata as $key => $value)
		{
			$hidden .= '<input type="hidden" name="attachment_data[' . $key . ']" value="' . $value . '" />';
		}

		$download_link = append_sid("{$phpbb_root_path}tracker.$phpEx", "mode=download&amp;id={$filedata['attach_id']}");

		$template->assign_vars(array(
			'FILENAME'			=> basename($filedata['real_filename']),
			'A_FILENAME'		=> addslashes(basename($filedata['real_filename'])),
			'ATTACH_ID'			=> $filedata['attach_id'],

			'U_VIEW_ATTACHMENT'	=> $download_link,
			'S_HIDDEN'			=> $hidden,
		));
	}

	public function display_ticket_attachment($attachment)
	{
		global $user, $template, $config, $phpbb_root_path, $phpEx, $cache;

		$upload_icon = '';

		$download_type = '';
		if ($this->extensions[$attachment['extension']]['display_cat'] == ATTACHMENT_CATEGORY_IMAGE)
		{
			$download_type .= '&amp;type=view';
		}

		$u_download_link = append_sid("{$phpbb_root_path}tracker.$phpEx", "mode=download&amp;id={$attachment['attach_id']}$download_type");

		if (isset($this->extensions[$attachment['extension']]))
		{
			if ($user->img('icon_topic_attach', '') && !$this->extensions[$attachment['extension']]['upload_icon'])
			{
				$upload_icon = $user->img('icon_topic_attach', '');
			}
			else if ($this->extensions[$attachment['extension']]['upload_icon'])
			{
				$upload_icon = '<img src="' . $phpbb_root_path . $config['upload_icons_path'] . '/' . trim($this->extensions[$attachment['extension']]['upload_icon']) . '" alt="" />';
			}
		}

		$template->assign_vars(array(
			'S_SHOW_ATTACHMENTS'	=> true,
			'U_DOWNLOAD_LINK'		=> $u_download_link,

			'UPLOAD_ICON'			=> $upload_icon,
			'FILESIZE'				=> $attachment['filesize'],
			'SIZE_LANG'				=> get_formatted_filesize($attachment['filesize']),
			'DOWNLOAD_NAME'			=> basename($attachment['real_filename']),
		));
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


	public function update_attachment($filedata, $ticket_id, $post_id = 0)
	{
		global $db;

		$data = array(
			'ticket_id'		=> $ticket_id,
			'post_id'		=> $post_id,
			'is_orphan'		=> false,
		);

		$sql = 'UPDATE ' . TRACKER_ATTACHMENTS_TABLE . '
			SET ' . $db->sql_build_array('UPDATE', $data) . '
			WHERE ' . $db->sql_in_set('attach_id', $filedata['attach_id']);
		$db->sql_query($sql);

	}

	/**
	* Add a project to the bug tracker
	* @param array $data array containing data to insert into projects table
	*/
	public function add_project($data)
	{
		global $db, $cache;

		$sql = 'INSERT INTO ' . TRACKER_PROJECT_TABLE . ' ' .
			$db->sql_build_array('INSERT', $data);
		$db->sql_query($sql);

		$cache->destroy('_tracker_projects');
	}

	/**
	* Update an existing project in the bug tracker
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

		$sql = 'DELETE FROM ' . TRACKER_VERSION_TABLE. '
			WHERE project_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_COMPONENTS_TABLE. '
			WHERE project_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_PROJECT_TABLE. '
			WHERE project_id = ' . $id;
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

		$sql = 'SELECT *
			FROM ' . TRACKER_PROJECT_TABLE . '
				ORDER BY project_type ASC, project_name_clean ASC';
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

			'WHERE'		=> 'a.is_orphan = 1',

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
				if (!in_array($file, array('.', '..', 'index.htm', 'index.html'), true))
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
	public function set_project_enabled($project_id, $action)
	{
		global $db, $cache;

		if (!$project_id)
		{
			return;
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

		$sql = 'UPDATE ' . TRACKER_PROJECT_TABLE . '
			SET project_enabled = ' . $status . '
			WHERE project_id = ' . (int) $project_id;
		$db->sql_query($sql);

		$cache->destroy('_tracker_projects');
	}

	/**
	* Add a to the bug tracker
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

		$sql = 'SELECT attach_id FROM ' . TRACKER_ATTACHMENTS_TABLE . '
			WHERE ticket_id = ' . $id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		if (sizeof($row))
		{
			foreach ($row as $item)
			{
				$this->remove_attachment($item);
			}
		}

		$sql = 'DELETE FROM ' . TRACKER_TICKETS_TABLE. '
			WHERE ticket_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_HISTORY_TABLE. '
			WHERE ticket_id = ' . $id;
		$db->sql_query($sql);

		$sql = 'DELETE FROM ' . TRACKER_POSTS_TABLE. '
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
	public function move_ticket($project_id, $ticket_id)
	{
		global $auth, $user, $db, $template;
		global $phpEx, $phpbb_root_path;

		if (!isset($this->projects[$project_id]))
		{
			trigger_error('TRACKER_PROJECT_NO_EXIST');
		}

		if (confirm_box(true))
		{
			$to_project_id = request_var('to_project_id', 0);
			if ($to_project_id)
			{
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
				$message .= sprintf($user->lang['TRACKER_REPLY_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$to_project_id&amp;t=$ticket_id") . '">', '</a>') . '<br /><br />';
				$message .= sprintf($user->lang['TRACKER_MOVED_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id") . '">', '</a>') . '<br /><br />';
				$message .= sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$to_project_id") . '">', '</a>') . '<br /><br />';
				$message .= sprintf($user->lang['TRACKER_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx") . '">', '</a>') . '<br /><br />';
				$message .= sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid("{$phpbb_root_path}index.$phpEx"). '">', '</a>');

				trigger_error($message);
			}
		}
		else
		{
			$template->assign_vars(array(
				'S_PROJECT_SELECT'		=> $this->project_select_options($this->get_projects(), $project_id),
			));

			confirm_box(false, '', build_hidden_fields(array(
				'p'					=> $project_id,
				't'					=> $ticket_id,
				'submit_mod'		=> true,
				'action'			=> 'move',
			)), 'tracker/tracker_move.html');
		}

	}

	/**
	* Sets lock/unlock of ticket
	* @param string $action value must be either 'lock' or 'unlock'
	* @param int $ticket_id id of ticket
	*/
	public function lock_unlock($action, $ticket_id)
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
	public function hide_unhide($action, $ticket_id)
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

		$sql = 'SELECT attach_id FROM ' . TRACKER_ATTACHMENTS_TABLE . '
			WHERE post_id = ' . $id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		if (sizeof($row))
		{
			foreach ($row as $item)
			{
				$this->remove_attachment($item);
			}
		}

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

	/**
	*Send email notification to required parties
	*/
	public function send_notification($data, $type)
	{
		global $phpbb_root_path, $phpEx, $user, $config, $db;

		if (!$this->config['send_email'])
		{
			return;
		}

		$subject = $email_template = $email_address = '';
		$email_template_vars = array();
		$board_url = generate_board_url() . '/';

		switch ($type)
		{
			case TRACKER_EMAIL_NOTIFY:

				$email_template = 'tracker_notify';

				$sql = 'SELECT project_name
							FROM ' . TRACKER_PROJECT_TABLE . '
						WHERE project_id = ' . $data['project_id'];
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$subject = $this->format_subject($data['ticket_title'], $data['project_id'], $data['ticket_id']);
				$email_address = $user->data['user_email'];
				strip_bbcode($data['ticket_desc'], $data['ticket_desc_uid']);
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> "{$board_url}tracker.$phpEx?p={$data['project_id']}&t={$data['ticket_id']}",
					'TICKET_ID'			=> $data['ticket_id'],
					'PROJECT_NAME'		=> htmlspecialchars_decode($row['project_name']),
					'TICKET_TITLE'		=> htmlspecialchars_decode($data['ticket_title']),
					'TICKET_DESC'		=> $this->format_desc($data['ticket_desc']),
					'TRACKER_URL'		=> "{$board_url}tracker.$phpEx",
					'TRACKER_TYPE'		=> $this->get_type_option('title', $data['project_id']),
					'SITE_NAME'			=> htmlspecialchars_decode($config['sitename']),
				);

			break;

			case TRACKER_EMAIL_NOTIFY_COMMENT:

				$email_template = 'tracker_notify_comment';

				$sql = 'SELECT ticket_user_id, ticket_title, ticket_hidden, status_id, project_id
							FROM ' . TRACKER_TICKETS_TABLE . '
						WHERE ticket_id = ' . $data['ticket_id'];
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				// We don't need to let the user know if they post in there own ticket
				if ($row['ticket_user_id'] == $user->data['user_id'])
				{
					return;
				}

				// If ticket is hidden then we won't notify anyone who is not project member
				if ($row['ticket_hidden'] == TRACKER_TICKET_HIDDEN)
				{
					if (!group_memberships($this->projects[$row['project_id']]['project_group'], $row['ticket_user_id'], true))
					{
						return;
					}
				}

				$sql = 'SELECT username, user_email
							FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $row['ticket_user_id'];
				$result = $db->sql_query($sql);
				$user_row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				$subject = $this->format_subject($row['ticket_title'], $row['project_id'], $data['ticket_id']);
				$email_address = $user_row['user_email'];
				strip_bbcode($data['post_desc'], $data['post_desc_uid']);
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user_row['username']),
					'CHANGE_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> $board_url . 'tracker.' . $phpEx . "?p={$row['project_id']}&t={$data['ticket_id']}",
					'TICKET_ID'			=> $data['ticket_id'],
					'TICKET_STATUS'		=> $this->set_status($row['status_id']),
					'TICKET_TITLE'		=> htmlspecialchars_decode($row['ticket_title']),
					'TICKET_DESC'		=> $this->format_desc($data['post_desc']),
					'TRACKER_URL'		=> $board_url . 'tracker.' . $phpEx,
					'TRACKER_TYPE'		=> $this->get_type_option('title', $row['project_id']),
					'SITE_NAME'			=> htmlspecialchars_decode($config['sitename']),
				);

			break;

			case TRACKER_EMAIL_NOTIFY_STATUS_SINGLE:

				$email_template = 'tracker_notify_status_single';

				$sql = 'SELECT ticket_user_id, ticket_title, status_id, ticket_hidden, project_id
							FROM ' . TRACKER_TICKETS_TABLE . '
						WHERE ticket_id = ' . $data['ticket_id'];
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				// If ticket is hidden then we won't notify anyone who is not project member
				if ($row['ticket_hidden'] == TRACKER_TICKET_HIDDEN)
				{
					if (!group_memberships($this->projects[$row['project_id']]['project_group'], $row['ticket_user_id'], true))
					{
						return;
					}
				}

				$sql = 'SELECT username, user_email
							FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $row['ticket_user_id'];
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
							$old_name = $this->set_lang_name($this->projects[$row['project_id']]['group_name']);
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
							$new_name = $this->set_lang_name($this->projects[$row['project_id']]['group_name']);
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

				$subject = $this->format_subject($row['ticket_title'], $row['project_id'], $data['ticket_id']);
				$email_address = $user_row['user_email'];
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user_row['username']),
					'CHANGE_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> $board_url . 'tracker.' . $phpEx . "?p={$row['project_id']}&t={$data['ticket_id']}",
					'TICKET_ID'			=> $data['ticket_id'],
					'TICKET_TITLE'		=> htmlspecialchars_decode($row['ticket_title']),
					'TRACKER_URL'		=> $board_url . 'tracker.' . $phpEx,
					'TRACKER_TYPE'		=> $this->get_type_option('title', $row['project_id']),
					'SITE_NAME'			=> htmlspecialchars_decode($config['sitename']),
					'FIELD_NAME1'		=> htmlspecialchars_decode($field_name),
					'OLD_VALUE1'		=> htmlspecialchars_decode($old_value),
					'NEW_VALUE1'		=> htmlspecialchars_decode($new_value),
				);
			break;

			case TRACKER_EMAIL_NOTIFY_STATUS_DOUBLE:

				$email_template = 'tracker_notify_status_double';

				$sql = 'SELECT ticket_user_id, ticket_title, ticket_hidden, status_id, project_id
							FROM ' . TRACKER_TICKETS_TABLE . '
						WHERE ticket_id = ' . $data['ticket_id'];
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				// If ticket is hidden then we won't notify anyone who is not project member
				if ($row['ticket_hidden'] == TRACKER_TICKET_HIDDEN)
				{
					if (!group_memberships($this->projects[$row['project_id']]['project_group'], $row['ticket_user_id'], true))
					{
						return;
					}
				}

				$sql = 'SELECT username, user_email
							FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $row['ticket_user_id'];
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
					$old_name = $this->set_lang_name($this->projects[$row['project_id']]['group_name']);
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
					$new_name = $this->set_lang_name($this->projects[$row['project_id']]['group_name']);
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

				$subject = $this->format_subject($row['ticket_title'], $row['project_id'], $data['ticket_id']);
				$email_address = $user_row['user_email'];
				$email_template_vars = array(
					'USERNAME'			=> htmlspecialchars_decode($user_row['username']),
					'CHANGE_USERNAME'	=> htmlspecialchars_decode($user->data['username']),
					'TICKET_URL'		=> $board_url . 'tracker.' . $phpEx . "?p={$row['project_id']}&t={$data['ticket_id']}",
					'TICKET_ID'			=> $data['ticket_id'],
					'TICKET_TITLE'		=> htmlspecialchars_decode($row['ticket_title']),
					'TRACKER_URL'		=> $board_url . 'tracker.' . $phpEx,
					'TRACKER_TYPE'		=> $this->get_type_option('title', $row['project_id']),
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

		include_once($phpbb_root_path . 'includes/functions_messenger.'.$phpEx);
		$messenger = new messenger(false);

		$messenger->subject($subject);
		$messenger->template($email_template, 'en');
		$messenger->to($email_address);

		$messenger->assign_vars($email_template_vars);

		$messenger->send(NOTIFY_EMAIL);
	}

	public function process_notification($data, $ticket)
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
			$this->send_notification($history_data, TRACKER_EMAIL_NOTIFY_STATUS_SINGLE);
		}
		else if ($history_ts && !$history_at)
		{
			$this->send_notification($history_status, TRACKER_EMAIL_NOTIFY_STATUS_SINGLE);
		}
		else if ($history_at && $history_ts)
		{
			$history_data['history_old_assigned_to'] = $ticket['ticket_assigned_to'];
			$this->send_notification(array_merge($history_data, $history_status), TRACKER_EMAIL_NOTIFY_STATUS_DOUBLE);
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

		$subject = sprintf($user->lang['TRACKER_EMAIL_SUBJECT'], $config['sitename'], $this->get_type_option('title', $project_id), $ticket_id, $subject);

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
		global $db;

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

		return (!empty($user->lang[$name])) ? $user->lang[$name] : $name;
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
	public function set_filter($type)
	{
		global $db;

		$filter = '';
		switch ($type)
		{
			case TRACKER_ALL:
			break;

			case TRACKER_ALL_OPENED:
				$filter = ' AND ' . $db->sql_in_set('status_id', $this->get_opened());
			break;

			case TRACKER_ALL_CLOSED:
				$filter = ' AND ' . $db->sql_in_set('status_id', $this->get_opened(), true);
			break;

			default:
				$filter = ' AND status_id = ' . $type;
			break;
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

	/**
	* Display select options for priorities, severities, components and versions
	*/
	public function select_options($project_id, $mode, $selected_id = false)
	{
		global $db, $user;

		switch ($mode)
		{
			case 'priority':
			case 'severity':
			break;

			case 'version':
				$table = TRACKER_VERSION_TABLE;
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
				WHERE project_id = ' . $project_id . '
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
			'FORUM_NAME'	=> $data['project_name'],
			'U_VIEW_FORUM'	=> append_sid("{$phpbb_root_path}tracker.$phpEx", (($in_stats) ? 'mode=statistics&amp;' : '' ) . 'p=' . $data['project_id']),
		));

		$template->assign_vars(array(
			'TRACKER_TICKET_ID'		=> $ticket_id,
			'U_VIEW_TRACKER_TICKET'	=> append_sid("{$phpbb_root_path}tracker.$phpEx", 'p=' . $data['project_id'] . '&amp;t=' . $ticket_id),
		));

		return;
	}

	/**
	* Displays a review of previous ticket posts
	*/
	public function display_review($ticket_id)
	{
		global $db, $user, $template, $phpEx, $phpbb_root_path;

		$template->assign_var('S_DISPLAY_REVIEW', true);

		$sql_array = array(
			'SELECT'	=> 't.*,
							u.user_colour,
							u.username',

			'FROM'		=> array(
				TRACKER_TICKETS_TABLE	=> 't',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 't.ticket_user_id = u.user_id',
				),
			),

			'WHERE'		=> 't.ticket_id = ' . $ticket_id,

		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);
		$ticket_row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$sql_array = array(
			'SELECT'	=> 'p.*,
							u.user_colour,
							u.username',

			'FROM'		=> array(
				TRACKER_POSTS_TABLE	=> 'p',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 'p.post_user_id = u.user_id',
				),
			),

			'WHERE'		=> 'p.ticket_id = ' . $ticket_id,

			'ORDER_BY'	=>	'p.post_time DESC',

		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);
		$posts_row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$review_array = array();

		foreach ($posts_row as $row)
		{
			$review_array[] = array (
				'user_id'		=> $row['post_user_id'],
				'user_colour'	=> $row['user_colour'],
				'username'		=> $row['username'],
				'text'			=> $row['post_desc'],
				'uid'			=> $row['post_desc_uid'],
				'bitfield'		=> $row['post_desc_bitfield'],
				'options'		=> $row['post_desc_options'],
				'time'			=> $row['post_time'],
			);
		}

		foreach ($ticket_row as $row)
		{
			$review_array[] = array (
				'user_id'		=> $row['ticket_user_id'],
				'user_colour'	=> $row['user_colour'],
				'username'		=> $row['username'],
				'text'			=> $row['ticket_desc'],
				'uid'			=> $row['ticket_desc_uid'],
				'bitfield'		=> $row['ticket_desc_bitfield'],
				'options'		=> $row['ticket_desc_options'],
				'time'			=> $row['ticket_time'],
			);
		}

		foreach ($review_array as $review)
		{
			$template->assign_block_vars('review', array(
				'POST_USER'		=> get_username_string('full', $review['user_id'], $review['username'], $review['user_colour']),
				'POST_TIME'		=> $user->format_date($review['time']),
				'POST_TEXT'		=> generate_text_for_display($review['text'], $review['uid'], $review['bitfield'], $review['options']),
			));
		}

	}

	/**
	* Displays a tickets history
	*/
	public function display_history($ticket_id, $project_id)
	{
		global $db, $user, $template, $phpEx, $phpbb_root_path;

		$template->assign_var('S_DISPLAY_HISTORY', true);

		$sql_array = array(
			'SELECT'	=> 'h.*,
							u1.user_colour as history_user_colour,
							u1.username as history_username,
							u2.user_colour as history_assigned_to_user_colour,
							u2.username as history_assigned_to_username',

			'FROM'		=> array(
				TRACKER_HISTORY_TABLE	=> 'h',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u1'),
					'ON'	=> 'h.history_user_id = u1.user_id',
				),
				array(
					'FROM'	=> array(USERS_TABLE => 'u2'),
					'ON'	=> 'h.history_assigned_to = u2.user_id',
				),
			),

			'WHERE'		=> 'h.ticket_id = ' . $ticket_id,

			'ORDER_BY'	=> 'h.history_time DESC',
		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			switch ($row['history_status'])
			{
				case TRACKER_HISTORY_ASSIGNED_TO:
					$history_action = $this->get_assigned_to($project_id, $row['history_assigned_to'], $row['history_assigned_to_username'], $row['history_assigned_to_user_colour'], 'history');
				break;

				case TRACKER_HISTORY_STATUS_CHANGED:
					$history_action = sprintf($user->lang['TRACKER_HISTORY_STATUS_CHANGED'], $this->set_status($row['history_old_status']), $this->set_status($row['history_new_status']));
				break;

				case TRACKER_HISTORY_SEVERITY_CHANGED:
					$history_action = sprintf($user->lang['TRACKER_HISTORY_SEVERITY_CHANGED'], (!isset($this->severity[$row['history_old_severity']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->set_lang_name($this->severity[$row['history_old_severity']]), (!isset($this->severity[$row['history_new_severity']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->set_lang_name($this->severity[$row['history_new_severity']]));
				break;

				case TRACKER_HISTORY_PRIORITY_CHANGED:
					$history_action = sprintf($user->lang['TRACKER_HISTORY_PRIORITY_CHANGED'], (!isset($this->priority[$row['history_old_priority']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->set_lang_name($this->priority[$row['history_old_priority']]), (!isset($this->priority[$row['history_new_priority']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->set_lang_name($this->priority[$row['history_new_priority']]));
				break;

				default:
					trigger_error('NO_MODE');
				break;
			}

			$template->assign_block_vars('history', array(
				'HISTORY_ACTION'		=> $history_action,
				'HISTORY_ACTION_BY'		=> sprintf($user->lang['TRACKER_HISTORY_ACTION_BY'], get_username_string('full', $row['history_user_id'], $row['history_username'], $row['history_user_colour']), $user->format_date($row['history_time'])),
			));
		}
		$db->sql_freeresult($result);
	}

	/**
	* Displays a tickets comments/posts
	*/
	public function display_comments($ticket_id, $project_id, $start = 0)
	{
		global $db, $user, $cache, $template, $phpEx, $phpbb_root_path, $config, $auth;

		$total_posts = $this->get_total('posts', $project_id, $ticket_id);
		$posts_per_page = $this->config['posts_per_page'];

		$template->assign_var('S_DISPLAY_COMMENTS', true);

		$sql_array = array(
			'SELECT'	=> 'p.*,
							a.attach_id,
							a.is_orphan,
							a.physical_filename,
							a.real_filename,
							a.extension,
							a.mimetype,
							a.filesize,
							a.filetime,
							u.user_colour,
							u.username',

			'FROM'		=> array(
				TRACKER_POSTS_TABLE	=> 'p',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(TRACKER_ATTACHMENTS_TABLE => 'a'),
					'ON'	=> 'p.post_id = a.post_id',
				),
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 'p.post_user_id = u.user_id',
				)
			),

			'WHERE'		=> 'p.ticket_id = ' . $ticket_id,

			'ORDER_BY'	=> 'p.post_time ASC',
		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query_limit($sql, $posts_per_page, $start);

		while ($row = $db->sql_fetchrow($result))
		{
			$upload_icon = $filesize = $size_lang = $u_download_link = '';
			if ($row['attach_id'])
			{
				$download_type = '';
				if ($this->extensions[$row['extension']]['display_cat'] == ATTACHMENT_CATEGORY_IMAGE)
				{
					$download_type .= '&amp;type=view';
				}

				$u_download_link = append_sid("{$phpbb_root_path}tracker.$phpEx", "mode=download&amp;id={$row['attach_id']}$download_type");

				if (isset($this->extensions[$row['extension']]))
				{
					if ($user->img('icon_topic_attach', '') && !$this->extensions[$row['extension']]['upload_icon'])
					{
						$upload_icon = $user->img('icon_topic_attach', '');
					}
					else if ($this->extensions[$row['extension']]['upload_icon'])
					{
						$upload_icon = '<img src="' . $phpbb_root_path . $config['upload_icons_path'] . '/' . trim($this->extensions[$row['extension']]['upload_icon']) . '" alt="" />';
					}
				}

				$filesize = $row['filesize'];
				$size_lang = ($filesize >= 1048576) ? $user->lang['MB'] : ( ($filesize >= 1024) ? $user->lang['KB'] : $user->lang['BYTES'] );
				$filesize = ($filesize >= 1048576) ? round((round($filesize / 1048576 * 100) / 100), 2) : (($filesize >= 1024) ? round((round($filesize / 1024 * 100) / 100), 2) : $filesize);
			}

			$template->assign_block_vars('comments', array(
				'S_CAN_DELETE'			=> $this->check_delete(),
				'U_DELETE'				=> append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;pid={$row['post_id']}&amp;mode=delete"),
				'S_CAN_EDIT'			=> $this->check_edit($row['post_time'], $row['post_user_id']),
				'U_EDIT'				=> append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;pid={$row['post_id']}&amp;mode=edit"),
				'COMMENT_POSTER'		=> get_username_string('full', $row['post_user_id'], $row['username'], $row['user_colour']),
				'COMMENT_TIME'			=> $user->format_date($row['post_time']),
				'COMMENT_DESC'			=> generate_text_for_display($row['post_desc'], $row['post_desc_uid'], $row['post_desc_bitfield'], $row['post_desc_options']),
				'EDITED_MESSAGE'		=> $this->fetch_edited_by($row, 'post'),
				'EDIT_REASON'			=> $row['edit_reason'],

				'S_DISPLAY_NOTICE'		=> (($auth->acl_get('u_tracker_download') && $row['attach_id']) || !$row['attach_id']) ? false : true,
				'S_SHOW_ATTACHMENTS'	=> ($auth->acl_get('u_tracker_download') && $row['attach_id']) ? true : false,
				'U_DOWNLOAD_LINK'		=> $u_download_link,

				'UPLOAD_ICON'			=> ($row['attach_id']) ? $upload_icon : '',
				'FILESIZE'				=> ($row['attach_id']) ? $filesize : '',
				'SIZE_LANG'				=> ($row['attach_id']) ? $size_lang : '',
				'DOWNLOAD_NAME'			=> ($row['attach_id']) ? basename($row['real_filename']) : '',
			));
		}
		$db->sql_freeresult($result);

		$l_total_posts = false;
		if ($total_posts == 1)
		{
			$l_total_posts = $total_posts . ' ' . $user->lang['POST'];
		}
		else if ($total_posts > 1)
		{
			$l_total_posts = $total_posts . ' ' . $user->lang['POSTS'];
		}

		$template->assign_vars(array(
			'PAGE_NUMBER'	=> ($posts_per_page > 0) ? on_page($total_posts, $posts_per_page, $start) : on_page($total_posts, $total_posts, $start),
			'TOTAL_POSTS'	=> $l_total_posts,
			'PAGINATION'	=> ($posts_per_page > 0) ? generate_pagination(append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id"), $total_posts, $posts_per_page, $start) : false,
		));
	}

	public function display_statistics($project_id)
	{
		global $db, $user, $cache, $template, $phpEx, $phpbb_root_path, $config, $auth;

		$template->assign_var('S_IN_STATS', true);
		if ($project_id)
		{
			//Get total open
			$sql = 'SELECT COUNT(ticket_id) as total
				FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $project_id . '
					AND ' . $db->sql_in_set('status_id', $this->get_opened());
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			$total_opened = $row['total'];

			//Get total closed
			$sql = 'SELECT COUNT(ticket_id) as total
				FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $project_id . '
					AND ' . $db->sql_in_set('status_id', $this->get_opened(), true);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			$total_closed = $row['total'];

			$template->assign_vars(array(
				'S_IN_PROJECT_STATS'	=> true,
				'L_TITLE'				=> $this->get_type_option('title', $project_id) . ' - ' . $this->projects[$project_id]['project_name'],

				'TOTAL_TICKETS'			=> $total_opened + $total_closed,
				'TOTAL_OPENED'			=> $total_opened,
				'TOTAL_CLOSED'			=> $total_closed,
			));

			$sql = 'SELECT status_id, COUNT(ticket_id) as total
				FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $project_id . '
				GROUP BY status_id
					ORDER BY status_id';
			$result = $db->sql_query($sql);

			$status_count = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$status_count[$row['status_id']] = $row['total'];
			}
			$db->sql_freeresult($result);

			foreach ($this->status as $item)
			{
				if ($item['filter'])
				{
					continue;
				}

				$template->assign_block_vars('status', array(
					'STATUS_TOTAL'		=> (isset($status_count[$item['id']])) ? $status_count[$item['id']] : 0,
					'STATUS_NAME'		=> $this->set_status($item['id']),
					'STATUS_CLOSED'		=> ($item['open']) ? $user->lang['NO'] : $user->lang['YES'],
				));
			}

			$sql_array = array(
				'SELECT'	=> 't.ticket_assigned_to,
								u.user_colour,
								u.username,
								u.username_clean,
								COUNT(t.ticket_id) as total_tickets',

				'FROM'		=> array(
					TRACKER_TICKETS_TABLE => 't',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(USERS_TABLE => 'u'),
						'ON'	=> 't.ticket_assigned_to = u.user_id',
					),
				),

				'WHERE'		=> 't.project_id = ' . $project_id,

				'GROUP_BY'	=> 't.ticket_assigned_to, u.user_colour, u.username, u.username_clean',

				'ORDER_BY'	=>	'total_tickets DESC, u.username_clean ASC',

			);

			$sql = $db->sql_build_query('SELECT', $sql_array);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			foreach ($row as $item)
			{
				if ($item['ticket_assigned_to'] == 0)
				{
					continue;
				}

				$template->assign_block_vars('assigne', array(
					'USERNAME'		=> $this->get_assigned_to($project_id, $item['ticket_assigned_to'], $item['username'], $item['user_colour']),
					'TOTAL'			=> $item['total_tickets'],
				));
			}

			$sql_array = array(
				'SELECT'	=> 't.ticket_user_id,
								u.user_colour,
								u.username,
								u.username_clean,
								COUNT(t.ticket_id) as total_tickets',

				'FROM'		=> array(
					TRACKER_TICKETS_TABLE => 't',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(USERS_TABLE => 'u'),
						'ON'	=> 't.ticket_user_id = u.user_id',
					),
				),

				'WHERE'		=> 't.project_id = ' . $project_id,

				'GROUP_BY'	=> 't.ticket_user_id, u.user_colour, u.username, u.username_clean',

				'ORDER_BY'	=>	'total_tickets DESC, u.username_clean ASC',

			);

			$sql = $db->sql_build_query('SELECT', $sql_array);
			$result = $db->sql_query_limit($sql, $this->config['top_reporters']);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			$template->assign_var('TRACKER_TOP_REPORTERS', sprintf($user->lang['TRACKER_TOP_REPORTERS_TITLE'], $this->config['top_reporters']));

			foreach ($row as $item)
			{
				if ($item['ticket_user_id'] == 0)
				{
					continue;
				}

				$template->assign_block_vars('top', array(
					'USERNAME'		=> $this->get_assigned_to($project_id, $item['ticket_user_id'], $item['username'], $item['user_colour']),
					'TOTAL'			=> $item['total_tickets'],
				));
			}

			// Get component stats
			$sql = 'SELECT component_id, COUNT(ticket_id) as total
				FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $project_id . '
				GROUP BY component_id
					ORDER BY component_id';
			$result = $db->sql_query($sql);

			$component_count = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$component_count[$row['component_id']] = $row['total'];
			}
			$db->sql_freeresult($result);

			$sql = 'SELECT component_id, component_name
				FROM ' . TRACKER_COMPONENTS_TABLE . '
				WHERE project_id = ' . $project_id . '
					ORDER BY component_name';
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			foreach ($row as $item)
			{
				$template->assign_block_vars('component', array(
					'COMPONENT_NAME'		=> $this->set_lang_name($item['component_name']),
					'TOTAL'					=> (isset($component_count[$item['component_id']])) ? $component_count[$item['component_id']] : 0,
				));
			}

			// Get version stats
			$sql = 'SELECT version_id, COUNT(ticket_id) as total
				FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $project_id . '
				GROUP BY version_id
					ORDER BY version_id';
			$result = $db->sql_query($sql);

			$version_count = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$version_count[$row['version_id']] = $row['total'];
			}
			$db->sql_freeresult($result);

			$sql = 'SELECT version_id, version_name
				FROM ' . TRACKER_VERSION_TABLE . '
				WHERE project_id = ' . $project_id . '
					ORDER BY version_name';
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			foreach ($row as $item)
			{
				$template->assign_block_vars('version', array(
					'VERSION_NAME'			=> $this->set_lang_name($item['version_name']),
					'TOTAL'					=> (isset($version_count[$item['version_id']])) ? $version_count[$item['version_id']] : 0,
				));
			}

			$this->generate_nav($this->projects[$project_id], false, true);
			// Output page
			page_header($user->lang['TRACKER_STATS'] . ' - ' . $this->get_type_option('title', $project_id) . ' - ' . $this->projects[$project_id]['project_name'], false);

		}
		else
		{
			$sql_array = array(
				'SELECT'	=> 'p.project_id,
								p.project_name,
								p.project_desc,
								p.project_name_clean,
								p.project_type,
								p.project_enabled,
								p.project_group,
								COUNT(t.ticket_id) as total_tickets',

				'FROM'		=> array(
					TRACKER_PROJECT_TABLE	=> 'p',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(TRACKER_TICKETS_TABLE => 't'),
						'ON'	=> 'p.project_id = t.project_id',
					),
				),

				'GROUP_BY'	=> 'p.project_id,
								p.project_name,
								p.project_desc,
								p.project_name_clean,
								p.project_type,
								p.project_enabled,
								p.project_group',

				'ORDER_BY'	=>	'p.project_type ASC, p.project_name_clean ASC',

			);

			$sql = $db->sql_build_query('SELECT', $sql_array);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			foreach ($row as $item)
			{
				if ($item['project_enabled'] == TRACKER_PROJECT_DISABLED)
				{
					if (!group_memberships($item['project_group'], $user->data['user_id'], true))
					{
						continue;
					}
				}

				$template->assign_block_vars('project', array(
					'U_PROJECT'			=> append_sid("{$phpbb_root_path}tracker.$phpEx", "mode=statistics&amp;p={$item['project_id']}"),
					'PROJECT_NAME'		=> $item['project_name'],
					'PROJECT_DESC'		=> $item['project_desc'],
					'PROJECT_TYPE'		=> $this->set_lang_name($this->types[$item['project_type']]['title']),
					'TOTAL_TICKETS'		=> (isset($item['total_tickets'])) ? $item['total_tickets'] : 0,
				));
			}

			// Output page
			page_header($user->lang['TRACKER_STATS'], false);

		}

		$template->set_filenames(array(
			'body' => 'tracker/tracker_stats_body.html')
		);

		page_footer();
	}

	/**
	* Returns the total of specified type either 'tickets' or 'posts'
	* Used for pagination
	* Follows phpbb3 $config['display_last_edited'] variable
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
				$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['edit_time']), $row['edit_count']);
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

	/*
	* Checks whether a user is allowed to delete posts/tickets
	*/
	public function check_delete()
	{
		global $auth;

		if ($auth->acl_get('a_tracker') || $auth->acl_get('u_tracker_delete_global') || ($auth->acl_get('u_tracker_delete_all') && $this->can_manage))
		{
			return true;
		}

		return false;

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
			$string = '<a style="color:#' . $this->projects[$project_id]['group_colour'] . '" href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . $this->projects[$project_id]['project_group']) . '" class="username-coloured">' . $this->set_lang_name($this->projects[$project_id]['group_name']) . '</a>';
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
}

?>