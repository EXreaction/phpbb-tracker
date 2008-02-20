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
define('IN_PHPBB', true);
$phpbb_root_path = './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

if (isset($_GET['mode']) && ($_GET['mode']) == 'download')
{
	require($phpbb_root_path . 'includes/tracker/tracker_download.' . $phpEx);
	exit;
}

include($phpbb_root_path . 'common.' . $phpEx);			
include($phpbb_root_path . 'includes/tracker/tracker_class.' . $phpEx);
include($phpbb_root_path . 'includes/functions_user.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('');

//Initialize tracker class
$tracker = new tracker();

//Get the varibles we will use to build the tracker pages
$mode = request_var('mode', '');
$ticket_id	= request_var('t', 0);
$project_id	= request_var('p', 0);
$post_id	= request_var('pid', 0);
$user_id	= request_var('u', 0);
$assigned_to_user_id	= request_var('at', 0);
$status_type = request_var('st', TRACKER_ALL_OPENED);
$start = request_var('start', 0);
$submit = (isset($_POST['submit'])) ? true : false;
$submit_mod = (isset($_POST['submit_mod'])) ? true : false;
$update = (isset($_POST['update'])) ? true : false;
$add_attachment = (isset($_POST['add_attachment'])) ? true : false;
$remove_attachment = (isset($_POST['delete_attachment'])) ? true : false;
$attachment_data = (isset($_POST['attachment_data'])) ? $_POST['attachment_data'] : array();
$preview = (isset($_POST['preview'])) ? true : false;
$errors = array();

//Make sure the project exists and enabled...
if (!empty($project_id))
{
	//Check if project actually exists...
	if (!isset($tracker->projects[$project_id]))
	{
		trigger_error('TRACKER_PROJECT_NO_EXIST');
	}
	
	//Check if the project is enabled...
	if ($tracker->projects[$project_id]['project_enabled'] == TRACKER_PROJECT_DISABLED)
	{
		if (!group_memberships($tracker->projects[$project_id]['project_group'], $user->data['user_id'], true))
		{
			trigger_error('TRACKER_PROJECT_NO_EXIST');
		}
	}
	
	//Since the project exists and user can see it, set the stause types
	$tracker->set_type($project_id);
}

//Check if user can view tracker
if (!$auth->acl_get('u_tracker_view'))
{
	trigger_error($user->lang['NO_PERMISSION_TRACKER_VIEW']);
}

//Check permissions here for adding tickets, posts, editing and deleting...
if (($mode == 'reply' || $mode == 'add') && (!$auth->acl_get('u_tracker_post') || !$user->data['is_registered']))
{
	trigger_error($user->lang['NO_PERMISSION_TRACKER_POST']);
}

if ($mode == 'edit' && (!$auth->acl_get('u_tracker_edit') || !$user->data['is_registered']))
{
	trigger_error($user->lang['NO_PERMISSION_TRACKER_EDIT']);
}

if ($mode == 'delete' && !$auth->acl_get('a_tracker'))
{
	trigger_error('TRACKER_DELETE_NO_PERMISSION');
}

if ($project_id && !$mode && !$ticket_id)
{
	$row = $tracker->projects[$project_id];
	$tracker->generate_nav($row);
	$can_manage = group_memberships($row['project_group'], $user->data['user_id'], true);
	$hidden_tickets = (!$can_manage) ? ' AND t.ticket_hidden = ' . TRACKER_TICKET_UNHIDDEN : '';
	$project_enabled = (!$can_manage) ? ' AND p.project_enabled = ' .TRACKER_PROJECT_ENABLED : '';
	$ticket_security = (!$can_manage && $row['project_security']) ? ' AND t.ticket_user_id = ' . $user->data['user_id'] : '';
	
	$my_tickets = ($user_id) ? ' AND t.ticket_user_id = ' . $user_id : '';
	$my_assigned_tickets = ($assigned_to_user_id) ? ' AND t.ticket_assigned_to = ' . $assigned_to_user_id : '';
	$sql_array = array(
		'SELECT'	=> 't.*,
						u1.user_colour as ticket_user_colour, 
						u1.username as ticket_username, 
						u2.user_colour as assigned_user_colour, 
						u2.username as assigned_username,
						u3.user_colour as last_post_user_colour, 
						u3.username as last_post_username',

		'FROM'		=> array(
			TRACKER_TICKETS_TABLE	=> 't',
		),

		'LEFT_JOIN'	=> array(
			array(
				'FROM'	=> array(TRACKER_PROJECT_TABLE => 'p'),
				'ON'	=> 't.project_id = p.project_id',
			),
			array(
				'FROM'	=> array(USERS_TABLE => 'u1'),
				'ON'	=> 'u1.user_id = t.ticket_user_id',
			),
			array(
				'FROM'	=> array(USERS_TABLE => 'u2'),
				'ON'	=> 'u2.user_id = t.ticket_assigned_to',
			),
			array(
				'FROM'	=> array(USERS_TABLE => 'u3'),
				'ON'	=> 'u3.user_id = t.last_post_user_id',
			),
		),

		'WHERE'		=> 't.project_id = ' . $project_id . $project_enabled . $hidden_tickets . $ticket_security . $my_tickets . $my_assigned_tickets . $tracker->set_filter($status_type),

		'ORDER_BY'	=> 't.ticket_time DESC',
	);
	
	$total_tickets = $tracker->get_total('tickets', $project_id, $ticket_id, $sql_array['WHERE']);
	$tickets_per_page = $tracker->config['tickets_per_page'];

	$sql = $db->sql_build_query('SELECT', $sql_array);
	$result = $db->sql_query_limit($sql, $tickets_per_page, $start);

	$tickets = $db->sql_fetchrowset($result);
	$db->sql_freeresult($result);
	
	if ($tickets)
	{
		$sql = 'SELECT *
			FROM ' . TRACKER_COMPONENTS_TABLE . '
			WHERE project_id = ' . $project_id;
		$result = $db->sql_query($sql);

		$components = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$components[$row['component_id']] = $row['component_name'];
		}
		$db->sql_freeresult($result);
	}

	foreach ($tickets as $item)
	{	
		$template->assign_block_vars('tickets', array(
			'U_VIEW_TICKET'				=> append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t={$item['ticket_id']}"),
			
			'LAST_POST_USERNAME'		=> (!empty($item['last_post_user_id'])) ? get_username_string('full', $item['last_post_user_id'], $item['last_post_username'], $item['last_post_user_colour']) : '',
			'LAST_POST_TIME'			=> $user->format_date($item['last_post_time']),
			
			'TICKET_HIDDEN'				=> ($item['ticket_hidden'] == TRACKER_TICKET_HIDDEN) ? true : false,
			'TICKET_ID'					=> $item['ticket_id'],
			'TICKET_TITLE'				=> $item['ticket_title'],
			'TICKET_USERNAME'			=> get_username_string('full', $item['ticket_user_id'], $item['ticket_username'], $item['ticket_user_colour']),
			'TICKET_TIME'				=> $user->format_date($item['ticket_time']),
			'TICKET_COMPONENT'			=> $tracker->set_component_name($item['component_id'], $components),
			'TICKET_ASSIGNED_TO'		=> $tracker->get_assigned_to($project_id, $item['ticket_assigned_to'], $item['assigned_username'], $item['assigned_user_colour']),
			'TICKET_STATUS'				=> $tracker->set_status($item['status_id']),		
		));
	}	

	$currently_showing = '';
	$sort_type = '';
	if ($user_id)
	{	
		$sort_type = "&amp;u=$user_id";
		$filter_username = array();
		$filter_user_id = $user_id;
		user_get_id_name($filter_user_id, $filter_username);
		$currently_showing = sprintf($user->lang['TRACKER_CURRENTLY_SHOWING_USER'], $tracker->format_username($filter_username[$user_id]), strtolower($tracker->set_status($status_type)));
	}
	else
	{
		$currently_showing = sprintf($user->lang['TRACKER_CURRENTLY_SHOWING'], $tracker->set_status($status_type));
	}
	
	if ($assigned_to_user_id)
	{
		$sort_type .= "&amp;at=$assigned_to_user_id";
		$filter_username = array();
		$filter_user_id = $assigned_to_user_id;
		user_get_id_name($filter_user_id, $filter_username);
		$currently_showing = $currently_showing . sprintf($user->lang['TRACKER_ASSIGNED_TO_USERNAME'], $filter_username[$assigned_to_user_id]);
	}
	
	$l_total_tickets = false;
	if ($total_tickets == 1)
	{
		$l_total_tickets = $total_tickets . ' ' . $user->lang['TRACKER_TICKET'];
	}
	else if ($total_tickets > 1)
	{
		$l_total_tickets = $total_tickets . ' ' . $user->lang['TRACKER_TICKETS'];
	}
	
	$template->assign_vars(array(
		'PAGE_NUMBER'	=> ($tickets_per_page > 0) ? on_page($total_tickets, $tickets_per_page, $start) : on_page($total_tickets, $total_tickets, $start),
		'TOTAL_TICKETS'	=> $l_total_tickets,
		'PAGINATION'	=> ($tickets_per_page > 0) ? generate_pagination(append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id"), $total_tickets, $tickets_per_page, $start) : false,
		)
	);
	
	// Assign index specific vars
	$template->assign_vars(array(
		'L_TITLE'						=> $tracker->get_type_option('title', $project_id),
		
		'S_CAN_MANAGE'					=> $can_manage,
		'PROJECT_ID'					=> $project_id,
		'TRACKER_USER_ID'				=> $user_id,					
		'TRACKER_ASSIGNED_USER_ID'		=> $assigned_to_user_id,	
		'TRACKER_CURRENTLY_SHOWING'		=> $currently_showing,
		'S_CAN_POST_TRACKER'			=> $auth->acl_get('u_tracker_post'),
		'TICKET_IMG'					=> $user->img('button_topic_new', $user->lang['TRACKER_POST_TICKET']),
		'U_POST_NEW_TICKET'				=> append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;mode=add"),
		'U_MY_TICKETS'					=> ($user_id) ? append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;st=$status_type" . (($assigned_to_user_id) ? "&amp;at=$assigned_to_user_id" : '')) : append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;st=$status_type&amp;u={$user->data['user_id']}" . (($assigned_to_user_id) ? "&amp;at=$assigned_to_user_id" : '')),
		'TRACKER_MY_TICKETS'			=> ($user_id) ? $user->lang['TRACKER_EVERYONES_TICKETS'] : $user->lang['TRACKER_MY_TICKETS'],
		
		'U_MY_ASSIGNED_TICKETS'			=> ($assigned_to_user_id) ? append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;st=$status_type" . (($user_id) ? "&amp;u=$user_id" : '')) : append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;at={$user->data['user_id']}&amp;st=$status_type" . (($user_id) ? "&amp;u=$user_id" : '')),
		'TRACKER_MY_ASSIGNED_TICKETS'	=> ($assigned_to_user_id) ? $user->lang['TRACKER_EVERYONES_ASSIGNED_TICKETS'] : $user->lang['TRACKER_MY_ASSIGNED_TICKETS'],
		
		'U_ACTION'						=> append_sid("{$phpbb_root_path}tracker.$phpEx"),
	
		'S_STATUS_OPTIONS'				=> $tracker->status_select_options($status_type, true),		
		'S_LOGIN_ACTION'				=> append_sid("{$phpbb_root_path}ucp.$phpEx", "mode=login&amp;redirect=tracker.$phpEx"),
		)
	);

	// Output page
	page_header($user->lang['TRACKER'] . ' - '  . $tracker->get_type_option('title', $project_id) . ' - ' .  $tracker->projects[$project_id]['project_name'], false);

	$template->set_filenames(array(
		'body' => 'tracker/tracker_tickets_body.html')
	);
}
else if ($project_id && $ticket_id && ((!$mode || $mode == 'history' || $mode == 'reply' || $mode == 'delete') || ($mode == 'edit' && $post_id)))
{	
	if ($mode == 'delete' && $submit && $auth->acl_get('a_tracker'))
	{
		if (confirm_box(true) && $post_id)
		{
			$tracker->delete_post($post_id, $ticket_id);
			
			$message = $user->lang['TRACKER_DELETE_POST_SUCCESS'] . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_REPLY_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid("{$phpbb_root_path}index.$phpEx"). '">', '</a>');

			trigger_error($message);
			
		}
		else if (confirm_box(true) && $ticket_id)
		{
			$tracker->delete_ticket($ticket_id);
			
			$message = $user->lang['TRACKER_DELETE_TICKET_SUCCESS'] . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid("{$phpbb_root_path}index.$phpEx"). '">', '</a>');

			trigger_error($message);
		}
	}
	else if ($mode == 'delete' && $auth->acl_get('a_tracker'))
	{		
		if ($post_id)
		{
			$s_hidden_fields = build_hidden_fields(array(
				'submit'	=> true,
				't'			=> $ticket_id,
				'p'			=> $project_id,
				'pid'		=> $post_id,
				)
			);

			confirm_box(false, 'TRACKER_DELETE_POST', $s_hidden_fields);
		}
		else if ($ticket_id)
		{
			$s_hidden_fields = build_hidden_fields(array(
				'submit'	=> true,
				't'			=> $ticket_id,
				'p'			=> $project_id,
				)
			);

			confirm_box(false, 'TRACKER_DELETE_TICKET', $s_hidden_fields);
		}		
	}
	
	add_form_key('add_post');
	
	if ($mode == 'reply' || $mode == 'edit')
	{	
		$tracker->check_ticket_exists($ticket_id);
		if ($mode == 'edit' && !$preview && !$submit)
		{
			$sql_array = array(
				'SELECT'	=> 'p.*,
							a.attach_id,
							a.poster_id,
							a.is_orphan,
							a.physical_filename,
							a.real_filename,
							a.extension,
							a.mimetype,
							a.filesize,
							a.filetime',

				'FROM'		=> array(
					TRACKER_POSTS_TABLE	=> 'p',
				),

				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(TRACKER_ATTACHMENTS_TABLE => 'a'),
						'ON'	=> 'p.post_id = a.post_id',
					),
				),

				'WHERE'		=> 'p.post_id = ' . $post_id,
			);
			
			$sql = $db->sql_build_query('SELECT', $sql_array);				
			$result = $db->sql_query($sql);

			$post_data = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);
			
			if (!$post_data)
			{
				trigger_error('TRACKER_POST_NO_EXIST');
			}
			
			$tracker->check_edit($post_data['edit_time'], $post_data['post_user_id'], false);
			
			if ($post_data['attach_id'])
			{
				$attachment_data = array(
					'poster_id'				=> $post_data['poster_id'],
					'filesize'				=> $post_data['filesize'],
					'mimetype'				=> $post_data['mimetype'],
					'extension'				=> $post_data['extension'],
					'physical_filename'		=> $post_data['physical_filename'],
					'real_filename'			=> $post_data['real_filename'],
					'filetime'				=> $post_data['filetime'],
					'attach_id'				=> $post_data['attach_id'],			
				);
			}
		}
		else
		{
			$post_data = array(
				'post_desc'					=> utf8_normalize_nfc(request_var('post_text', '', true)),
				'post_time'					=> time(),
				'post_user_id'				=> $user->data['user_id'],
				'post_desc_bitfield'		=> '',
				'post_desc_options'			=> 7,
				'post_desc_uid'				=> '',
				'ticket_id'					=> $ticket_id,
				'edit_reason'				=> '',
			);
		}

		if ($mode == 'edit' && ($preview || $submit))
		{
			unset($post_data['post_time']);
			unset($post_data['post_user_id']);
			$post_data['edit_reason'] = utf8_normalize_nfc(request_var('edit_reason', '', true));
			$post_data['edit_time'] = time();
			$post_data['edit_user'] = $user->data['user_id'];
		}
		
		if ($add_attachment)
		{
			$filedata = array();
			$filedata = $tracker->add_attachment('attachment', $errors);
			if (sizeof($filedata))
			{
				$tracker->posting_gen_attachment_data($filedata);		
			}
		}
		else if (sizeof($attachment_data))
		{
			if ($remove_attachment)
			{
				$tracker->remove_attachment($attachment_data);
			}
			else
			{
				$tracker->posting_gen_attachment_data($attachment_data);
			}
		}

		if ($submit)
		{
			if (!check_form_key('add_post'))
			{
				$errors[] = $user->lang['FORM_INVALID'];
			}
			
			if ($post_data['post_desc'] && !sizeof($errors))
			{
				generate_text_for_storage($post_data['post_desc'], $post_data['post_desc_uid'], $post_data['post_desc_bitfield'], $post_data['post_desc_options'], true, true, true);

				if ($mode == 'reply')
				{					
					$post_id = $tracker->add_post($post_data, $ticket_id);
					if (sizeof($attachment_data))
					{
						$tracker->update_attachment($attachment_data, $ticket_id, $post_id);
					}
				}
				else if ($mode == 'edit')
				{
					$tracker->update_post($post_data, $post_id);
					if (sizeof($attachment_data))
					{
						$tracker->update_attachment($attachment_data, $ticket_id, $post_id);
					}
				}

				$message = $user->lang['TRACKER_TICKET_REPLY_SUBMITTED'] . '<br /><br />';
				$message .= sprintf($user->lang['TRACKER_REPLY_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id") . '">', '</a>') . '<br /><br />';
				$message .= sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id") . '">', '</a>') . '<br /><br />';
				$message .= sprintf($user->lang['TRACKER_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx") . '">', '</a>') . '<br /><br />';
				$message .= sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid("{$phpbb_root_path}index.$phpEx"). '">', '</a>');

				trigger_error($message);
			}
			else if (!$post_data['post_desc'])
			{
				$errors[] = $user->lang['TRACKER_TICKET_MESSAGE_ERROR'];				
			}

		}
		
		if ($preview)
		{
			$preview_data = array(
				'text'		=> $post_data['post_desc'],
				'uid'		=> $post_data['post_desc_uid'],
				'bitfield'	=> $post_data['post_desc_bitfield'],
				'options'	=> $post_data['post_desc_options'],
			);
				
			generate_text_for_storage($preview_data['text'], $preview_data['uid'], $preview_data['bitfield'], $preview_data['options'], true, true, true);
					
			$template->assign_vars(array(
				'S_PREVIEW'			=> true,
				'REPLY_PREVIEW'		=> generate_text_for_display($preview_data['text'], $preview_data['uid'], $preview_data['bitfield'], $preview_data['options']),	
				)
			);
		}
		
		// Assign index specific vars
		$post_desc = generate_text_for_edit($post_data['post_desc'], $post_data['post_desc_uid'], $post_data['post_desc_options']);
		$template->assign_vars(array(
			'S_EDIT_REASON'			=> ($mode == 'edit') ? true : false,
			'EDIT_REASON_TEXT'		=> ($mode == 'edit') ? $post_data['edit_reason'] : '',
			'REPLY_DESC'			=> $post_desc['text'],
			'U_ACTION'				=> ($mode == 'edit') ? append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;pid=$post_id&amp;mode=edit") : append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;mode=reply"),
			)
		);
	}
	
	$sql_array = array(
		'SELECT'	=> 't.*,
						a.attach_id,
						a.is_orphan,
						a.physical_filename,
						a.real_filename,
						a.extension,
						a.mimetype,
						a.filesize,
						a.filetime,
						p.project_id, 
						p.project_name,
						p.project_group,
						p.project_enabled,
						u1.user_colour as ticket_user_colour, 
						u1.username as ticket_username, 
						u2.user_colour as assigned_user_colour, 
						u2.username as assigned_username,
						c.component_name,
						v.version_name',

		'FROM'		=> array(
			TRACKER_TICKETS_TABLE	=> 't',
		),

		'LEFT_JOIN'	=> array(
			array(
				'FROM'	=> array(TRACKER_PROJECT_TABLE => 'p'),
				'ON'	=> 't.project_id = p.project_id',
			),
			array(
				'FROM'	=> array(TRACKER_ATTACHMENTS_TABLE => 'a'),
				'ON'	=> 't.ticket_id = a.ticket_id AND a.post_id = 0',
			),
			array(
				'FROM'	=> array(USERS_TABLE => 'u1'),
				'ON'	=> 'u1.user_id = t.ticket_user_id',
			),
			array(
				'FROM'	=> array(USERS_TABLE => 'u2'),
				'ON'	=> 'u2.user_id = t.ticket_assigned_to',
			),
			array(
				'FROM'	=> array(TRACKER_COMPONENTS_TABLE => 'c'),
				'ON'	=> 't.component_id = c.component_id',
			),
			array(
				'FROM'	=> array(TRACKER_VERSION_TABLE => 'v'),
				'ON'	=> 't.version_id = v.version_id',
			),
		),

		'WHERE'		=> 't.ticket_id = ' . $ticket_id,
	);

	$sql = $db->sql_build_query('SELECT', $sql_array);
	$result = $db->sql_query($sql);

	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	
	$can_manage = group_memberships($row['project_group'], $user->data['user_id'], true);
	if (!$row || ($row['ticket_hidden'] == TRACKER_TICKET_HIDDEN && !$can_manage) || ($row['project_enabled'] == TRACKER_PROJECT_DISABLED && !$can_manage) || ($row['project_security'] && !$can_manage && $row['ticket_user_id'] != $user->data['user_id']))
	{
		trigger_error('TRACKER_TICKET_NO_EXIST');
	}
	
	if ($project_id && $ticket_id && !$mode && $can_manage)
	{
		$tracker->update_last_visit($ticket_id);
	}
	
	$tracker->generate_nav($row, $ticket_id);
	
	if ($mode != 'reply' || $mode != 'edit')
	{
		if ($can_manage && $update && $user->data['is_registered'] && $auth->acl_get('u_tracker_view') && $auth->acl_get('u_tracker_post'))
		{
			if (!check_form_key('add_post'))
			{
				trigger_error('FORM_INVALID');
			}
			
			$data = array(
				'ticket_assigned_to'	=> request_var('au', 0),
				'status_id'				=> request_var('cs', 0),
				'priority_id'			=> request_var('pr', 0),
				'severity_id'			=> request_var('s', 0),
			);

			$tracker->update_ticket($data, $ticket_id);
			
			$history_ts = false;
			if ($data['status_id'] != $row['status_id'])
			{
				$history_status = array(
					'history_time'			=> time(),
					'history_status'		=> TRACKER_HISTORY_STATUS_CHANGED,
					'history_user_id'		=> $user->data['user_id'],
					'ticket_id'				=> $ticket_id,
					'history_old_status'	=> $row['status_id'],
					'history_new_status'	=> $data['status_id'],
				);
				
				$tracker->add_history($history_status);
				$history_ts = true;
			}
			
			$history_at = false;
			if ($data['ticket_assigned_to'] != $row['ticket_assigned_to'])
			{
				$history_data = array(
					'history_time'			=> time(),
					'history_status'		=> TRACKER_HISTORY_ASSIGNED_TO,
					'history_user_id'		=> $user->data['user_id'],
					'ticket_id'				=> $ticket_id,
					'history_assigned_to'	=> $data['ticket_assigned_to'],
				);
				
				$tracker->add_history($history_data);
				$history_at = true;
			}
			
			if ($history_at && !$history_ts)
			{
				$history_data['history_old_assigned_to'] = $row['ticket_assigned_to'];
				$tracker->send_notification($history_data, TRACKER_EMAIL_NOTIFY_STATUS_SINGLE);
			}
			else if ($history_ts && !$history_at)
			{
				$tracker->send_notification($history_status, TRACKER_EMAIL_NOTIFY_STATUS_SINGLE);
			}
			else if ($history_at && $history_ts)
			{
				$history_data['history_old_assigned_to'] = $row['ticket_assigned_to'];
				$tracker->send_notification(array_merge($history_data, $history_status), TRACKER_EMAIL_NOTIFY_STATUS_DOUBLE);
			}
			
			$message = $user->lang['TRACKER_TICKET_UPDATED'] . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_UPDATED_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid("{$phpbb_root_path}index.$phpEx"). '">', '</a>');

			trigger_error($message);
		}
	}

	if ($submit_mod && $can_manage && (!$mode || $mode == 'history'))
	{
		$action = request_var('action', '');
		switch ($action)
		{
			case 'lock':			
			case 'unlock':				
				$tracker->lock_unlock($action, $ticket_id);
				redirect(build_url());
			break;
			
			case 'hide':			
			case 'unhide':
				$tracker->hide_unhide($action, $ticket_id);
				redirect(build_url());
			break;
			
			case 'move':
				$tracker->move_ticket($project_id, $ticket_id);
			break;
			
			default:
			break;
		}
		
	}
	
	$ticket_mod = '';
	$ticket_mod .= ($can_manage || ($row['ticket_status'] == TRACKER_TICKET_UNLOCKED)) ? (($row['ticket_status'] == TRACKER_TICKET_UNLOCKED) ? '<option value="lock">' . $user->lang['TRACKER_LOCK_TICKET'] . '</option>' : '<option value="unlock">' . $user->lang['TRACKER_UNLOCK_TICKET'] . '</option>') : '';
	$ticket_mod .= ($can_manage || ($row['ticket_hidden'] == TRACKER_TICKET_UNHIDDEN)) ? (($row['ticket_hidden'] == TRACKER_TICKET_UNHIDDEN) ? '<option value="hide">' . $user->lang['TRACKER_HIDE_TICKET'] . '</option>' : '<option value="unhide">' . $user->lang['TRACKER_UNHIDE_TICKET'] . '</option>') : '';
	$ticket_mod .= ($can_manage) ? '<option value="move">' . $user->lang['TRACKER_MOVE_TICKET'] . '</option>' : '';
	
	$s_ticket_reply = ($mode == 'reply' || $mode == 'edit') ? true : false;
	
	if ($row['attach_id'] && $auth->acl_get('u_tracker_download'))
	{
		$tracker->display_ticket_attachment($row);
	}
	
	$can_attach = false;
	if ($s_ticket_reply)
	{
		$can_attach = (file_exists($phpbb_root_path . $tracker->config['attachment_path']) && $config['allow_attachments'] && @is_writable($phpbb_root_path . $tracker->config['attachment_path']) && $auth->acl_get('u_tracker_attach') && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on')) ? true : false;
	}
	
	$s_ticket_component = $tracker->get_type_option('component', $project_id);
	$s_ticket_version = $tracker->get_type_option('version', $project_id);
	$s_ticket_priority = $tracker->get_type_option('priority', $project_id);
	$s_ticket_severity = $tracker->get_type_option('severity', $project_id);
	$s_ticket_environment = $tracker->get_type_option('environment', $project_id);
	
	$template->assign_vars(array(
		'S_TICKET_REPLY'			=> $s_ticket_reply,
		'S_MANAGE_TICKET'			=> $can_manage,
		'S_TICKET_ENVIRONMENT'		=> $s_ticket_environment,
		
		'S_CAN_ATTACH'				=> ($can_attach) ? true : false,
		'S_DISPLAY_NOTICE'			=> (($auth->acl_get('u_tracker_download') && $row['attach_id']) || !$row['attach_id']) ? false : true,
		'S_FORM_ENCTYPE'			=> ($can_attach) ? ' enctype="multipart/form-data"' : '',
		
		'U_UPDATE_ACTION'			=> ($can_manage) ? append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id") : '',
		'S_STATUS_OPTIONS'			=> ($s_ticket_reply || (!$can_manage && !$s_ticket_reply)) ? '' : $tracker->status_select_options($row['status_id']),
		'S_ASSIGN_USER_OPTIONS'		=> ($s_ticket_reply || (!$can_manage && !$s_ticket_reply)) ? '' : $tracker->user_select_options($row['ticket_assigned_to'], $row['project_group'], $project_id),
		'S_SEVERITY_OPTIONS'		=> (!$s_ticket_severity || $s_ticket_reply || (!$can_manage && !$s_ticket_reply)) ? '' : $tracker->select_options($project_id, 'severity', $row['severity_id']),
		'S_PRIORITY_OPTIONS'		=> (!$s_ticket_priority || $s_ticket_reply || (!$can_manage && !$s_ticket_reply)) ? '' : $tracker->select_options($project_id, 'priority', $row['priority_id']),
		'S_TICKET_MOD' 				=> ($ticket_mod != '') ? '<select name="action">' . $ticket_mod . '</select>' : '',
		
		'S_CAN_POST_TRACKER'		=> $auth->acl_get('u_tracker_post'),
		'REPLY_IMG'					=> ($row['ticket_status'] == TRACKER_TICKET_LOCKED) ? $user->img('button_topic_locked', 'TOPIC_LOCKED') :$user->img('button_topic_reply', 'POST_REPLY'),
		'EDIT_IMG' 					=> $user->img('icon_post_edit', 'EDIT_POST'),
		'DELETE_IMG' 				=> $user->img('icon_post_delete', 'DELETE_POST'),
		'S_IS_LOCKED'				=>($row['ticket_status'] == TRACKER_TICKET_LOCKED) ? true : false,
		
		'EDITED_MESSAGE'			=> $tracker->fetch_edited_by($row, 'ticket'),
		'EDIT_REASON'				=> $row['edit_reason'],
		
		'S_CAN_DELETE'				=> $auth->acl_get('a_tracker'),
		'U_DELETE'					=> append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;mode=delete"),
		'S_CAN_EDIT'				=> $tracker->check_edit($row['edit_time'], $row['ticket_user_id']),
		'U_EDIT'					=> append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;mode=edit"),
		
		'L_TITLE'					=> $tracker->get_type_option('title', $project_id),		
		'L_TITLE_EXPLAIN'			=> sprintf($user->lang['TRACKER_REPLY_EXPLAIN'], $row['ticket_title']), 
		'U_POST_REPLY_TICKET'		=> append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;mode=reply"),
		'U_SEND_PM'					=> append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=pm&amp;mode=compose&amp;u=' . $row['ticket_user_id']),
		'U_VIEW_TICKET_HISTORY'		=> ($mode == 'history') ? append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id") : append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;mode=history"),
		'L_TICKET_HISTORY'			=> ($mode == 'history') ? $user->lang['TRACKER_HIDE_TICKET_HISTORY'] : $user->lang['TRACKER_VIEW_TICKET_HISTORY'],
		
		'TRACKER_REPLY_DETAIL'			=> $user->lang['TRACKER_REPLY_DETAIL'] . (($tracker->config['send_email']) ? $user->lang['TRACKER_REPLY_DETAIL_EMAIL'] : ''),
		
		'ERROR'						=> (sizeof($errors)) ? implode('<br />', $errors) : '',
		'PROJECT_NAME'				=> $row['project_name'],
		'TICKET_ASSIGNED_TO'		=> $tracker->get_assigned_to($project_id, $row['ticket_assigned_to'], $row['assigned_username'], $row['assigned_user_colour']),
		'TICKET_REPORTED_BY'		=> get_username_string('full', $row['ticket_user_id'], $row['ticket_username'], $row['ticket_user_colour']),
		'TICKET_ID'					=> $row['ticket_id'],
		'TICKET_TITLE'				=> $row['ticket_title'],
		'TICKET_HIDDEN'				=> ($row['ticket_hidden'] == TRACKER_TICKET_HIDDEN) ? true : false,
		'TICKET_DESC'				=> generate_text_for_display($row['ticket_desc'], $row['ticket_desc_uid'], $row['ticket_desc_bitfield'], $row['ticket_desc_options']),	
		'TICKET_STATUS'				=> '(' . strtolower($tracker->set_status($row['status_id'])) . ')',
		'TICKET_STATUS_DETAILS'		=> $tracker->set_status($row['status_id']),
		'TICKET_CLOSED'				=> $tracker->is_closed($row['status_id']),
		'TICKET_LAST_VISIT'			=> (!empty($row['last_visit_user_id'])) ? sprintf($user->lang['TRACKER_LAST_VISIT'], get_username_string('full', $row['last_visit_user_id'], $row['last_visit_username'], $row['last_visit_user_colour']), $user->format_date($row['last_visit_time'])) : '',
		'TICKET_TIME'				=> $user->format_date($row['ticket_time']), 
		
		'S_TICKET_COMPONENT'		=> $s_ticket_component,
		'S_TICKET_VERSION'			=> $s_ticket_version,
		'S_TICKET_PRIORITY'			=> $s_ticket_priority,
		'S_TICKET_SEVERITY'			=> $s_ticket_severity,
		
		'TICKET_COMPONENT'			=> (empty($row['component_name'])) ? $user->lang['TRACKER_UNKNOWN'] : $tracker->set_lang_name($row['component_name']),
		'TICKET_VERSION'			=> (empty($row['version_name'])) ? $user->lang['TRACKER_UNKNOWN'] : $tracker->set_lang_name($row['version_name']),
		'TICKET_PRIORITY'			=> (!isset($tracker->priority[$row['priority_id']])) ? $user->lang['TRACKER_UNKNOWN'] : $tracker->set_lang_name($tracker->priority[$row['priority_id']]),
		'TICKET_SEVERITY'			=> (!isset($tracker->severity[$row['severity_id']])) ? $user->lang['TRACKER_UNKNOWN'] : $tracker->set_lang_name($tracker->severity[$row['severity_id']]),
		'TICKET_PHP'				=> (empty($row['ticket_php'])) ? $user->lang['TRACKER_UNKNOWN'] : $row['ticket_php'],
		'TICKET_DBMS'				=> (empty($row['ticket_dbms'])) ? $user->lang['TRACKER_UNKNOWN'] : $row['ticket_dbms'],
		)
	);
	
	switch ($mode)
	{
		case 'history':
			$tracker->display_history($ticket_id, $project_id);
		break;
		
		case 'reply':
			$tracker->display_review($ticket_id);
		break;
		
		default:
			$tracker->display_comments($ticket_id, $project_id, $start);
		break;
	}
	
	
	// Output page
	page_header($user->lang['TRACKER'] . ' - ' . $tracker->get_type_option('title', $project_id) . ' - ' . $tracker->projects[$project_id]['project_name'], false);

	$template->set_filenames(array(
		'body' => 'tracker/tracker_tickets_view_body.html')
	);
}
else if ($project_id && ($mode == 'add' || $mode == 'edit'))
{	
	add_form_key('add_ticket');
	
	if ($mode == 'edit' && !$preview && !$submit)
	{	
		$sql_array = array(
			'SELECT'	=> 't.*,
							a.attach_id,
							a.poster_id,
							a.is_orphan,
							a.physical_filename,
							a.real_filename,
							a.extension,
							a.mimetype,
							a.filesize,
							a.filetime',

			'FROM'		=> array(
				TRACKER_TICKETS_TABLE	=> 't',
			),

			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(TRACKER_ATTACHMENTS_TABLE => 'a'),
					'ON'	=> 't.ticket_id = a.ticket_id AND a.post_id = 0',
				),
			),

			'WHERE'		=> 't.ticket_id = ' . $ticket_id,
		);

		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		$ticket_data = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		if (!$ticket_data)
		{
			trigger_error('TRACKER_TICKET_NO_EXIST');
		}		
		
		$tracker->check_edit($ticket_data['edit_time'], $ticket_data['ticket_user_id'], false);	
		
		if ($ticket_data['attach_id'])
		{
			$attachment_data = array(
				'poster_id'				=> $ticket_data['poster_id'],
				'filesize'				=> $ticket_data['filesize'],
				'mimetype'				=> $ticket_data['mimetype'],
				'extension'				=> $ticket_data['extension'],
				'physical_filename'		=> $ticket_data['physical_filename'],
				'real_filename'			=> $ticket_data['real_filename'],
				'filetime'				=> $ticket_data['filetime'],
				'attach_id'				=> $ticket_data['attach_id'],			
			);
		}
	}
	else
	{
		$ticket_data = array(
				'ticket_title'				=> utf8_normalize_nfc(request_var('ticket_title', '', true)),
				'ticket_desc'				=> utf8_normalize_nfc(request_var('ticket_desc', '', true)),
				'ticket_php'				=> utf8_normalize_nfc(request_var('ticket_php', '', true)),
				'ticket_dbms'				=> utf8_normalize_nfc(request_var('ticket_dbms', '', true)),
				'component_id'				=> request_var('component_id', 0),
				'version_id'				=> request_var('version_id', 0),
				'ticket_time'				=> time(),
				'ticket_user_id'			=> $user->data['user_id'],
				'ticket_desc_bitfield'		=> '',
				'ticket_desc_options'		=> 7,
				'ticket_desc_uid'			=> '',
				'status_id'					=> TRACKER_NEW_STATUS,
				'project_id'				=> $project_id,
				'edit_reason'				=> '',
		);
	}
	
	if ($mode == 'edit' && ($preview || $submit || $add_attachment || $remove_attachment))
	{
		unset($ticket_data['ticket_user_id']);
		unset($ticket_data['ticket_time']);
		$ticket_data['edit_reason'] = utf8_normalize_nfc(request_var('edit_reason', '', true));
		$ticket_data['edit_time'] = time();
		$ticket_data['edit_user'] = $user->data['user_id'];
	}
	
	if ($add_attachment)
	{
		$filedata = array();
		$filedata = $tracker->add_attachment('attachment', $errors);
		if (sizeof($filedata))
		{
			$tracker->posting_gen_attachment_data($filedata);		
		}
	}
	else if (sizeof($attachment_data))
	{
		if ($remove_attachment)
		{
			$tracker->remove_attachment($attachment_data);
		}
		else
		{
			$tracker->posting_gen_attachment_data($attachment_data);
		}
	}
	
	if ($submit)
	{
		if (!check_form_key('add_ticket'))
		{
			$errors[] = $user->lang['FORM_INVALID'];
		}
				
		if ($ticket_data['ticket_title'] && $ticket_data['ticket_desc'] && !sizeof($errors))
		{
			generate_text_for_storage($ticket_data['ticket_desc'], $ticket_data['ticket_desc_uid'], $ticket_data['ticket_desc_bitfield'], $ticket_data['ticket_desc_options'], true, true, true);
				
			if ($mode == 'add')
			{
				$ticket_id = $tracker->add_ticket($ticket_data);
				if (sizeof($attachment_data))
				{
					$tracker->update_attachment($attachment_data, $ticket_id);
				}
			}
			else if ($mode == 'edit')
			{
				$tracker->update_ticket($ticket_data, $ticket_id, true);
				if (sizeof($attachment_data))
				{
					$tracker->update_attachment($attachment_data, $ticket_id);
				}
			}
			else
			{
				trigger_error('NO_MODE');
			}
			
			$message = $user->lang['TRACKER_TICKET_SUBMITTED'] . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_SUBMITTED_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_PROJECT_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['TRACKER_RETURN'], '<a href="' . append_sid("{$phpbb_root_path}tracker.$phpEx") . '">', '</a>') . '<br /><br />';
			$message .= sprintf($user->lang['RETURN_INDEX'], '<a href="' . append_sid("{$phpbb_root_path}index.$phpEx"). '">', '</a>');

			trigger_error($message);
		}
		else
		{
			if (!$ticket_data['ticket_title'])
			{
				$errors[] = $user->lang['TRACKER_TICKET_TITLE_ERROR'];
			}
			
			if (!$ticket_data['ticket_desc'])
			{
				$errors[] = $user->lang['TRACKER_TICKET_DESC_ERROR'];
			}
		}
	}
	
	if ($preview)
	{
		$preview_data = array(
			'text'		=> $ticket_data['ticket_desc'],
			'uid'		=> $ticket_data['ticket_desc_uid'],
			'bitfield'	=> $ticket_data['ticket_desc_bitfield'],
			'options'	=> $ticket_data['ticket_desc_options'],
		);
			
		generate_text_for_storage($preview_data['text'], $preview_data['uid'], $preview_data['bitfield'], $preview_data['options'], true, true, true);
		
		$template->assign_vars(array(
			'S_PREVIEW'			=> true,
			'TICKET_PREVIEW'	=> generate_text_for_display($preview_data['text'], $preview_data['uid'], $preview_data['bitfield'], $preview_data['options']),	
			)
		);
	}
	
	$s_ticket_component = $tracker->get_type_option('component', $project_id);
	$s_ticket_version = $tracker->get_type_option('version', $project_id);
	$s_ticket_environment = $tracker->get_type_option('environment', $project_id);
	
	// Assign index specific vars
	$ticket_desc = generate_text_for_edit($ticket_data['ticket_desc'], $ticket_data['ticket_desc_uid'], $ticket_data['ticket_desc_options']);
	$can_attach = (file_exists($phpbb_root_path . $tracker->config['attachment_path']) && $config['allow_attachments'] && @is_writable($phpbb_root_path . $tracker->config['attachment_path']) && $auth->acl_get('u_tracker_attach') && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on')) ? true : false;
	$template->assign_vars(array(
		'L_TITLE'					=> $tracker->get_type_option('title', $project_id),
		'L_TITLE_EXPLAIN'			=> sprintf($user->lang['TRACKER_ADD_EXPLAIN'], $tracker->projects[$project_id]['project_name'], $tracker->get_type_option('title', $project_id)) . (($tracker->config['send_email']) ? $user->lang['TRACKER_ADD_EXPLAIN_EMAIL'] : ''),
		'ERROR'						=> (sizeof($errors)) ? implode('<br />', $errors) : '',
		
		'S_EDIT_REASON'				=> ($mode == 'edit') ? true : false,
		'S_FORM_ENCTYPE'			=> ($can_attach) ? ' enctype="multipart/form-data"' : '',
		'S_COMPONENT_OPTIONS'		=> $tracker->select_options($project_id, 'component', $ticket_data['component_id']),
		'S_VERSION_OPTIONS'			=> $tracker->select_options($project_id, 'version', $ticket_data['version_id']),
		'S_CAN_ATTACH'				=> $can_attach,
		'S_TICKET_COMPONENT'		=> $s_ticket_component,
		'S_TICKET_VERSION'			=> $s_ticket_version,
		'S_TICKET_ENVIRONMENT'		=> $s_ticket_environment,
		
		'EDIT_REASON_TEXT'			=> ($mode == 'edit') ? $ticket_data['edit_reason'] : '',
		'PROJECT_ID'				=> $project_id,
		'PROJECT_NAME'				=> $tracker->projects[$project_id]['project_name'],
		'PROJECT_TYPE'				=> $tracker->get_type_option('title', $project_id),
		'TICKET_TITLE'				=> $ticket_data['ticket_title'],
		'TICKET_DESC'				=> $ticket_desc['text'],
		'TICKET_PHP'				=> $ticket_data['ticket_php'],
		'TICKET_DBMS'				=> $ticket_data['ticket_dbms'],

		'U_ACTION'					=> ($mode == 'edit') ? append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;t=$ticket_id&amp;mode=edit") : append_sid("{$phpbb_root_path}tracker.$phpEx", "p=$project_id&amp;mode=add"),
		)
	);

	// Output page
	page_header($user->lang['TRACKER'] . ' - ' . $tracker->get_type_option('title', $project_id) . ' - ' . $tracker->projects[$project_id]['project_name'], false);
	
	$tracker->generate_nav($tracker->projects[$project_id]);

	$template->set_filenames(array(
		'body' => 'tracker/tracker_tickets_add_body.html')
	);
}
else
{	
	$row = $tracker->projects;
	if (!sizeof($row))
	{
		trigger_error('TRACKER_NO_PROJECT_EXIST');
	}

	$display_project = false;
	foreach ($tracker->types as $key => $type)
	{		
		$template->assign_block_vars($type['id'], array());
		
		foreach ($row as $item)
		{
			if ($item['project_type'] != $key)
			{
				continue;
			}
			
			if ($item['project_enabled'] == TRACKER_PROJECT_DISABLED)
			{
				if (!group_memberships($item['project_group'], $user->data['user_id'], true))
				{
					continue;
				}
			}
			
			$display_project = true;			
			$template->assign_block_vars($type['id'] . '.project', array(
				'PROJECT_NAME'		=> $item['project_name'],
				'PROJECT_DESC'		=> $item['project_desc'],
				'U_PROJECT' 		=> append_sid("{$phpbb_root_path}tracker.$phpEx", 'p=' . $item['project_id']),			
			));
		}	
	}
	
	// Assign index specific vars
	$template->assign_vars(array(
		'S_DISPLAY_PROJECT'			=> $display_project,
		'S_LOGIN_ACTION'			=> append_sid("{$phpbb_root_path}ucp.$phpEx", "mode=login&amp;redirect=tracker.$phpEx"),
		)
	);

	// Output page
	page_header($user->lang['TRACKER'], false);

	$template->set_filenames(array(
		'body' => 'tracker/tracker_index_body.html')
	);
}

page_footer();
?>