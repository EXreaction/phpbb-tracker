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
	public $url_builder;

	public $errors = array();

	public function __construct($in_tracker = true)
	{
		global $template, $user;
		global $phpbb_root_path, $phpEx;

		if (!function_exists('group_memberships'))
		{
			include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
		}

		// Do not change order of following includes
		include($phpbb_root_path . 'tracker/includes/constants.' . $phpEx);
		include($phpbb_root_path . 'tracker/includes/cache.' . $phpEx);
		include($phpbb_root_path . 'tracker/includes/api.' . $phpEx);

		// make an url builder object
		$this->url_builder = new tracker_url_builder();

		// make an api object
		$this->api = new tracker_api();
		$this->api->set_url_builder(array(&$this->url_builder, 'build'));

		// Add language vars to array
		$user->add_lang(array('mods/tracker', 'posting'));

		$template->assign_vars(array(
			'S_IN_TRACKER'				=> $in_tracker,
			'U_TRACKER' 				=> $this->api->build_url('index'),
			'U_TRACKER_STATS'			=> $this->api->build_url('statistics'),
		));

		if ($in_tracker)
		{
			$template->assign_block_vars('navlinks', array(
				'FORUM_NAME'   		=> $user->lang['TRACKER_INDEX'],
				'U_VIEW_FORUM'  	=> $this->api->build_url('index'),
			));
		}
	}

	/**
	 * Display the tracker index
	 */
	public function display_index($project_cat_id = false)
	{
		global $user, $template;

		if (!sizeof($this->api->projects))
		{
			trigger_error('TRACKER_NO_PROJECT_EXIST');
		}

		$display_project = false;
		$project_array = array();
		foreach ($this->api->projects as $item)
		{
			if ($item['project_enabled'] == TRACKER_PROJECT_DISABLED)
			{
				if (!group_memberships($item['project_group'], $user->data['user_id'], true))
				{
					continue;
				}
			}
			$project_array[$item['project_name_clean']][] =  $item;
		}

		foreach ($project_array as $projects)
		{
			$display_project = true;
			if ($project_cat_id && $project_cat_id != $projects[0]['project_cat_id'])
			{
				continue;
			}

			$template->assign_block_vars('cat', array(
				'PROJECT_NAME'		=> $projects[0]['project_name'],
				'U_PROJECT' 		=> $this->api->build_url('project_cat', array($projects[0]['project_cat_id'])),
			));

			foreach ($projects as $item)
			{
				$template->assign_block_vars('cat.project', array(
					'PROJECT_TYPE'				=> $this->api->set_lang_name($this->api->types[$item['project_type']]['title']),
					'PROJECT_DESC'				=> $item['project_desc'],
					'U_PROJECT_STATISTICS'		=> $this->api->build_url('statistics_p', array($item['project_id'])),
					'U_PROJECT' 				=> $this->api->build_url('project', array($item['project_id'])),
				));
			}
		}

		if ($project_cat_id)
		{
			$template->assign_block_vars('navlinks', array(
				'FORUM_NAME'   		=> $this->api->project_cats[$project_cat_id]['project_name'],
				'U_VIEW_FORUM'  	=> $this->api->build_url('project_cat', array($project_cat_id)),
			));
		}

		
		// Assign index specific vars
		$template->assign_vars(array(
			'S_DISPLAY_PROJECT'			=> $display_project,

			'TRACKER_PROJECTS'			=> sprintf($user->lang['TRACKER_PROJECTS'], '<a href="' . $this->api->build_url('statistics') . '">','</a>' ),
		));

		
		// Output page
		page_header($user->lang['TRACKER'], false);
		
		$template->set_filenames(array(
			'body' => 'tracker/tracker_index_body.html')
		);

		page_footer();
	}

	/**
	* Displays a tickets comments/posts
	*/
	public function display_comments($ticket_id, $project_id, $start = 0)
	{
		global $db, $user, $cache, $template, $phpEx, $phpbb_root_path, $config, $auth;

		$total_posts = $this->api->get_total('posts', $project_id, $ticket_id);
		$posts_per_page = $this->api->config['posts_per_page'];

		$template->assign_var('S_DISPLAY_COMMENTS', true);

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
				)
			),

			'WHERE'		=> 'p.ticket_id = ' . $ticket_id,

			'ORDER_BY'	=> 'p.post_time ASC',
		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query_limit($sql, $posts_per_page, $start);

		$comment_data = $post_ids = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$comment_data[$row['post_id']] = $row;
			if ($row['post_attachment'])
			{
				$post_ids[] = $row['post_id'];
			}
		}
		$db->sql_freeresult($result);

		if ($auth->acl_get('u_tracker_download') && sizeof($post_ids))
		{
			unset($this->api->attachment_data_post);
			$this->api->get_attachment_data($ticket_id, $post_ids);
			if (sizeof($this->api->attachment_data_post))
			{
				foreach ($this->api->attachment_data_post as $key => $value)
				{
					if (isset($comment_data[$key]))
					{
						$comment_data[$key]['attachment_data'] = $value;
					}
				}
			}
			unset($this->api->attachment_data_post);
		}

		foreach ($comment_data as $row)
		{
			$comment_desc = generate_text_for_display($row['post_desc'], $row['post_desc_uid'], $row['post_desc_bitfield'], $row['post_desc_options']);
			if (isset($row['attachment_data']))
			{
				$update_count = array();
				$this->api->parse_attachments_for_view($comment_desc, $row['attachment_data'], $update_count);
			}

			$template->assign_block_vars('comments', array(
				'S_HAS_ATTACHMENTS'		=> (!empty($row['attachment_data'])) ? true : false,
				'S_CAN_DELETE'			=> $this->check_permission('delete', true),
				'U_DELETE'				=> $this->api->build_url('delete_pid', array($project_id, $ticket_id, $row['post_id'])),
				'S_CAN_EDIT'			=> $this->api->check_edit($row['post_time'], $row['post_user_id']),
				'U_EDIT'				=> $this->api->build_url('edit_pid', array($project_id, $ticket_id, $row['post_id'])),
				'COMMENT_DESC'			=> $comment_desc,
				'COMMENT_POSTER'		=> ($row['post_user_id'] != ANONYMOUS) ? get_username_string('full', $row['post_user_id'], $row['username'], $row['user_colour']) :  get_username_string('full', $row['post_user_id'], $row['username'], $row['user_colour'], $row['post_username']),
				'COMMENT_TIME'			=> $user->format_date($row['post_time']),
				'EDITED_MESSAGE'		=> $this->api->fetch_edited_by($row, 'post'),
				'EDIT_REASON'			=> $row['edit_reason'],
				'POST_ID'				=> $row['post_id'],
			));

			if (!empty($row['attachment_data']))
			{
				foreach ($row['attachment_data'] as $attach_row)
				{
					$template->assign_block_vars('comments.attachment', array(
						'DISPLAY_ATTACHMENT' => $attach_row,
					));
				}
			}
		}

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
			'POST_IMG'		=> $user->img('icon_post_target', ''),
			'PAGE_NUMBER'	=> ($posts_per_page > 0) ? on_page($total_posts, $posts_per_page, $start) : on_page($total_posts, $total_posts, $start),
			'TOTAL_POSTS'	=> $l_total_posts,
			'PAGINATION'	=> ($posts_per_page > 0) ? generate_pagination($this->api->build_url('ticket', array($project_id, $ticket_id)), $total_posts, $posts_per_page, $start) : false,
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
					$history_action = $this->api->get_assigned_to($project_id, $row['history_assigned_to'], $row['history_assigned_to_username'], $row['history_assigned_to_user_colour'], 'history');
				break;

				case TRACKER_HISTORY_STATUS_CHANGED:
					$history_action = sprintf($user->lang['TRACKER_HISTORY_STATUS_CHANGED'], $this->api->set_status($row['history_old_status']), $this->api->set_status($row['history_new_status']));
				break;

				case TRACKER_HISTORY_SEVERITY_CHANGED:
					$history_action = sprintf($user->lang['TRACKER_HISTORY_SEVERITY_CHANGED'], (!isset($this->api->severity[$row['history_old_severity']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->api->set_lang_name($this->api->severity[$row['history_old_severity']]), (!isset($this->api->severity[$row['history_new_severity']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->api->set_lang_name($this->api->severity[$row['history_new_severity']]));
				break;

				case TRACKER_HISTORY_PRIORITY_CHANGED:
					$history_action = sprintf($user->lang['TRACKER_HISTORY_PRIORITY_CHANGED'], (!isset($this->api->priority[$row['history_old_priority']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->api->set_lang_name($this->api->priority[$row['history_old_priority']]), (!isset($this->api->priority[$row['history_new_priority']])) ? $user->lang['TRACKER_UNKNOWN'] : $this->api->set_lang_name($this->api->priority[$row['history_new_priority']]));
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
				'user_id'			=> $row['post_user_id'],
				'user_colour'		=> $row['user_colour'],
				'username'			=> $row['username'],
				'post_username'		=> $row['post_username'],
				'text'				=> $row['post_desc'],
				'uid'				=> $row['post_desc_uid'],
				'bitfield'			=> $row['post_desc_bitfield'],
				'options'			=> $row['post_desc_options'],
				'time'				=> $row['post_time'],
			);
		}

		foreach ($ticket_row as $row)
		{
			$review_array[] = array (
				'user_id'			=> $row['ticket_user_id'],
				'user_colour'		=> $row['user_colour'],
				'username'			=> $row['username'],
				'post_username'		=> $row['ticket_username'],
				'text'				=> $row['ticket_desc'],
				'uid'				=> $row['ticket_desc_uid'],
				'bitfield'			=> $row['ticket_desc_bitfield'],
				'options'			=> $row['ticket_desc_options'],
				'time'				=> $row['ticket_time'],
			);
		}

		foreach ($review_array as $review)
		{
			$template->assign_block_vars('review', array(
				'POST_USER'		=> ($review['user_id'] != ANONYMOUS) ? get_username_string('full', $review['user_id'], $review['username'], $review['user_colour']) :  get_username_string('full', $review['user_id'], $review['username'], $review['user_colour'], $review['post_username']),
				'POST_TIME'		=> $user->format_date($review['time']),
				'POST_TEXT'		=> generate_text_for_display($review['text'], $review['uid'], $review['bitfield'], $review['options']),
			));
		}

		$template->assign_vars(array(
			'POST_IMG'		=> $user->img('icon_post_target', ''),
		));
	}

	public function display_statistics($project_id, $project_cat_id = false)
	{
		global $db, $user, $cache, $template, $phpEx, $phpbb_root_path, $config, $auth;

		$template->assign_var('S_IN_STATS', true);

		$template->assign_block_vars('navlinks', array(
			'FORUM_NAME'   		=> $user->lang['TRACKER_STATS'],
			'U_VIEW_FORUM'  	=> $this->api->build_url('statistics'),
		));

		if ($project_id)
		{
			//Get total open
			$sql = 'SELECT COUNT(ticket_id) as total
				FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $project_id . '
					AND ' . $db->sql_in_set('status_id', $this->api->get_opened());
			$result = $db->sql_query($sql);
			$total_opened = $db->sql_fetchfield('total');
			$db->sql_freeresult($result);

			//Get total closed
			$sql = 'SELECT COUNT(ticket_id) as total
				FROM ' . TRACKER_TICKETS_TABLE . '
				WHERE project_id = ' . $project_id . '
					AND ' . $db->sql_in_set('status_id', $this->api->get_opened(), true);
			$result = $db->sql_query($sql);
			$total_closed = $db->sql_fetchfield('total');
			$db->sql_freeresult($result);

			$template->assign_vars(array(
				'S_IN_PROJECT_STATS'	=> true,
				'L_TITLE'				=> $this->api->projects[$project_id]['project_name'] . ' - ' . $this->api->get_type_option('title', $project_id),

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

			foreach ($this->api->status as $item)
			{
				if ($item['filter'])
				{
					continue;
				}

				$template->assign_block_vars('status', array(
					'STATUS_TOTAL'		=> (isset($status_count[$item['id']])) ? $status_count[$item['id']] : 0,
					'STATUS_NAME'		=> $this->api->set_status($item['id']),
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
					'USERNAME'		=> $this->api->get_assigned_to($project_id, $item['ticket_assigned_to'], $item['username'], $item['user_colour']),
					'TOTAL'			=> $item['total_tickets'],
				));
			}

			$sql_array = array(
				'SELECT'	=> 't.ticket_user_id,
								t.ticket_username,
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
			$result = $db->sql_query_limit($sql, $this->api->config['top_reporters']);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			$template->assign_var('TRACKER_TOP_REPORTERS', sprintf($user->lang['TRACKER_TOP_REPORTERS_TITLE'], $this->api->config['top_reporters']));

			foreach ($row as $item)
			{
				if ($item['ticket_user_id'] == 0)
				{
					continue;
				}

				$template->assign_block_vars('top', array(
					'USERNAME'		=> ($item['ticket_user_id'] != ANONYMOUS) ? get_username_string('full', $item['ticket_user_id'], $item['username'], $item['user_colour']) :  get_username_string('full', $item['ticket_user_id'], $item['username'], $item['user_colour'], $item['ticket_username']),
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
					'COMPONENT_NAME'		=> $this->api->set_lang_name($item['component_name']),
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
					'U_VIEW_CHANGELOG'		=> $this->api->build_url('changelog', array($project_id, $item['version_id'])),

					'VERSION_NAME'			=> $this->api->set_lang_name($item['version_name']),
					'TOTAL'					=> (isset($version_count[$item['version_id']])) ? $version_count[$item['version_id']] : 0,
				));
			}

			$this->api->generate_nav($this->api->projects[$project_id], false, true);

			// Output page
			page_header($user->lang['TRACKER_STATS'] . ' - ' . $this->api->get_type_option('title', $project_id) . ' - ' . $this->api->projects[$project_id]['project_name'], false);

		}
		else
		{
			$sql_array = array(
				'SELECT'	=> 'p.project_id,
								p.project_cat_id,
								pc.project_name,
								p.project_desc,
								pc.project_name_clean,
								p.project_type,
								p.project_enabled,
								p.project_group,
								COUNT(t.ticket_id) as total_tickets',

				'FROM'		=> array(
					TRACKER_PROJECT_TABLE	=> 'p',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(TRACKER_PROJECT_CATS_TABLE => 'pc'),
						'ON'	=> 'p.project_cat_id = pc.project_cat_id',
					),
					array(
						'FROM'	=> array(TRACKER_TICKETS_TABLE => 't'),
						'ON'	=> 'p.project_id = t.project_id',
					),
				),

				'GROUP_BY'	=> 'p.project_id,
								p.project_cat_id,
								pc.project_name,
								p.project_desc,
								pc.project_name_clean,
								p.project_type,
								p.project_enabled,
								p.project_group',

				'ORDER_BY'	=>	'pc.project_name_clean ASC, p.project_type ASC',

			);

			$sql = $db->sql_build_query('SELECT', $sql_array);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			$project_array = array();
			foreach ($row as $item)
			{
				if ($item['project_enabled'] == TRACKER_PROJECT_DISABLED)
				{
					if (!group_memberships($item['project_group'], $user->data['user_id'], true))
					{
						continue;
					}
				}

				$project_array[$item['project_name_clean']][] =  $item;
			}

			foreach ($project_array as $projects)
			{
				if ($project_cat_id && $project_cat_id != $projects[0]['project_cat_id'])
				{
					continue;
				}

				$template->assign_block_vars('cat', array(
					'PROJECT_NAME'		=> $projects[0]['project_name'],
					'U_PROJECT' 		=> $this->api->build_url('statistics_pc', array($projects[0]['project_cat_id'])),
				));

				foreach ($projects as $item)
				{
					$template->assign_block_vars('cat.project', array(
						'U_PROJECT'			=> $this->api->build_url('statistics_p', array($item['project_id'])),
						'PROJECT_NAME'		=> $item['project_name'],
						'PROJECT_DESC'		=> $item['project_desc'],
						'PROJECT_TYPE'		=> $this->api->set_lang_name($this->api->types[$item['project_type']]['title']),
						'TOTAL_TICKETS'		=> (isset($item['total_tickets'])) ? $item['total_tickets'] : 0,
					));
				}

			}

			if ($project_cat_id)
			{
				$template->assign_block_vars('navlinks', array(
					'FORUM_NAME'   		=> $this->api->project_cats[$project_cat_id]['project_name'],
					'U_VIEW_FORUM'  	=> $this->api->build_url('statistics_pc', array($project_cat_id)),
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
	* Display a changelog for the specified version
	* @todo decide how to handle security projects/tickets
	*/
	public function display_changelog($project_id, $version_id)
	{
		global $db, $user, $cache, $template, $phpEx, $phpbb_root_path, $config, $auth;

		$sql = 'SELECT version_name
			FROM ' . TRACKER_VERSION_TABLE . "
			WHERE version_id = $version_id";
		$result = $db->sql_query($sql);
		$version_name = (string) $db->sql_fetchfield('version_name');
		$db->sql_freeresult($result);

		$sql_array = array(
			'SELECT'	=> 't.ticket_id,
							t.ticket_title,
							c.component_name',

			'FROM'		=> array(
				TRACKER_TICKETS_TABLE => 't',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(TRACKER_COMPONENTS_TABLE => 'c'),
					'ON'	=> 't.component_id = c.component_id',
				),
			),

			'WHERE'		=> "t.version_id = $version_id AND " . $db->sql_in_set('t.status_id', array(15, 16)),

			'ORDER_BY'	=>	't.ticket_id ASC',

		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		$changes = $change_html = array();
		$board_url = generate_board_url() . '/';
		$page_title = sprintf($user->lang['TRACKER_VERSION_CHANGELOG'], $this->api->projects[$project_id]['project_name'], $version_name);

		if (sizeof($row))
		{
			$changes[] = '[size=120][b]' . $page_title . '[/b][/size]';
			$changes[] = '[list]';
			
			$changes_html[] = '<span style="font-size: 120%; line-height: 116%; font-weight: bold;">' . $page_title . '</span>';
			$changes_html[] = '<ul>';

			foreach ($row as $fixed)
			{
				$component_name = (empty($fixed['component_name'])) ? '' : '[' . $this->api->set_lang_name($fixed['component_name']) . '] ';
				$ticket_url = $board_url . $this->api->build_url('changelog', array($project_id, $fixed['ticket_id']));
				$changes[] = '[*][url=' . $ticket_url . '][' . $this->api->projects[$project_id]['project_name'] . '-' . $fixed['ticket_id'] . '][/url] - ' . $component_name . $fixed['ticket_title'];
				$changes_html[] = '	<li><a href="' . $ticket_url . '">[' . $this->api->projects[$project_id]['project_name'] . '-' . $fixed['ticket_id'] . ']</a> - ' . $component_name . $fixed['ticket_title'] . '</li>';
			}

			$changes[] = '[/list]';
			$changes_html[] = '</ul>';

			// Output
			$display_output = implode("\n", $changes_html);

			// BBCode
			$uid = $bitfield = $options = '';
			$allow_bbcode = $allow_urls = $allow_smilies = true;
			$output = '[code]' . implode("\n", $changes) . '[/code]';
			generate_text_for_storage($output, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);
			$bbcode_output = generate_text_for_display($output, $uid, $bitfield, $options);

			// HTML
			$uid = $bitfield = $options = '';
			$allow_bbcode = $allow_urls = $allow_smilies = true;
			$output = '[code]' . $display_output . '[/code]';
			generate_text_for_storage($output, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);
			$html_output = generate_text_for_display($output, $uid, $bitfield, $options);


			$template->assign_vars(array(
				'S_HAS_CHANGELOG'		=> true,
				'OUTPUT'				=> $display_output,
				'BBCODE_CHANGELOG'		=> $bbcode_output,
				'HTML_CHANGELOG'		=> $html_output,
			));

		}


		// Output page
		page_header($page_title, false);

		$template->assign_vars(array(
			'S_IN_CHANGELOG'		=> true,

			'PAGE_TITLE'			=> $page_title,
		));

		$template->set_filenames(array(
			'body' => 'tracker/tracker_stats_body.html')
		);

		page_footer();

	}

	public function display_delete($project_id, $post_id, $ticket_id)
	{
		global $user;

		if (confirm_box(true))
		{
			$message = $return_msg = '';

			if ($post_id)
			{
				$this->api->delete_post($post_id, $ticket_id);

				$message	= 'TRACKER_DELETE_POST_SUCCESS';
				$return_msg	= 'TRACKER_REPLY_RETURN';
			}
			else if ($ticket_id)
			{
				$this->api->delete_ticket($ticket_id);

				$message = 'TRACKER_DELETE_TICKET_SUCCESS';
			}

			$this->back_link($message, $return_msg, $project_id, $post_id ? $ticket_id : false);
		}

		if ($post_id)
		{
			$s_hidden_fields = build_hidden_fields(array(
				'submit'	=> true,
				't'			=> $ticket_id,
				'p'			=> $project_id,
				'pid'		=> $post_id,
			));

			confirm_box(false, 'TRACKER_DELETE_POST', $s_hidden_fields);
		}
		else if ($ticket_id)
		{
			$s_hidden_fields = build_hidden_fields(array(
				'submit'	=> true,
				't'			=> $ticket_id,
				'p'			=> $project_id,
			));

			confirm_box(false, 'TRACKER_DELETE_TICKET', $s_hidden_fields);
		}
	}

	public function check_permission($mode, $return = false)
	{
		global $auth, $user;

		// Check if user can view tracker
		if (!$auth->acl_get('u_tracker_view'))
		{
			if ($return)
			{
				return false;
			}
			else
			{
				trigger_error('NO_PERMISSION_TRACKER_VIEW');
			}
		}

		switch ($mode)
		{
			case 'reply':
			case 'add':
				if (!$auth->acl_get('u_tracker_post'))
				{
					if ($return)
					{
						return false;
					}
					else
					{
						trigger_error('NO_PERMISSION_TRACKER_POST');
					}
				}
			break;

			case 'edit':
				if (!$auth->acl_get('a_tracker') && !$auth->acl_get('u_tracker_edit') && !$auth->acl_get('u_tracker_edit_all') && !$auth->acl_get('u_tracker_edit_global'))
				{
					if ($return)
					{
						return false;
					}
					else
					{
						trigger_error('NO_PERMISSION_TRACKER_EDIT');
					}
				}
			break;

			case 'delete':
				if (!$auth->acl_get('a_tracker') && !$auth->acl_get('u_tracker_delete_global') && (!$auth->acl_get('u_tracker_delete_all') || !$this->api->can_manage))
				{
					if ($return)
					{
						return false;
					}
					else
					{
						trigger_error('TRACKER_DELETE_NO_PERMISSION');
					}
				}
			break;

			default:
			break;
		}
		return true;
	}

	public function check_exists($project_id)
	{
		// Check if project actually exists...
		if (!isset($this->api->projects[$project_id]))
		{
			return false;
		}

		$this->api->set_manage($project_id);

		// Check if the project is enabled...
		if ($this->api->projects[$project_id]['project_enabled'] == TRACKER_PROJECT_DISABLED)
		{
			if (!$this->api->can_manage)
			{
				return false;
			}
		}

		// Since the project exists and user can see it, set the staus types
		$this->api->set_type($project_id);

		return true;
	}

	//$prefix needs to be either 'ticket' or 'post'
	public function check_username($mode, $user_id, $username)
	{
		global $user;

		if ($username && $user_id == ANONYMOUS)
		{
			if (($result = validate_username($username, '')) !== false)
			{
				$user->add_lang('ucp');
				$this->errors[] = $user->lang[$result . '_USERNAME'];
			}
		}
	}

	public function check_captcha($mode, $submit, $preview, $refresh, &$s_hidden_fields_confirm)
	{
		global $config, $user, $template, $db, $phpbb_root_path, $phpEx;

		include($phpbb_root_path . 'includes/captcha/captcha_factory.' . $phpEx);
		$captcha =& phpbb_captcha_factory::get_instance($config['captcha_plugin']);
		$captcha->init(CONFIRM_POST);

		if ($submit || $preview || $refresh)
		{
			if (in_array($mode, array('add', 'edit', 'reply')))
			{
				$captcha_data = array();

				$vc_response = $captcha->validate($captcha_data);
				if ($vc_response)
				{
					$this->errors[] = $vc_response;
				}
			}
		}

		if ($submit)
		{
			if ((isset($captcha) && $captcha->is_solved() === true) && (in_array($mode, array('add', 'edit', 'reply'))))
			{
				$captcha->reset();
			}
		}

		// Posting uses is_solved for legacy reasons. Plugins have to use is_solved to force themselves to be displayed.
		if ((isset($captcha) && $captcha->is_solved() === false) && (in_array($mode, array('add', 'edit', 'reply'))))
		{
			$template->assign_vars(array(
				'S_CONFIRM_CODE'			=> true,
				'CAPTCHA_TEMPLATE'			=> $captcha->get_template(),
			));
		}

		// Add the confirm id/code pair to the hidden fields, else an error is displayed on next submit/preview
		if (isset($captcha) && $captcha->is_solved() !== false)
		{
			$s_hidden_fields_confirm .= build_hidden_fields($captcha->get_hidden_fields());
		}
	}

	/**
	 * Create a "back" link (with trigger_error)
	 */
	public function back_link($message = '', $ticket_msg = '', $project_id = false, $ticket_id = false, $index = true, $board = true)
	{
		global $user;

		$message	= (!empty($user->lang[$message])) ? $user->lang[$message] : $message;

		$return_msg = array();

		if ($message)
		{
			$return_msg[] = $message;
		}

		if ($project_id && $ticket_id)
		{
			$return_msg[] = sprintf($user->lang[$ticket_msg ? $ticket_msg : 'TRACKER_REPLY_RETURN'], '<a href="' . $this->api->build_url('ticket', array($project_id, $ticket_id)) . '">', '</a>');
		}

		if ($project_id)
		{
			$return_msg[] = sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . $this->api->build_url('project', array($project_id)) . '">', '</a>');
		}

		if ($index)
		{
			$return_msg[] = sprintf($user->lang['TRACKER_RETURN'], '<a href="' . $this->api->build_url('index') . '">', '</a>');
		}

		if ($board)
		{
			$return_msg[] = sprintf($user->lang['RETURN_INDEX'], '<a href="' . $this->api->build_url('board') . '">', '</a>');
		}

		trigger_error(implode('<br /><br />', $return_msg));
	}
}

/**
 * URL builder is used to build urls (duh)
 */
class tracker_url_builder
{
	public $url_base;
	public $clean_url_base;
	public $url_ary = array(
		'index'					=> false,
		'project_cat'			=> 'c=%1$s',
		'project'				=> 'p=%1$s',
		'project_st'			=> 'p=%1$s&amp;st=%2$s',
		'project_st_at'			=> 'p=%1$s&amp;st=%2$s&amp;at=%3$s&amp;vid=%4$s&amp;cid=%5$s',
		'project_st_at_u'		=> 'p=%1$s&amp;st=%2$s&amp;at=%3$s&amp;u=%4$s&amp;vid=%5$s&amp;cid=%6$s',
		'project_st_u'			=> 'p=%1$s&amp;st=%2$s&amp;u=%3$s&amp;vid=%4$s&amp;cid=%5$s',
		'ticket'				=> 'p=%1$s&amp;t=%2$s',
		'subscribe_t'			=> 'p=%1$s&amp;t=%2$s&amp;subscribe=true',
		'unsubscribe_t'			=> 'p=%1$s&amp;t=%2$s&amp;unsubscribe=true',
		'subscribe_p'			=> 'p=%1$s&amp;subscribe=true',
		'unsubscribe_p'			=> 'p=%1$s&amp;unsubscribe=true',
		'changelog'				=> 'mode=changelog&amp;p=%1$s&amp;vid=%2$s',
		'history'				=> 'mode=history&amp;p=%1$s&amp;t=%2$s',
		'statistics'			=> 'mode=statistics',
		'statistics_p'			=> 'mode=statistics&amp;p=%1$s',
		'statistics_pc'			=> 'mode=statistics&amp;c=%1$s',
		'download'				=> 'mode=download&amp;id=%1$s',
		'download_flash'		=> 'mode=download&amp;id=%1$s&amp;view=1',
		'download_thumb'		=> 'mode=download&amp;id=%1$s&amp;t=%2$s',
		'download_thumb_type'	=> 'mode=download&amp;id=%1$s&amp;t=%2$s&amp;type=%3$s',
		'download_type'			=> 'mode=download&amp;id=%1$s&amp;type=%2$s',
		'delete'				=> 'mode=delete&amp;p=%1$s&amp;t=%2$s',
		'delete_pid'			=> 'mode=delete&amp;p=%1$s&amp;t=%2$s&amp;pid=%3$s',
		'edit'					=> 'mode=edit&amp;p=%1$s&amp;t=%2$s',
		'edit_pid'				=> 'mode=edit&amp;p=%1$s&amp;t=%2$s&amp;pid=%3$s',
		'reply'					=> 'mode=reply&amp;p=%1$s&amp;t=%2$s',
		'add'					=> 'mode=add&amp;p=%1$s',
		'search'				=> 'mode=search&amp;p=%1$s&amp;term=%2$s',
		'search_st_at_u'		=> 'mode=search&amp;p=%1$s&amp;term=%2$s&amp;st=%3$s&amp;at=%4$s&amp;u=%5$s&amp;vid=%6$s&amp;cid=%7$s',
	);

	public function __construct()
	{
		global $phpbb_root_path, $phpEx;

		$this->url_base = "{$phpbb_root_path}tracker.$phpEx";
		$this->clean_url_base = "tracker.$phpEx";
	}

	public function build($mode, $args)
	{
		global $phpbb_root_path, $phpEx;

		switch ($mode)
		{
			case 'board':
				return append_sid("{$phpbb_root_path}index.$phpEx");
			break;
			case 'memberlist_group':
				return append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . array_shift($args));
			break;
			case 'compose_pm':
				return append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;u=' . array_shift($args));
			break;
			case 'login':
				return append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login') . '&amp;redirect=' . urlencode(str_replace('&amp;', '&', build_url()));
			break;
			case 'changelog':
				return  $this->clean_url_base . '?' . vsprintf($this->url_ary['ticket'], $args);
			break;
			default:
				if (isset($this->url_ary[$mode]))
				{
					return $this->_build($mode, $args);
				}
				else if (strpos($mode, 'clean_') === 0 && isset($this->url_ary[substr($mode, strlen('clean_'))]))
				{
					return $this->_build(substr($mode, strlen('clean_')), $args, false);
				}
				return $this->_build('index', NULL);
			break;
		}
	}

	public function _build($mode, $args, $append_sid = true)
	{
		if (is_array($args) && sizeof($args))
		{
			return ($append_sid) ? append_sid($this->url_base, vsprintf($this->url_ary[$mode], $args)) : $this->clean_url_base . '?' . str_replace('&amp;', '&', vsprintf($this->url_ary[$mode], $args));
		}
		else
		{
			return ($append_sid) ? append_sid($this->url_base, $this->url_ary[$mode]) : $this->clean_url_base . (($this->url_ary[$mode]) ? '?' . $this->url_ary[$mode] : '');
		}
	}
}

?>