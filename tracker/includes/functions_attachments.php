<?php
/**
*
* @package tracker
* @version $Id$
* @copyright (c) 2008 http://www.jeffrusso.net
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Attachment class for phpBB Tracker
*
* Most of this code is forked from User Blog mod (EXreaction, Lithium Studios) which is forked from the attachment code in phpBB3.
*/
class tracker_attachment
{
	public $attachment_data = array();
	public $filename_data = array();
	public $warn_msg = array();

	/**
	* Updates some attachment data
	*/
	public function update_attachment_data($ticket_id, $post_id = 0)
	{
		global $auth, $config, $db;

		/*if (!$config['user_tracker_enable_attachments'] || !$auth->acl_get('u_trackerattach'))
		{
			return;
		}*/

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

		/*if (!$config['user_tracker_enable_attachments'])
		{
			return;
		}*/

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

				$download_link = '';//tracker_url(false, false, false, array('page' => 'download', 'mode' => 'download', 'id' => intval($attach_row['attach_id'])));

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

		/*if (!$config['user_tracker_enable_attachments'] || !$auth->acl_get('u_download'))
		{
			return;
		}*/

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
				$this->attachment_data[] = $row;
			}
			else if ($row['ticket_id'] != 0)
			{
				$this->attachment_data[] = $row;
			}
		}
		$db->sql_freeresult($result);
	}

	/**
	* Parse Attachments
	*/
	public function parse_attachments($form_name, $submit, $preview, $refresh, &$text)
	{
		global $config, $auth, $user, $phpbb_root_path, $phpEx, $db, $message_parser;

		/*if (!$config['user_tracker_enable_attachments'] || !$auth->acl_get('u_trackerattach'))
		{
			return;
		}*/

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
			//if ($num_attachments < $config['user_tracker_max_attachments'] || $auth->acl_get('u_trackernolimitattach'))
			if ($num_attachments < $config['user_tracker_max_attachments'])
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
					//$message_parser->message = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "'[attachment='.(\\1 + 1).']\\2[/attachment]'", $message_parser->message);

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
				$error[] = sprintf($user->lang['TOO_MANY_ATTACHMENTS'], $config['user_tracker_max_attachments']);
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
					//$message_parser->message = preg_replace('#\[attachment=([0-9]+)\](.*?)\[\/attachment\]#e', "(\\1 == \$index) ? '' : ((\\1 > \$index) ? '[attachment=' . (\\1 - 1) . ']\\2[/attachment]' : '\\0')", $message_parser->message);

					// Reindex Array
					$this->attachment_data = array_values($this->attachment_data);
				}
			}
			else if (($add_file || $preview) && $upload_file)
			{
				//if ($num_attachments < $config['user_tracker_max_attachments'] || $auth->acl_get('u_trackernolimitattach'))
				if ($num_attachments < $config['max_attachments'] || $auth->acl_get('a_'))
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
						//$message_parser->message = preg_replace('#\[attachment=([0-9]+):' . $message_parser->bbcode_uid . '\](.*?)\[\/attachment:' . $message_parser->bbcode_uid . '\]#e', "'[attachment='.(\\1 + 1).':{$message_parser->bbcode_uid}]\\2[/attachment:{$message_parser->bbcode_uid}]'", $message_parser->message);
						$this->filename_data['filecomment'] = '';
					}
				}
				else
				{
					$error[] = sprintf($user->lang['TOO_MANY_ATTACHMENTS'], $config['user_tracker_max_attachments']);
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

		/*if (!$config['user_tracker_enable_attachments'] || !$auth->acl_get('u_trackerattach'))
		{
			return;
		}*/

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

		/*if (!$config['user_tracker_enable_attachments'] || !$auth->acl_get('u_trackerattach'))
		{
			return;
		}*/

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

		if (!$file->move_file($config['upload_path'] . '/tracker', false, $no_image))
		{
			$file->error[] = sprintf($user->lang[$file->upload->error_prefix . 'GENERAL_UPLOAD_ERROR'], $config['upload_path'] . '/tracker');
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
		if ($free_space = @disk_free_space($phpbb_root_path . $config['upload_path']))
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

		//if (!$config['user_tracker_enable_attachments'] || !sizeof($attachments) || !$auth->acl_get('u_download'))
		if (!sizeof($attachments) || !$auth->acl_get('u_download'))
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

		$extensions = $cache->obtain_attach_extensions();

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
			$filename = $phpbb_root_path . $config['upload_path'] . '/tracker/' . basename($attachment['physical_filename']);
			$thumbnail_filename = $phpbb_root_path . $config['upload_path'] . '/tracker/thumb_' . basename($attachment['physical_filename']);

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

				$download_link = '';//tracker_url(false, false, false, array('page' => 'download', 'mode' => 'download', 'id' => $attachment['attach_id']));

				switch ($display_cat)
				{
					// Images
					case ATTACHMENT_CATEGORY_IMAGE:
						$l_downloaded_viewed = 'VIEWED_COUNT';

						$inline_link = '';//tracker_url(false, false, false, array('page' => 'download', 'mode' => 'download', 'id' => $attachment['attach_id']));

						$block_array += array(
							'S_IMAGE'		=> true,
							'U_INLINE_LINK'		=> $inline_link,
						);

						$update_count[] = $attachment['attach_id'];
					break;

					// Images, but display Thumbnail
					case ATTACHMENT_CATEGORY_THUMB:
						$l_downloaded_viewed = 'VIEWED_COUNT';

						$thumbnail_link = '';//tracker_url(false, false, false, array('page' => 'download', 'mode' => 'thumbnail', 'id' => $attachment['attach_id']));

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
}
?>