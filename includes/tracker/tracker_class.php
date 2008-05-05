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
}

?>