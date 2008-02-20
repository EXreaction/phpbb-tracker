/*

 $Id: $

*/

BEGIN TRANSACTION
GO

/*
	Table: 'phpbb_tracker_project'
*/
CREATE TABLE [phpbb_tracker_project] (
	[project_id] [int] IDENTITY (1, 1) NOT NULL ,
	[project_name] [varchar] (255) DEFAULT ('') NOT NULL ,
	[project_desc] [varchar] (255) DEFAULT ('') NOT NULL ,
	[project_group] [int] DEFAULT (0) NOT NULL ,
	[project_type] [int] DEFAULT (0) NOT NULL ,
	[project_enabled] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_project] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_project] PRIMARY KEY  CLUSTERED 
	(
		[project_id]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_config'
*/
CREATE TABLE [phpbb_tracker_config] (
	[config_name] [varchar] (255) DEFAULT ('') NOT NULL ,
	[config_value] [varchar] (255) DEFAULT ('') NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_config] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_config] PRIMARY KEY  CLUSTERED 
	(
		[config_name]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_attachments'
*/
CREATE TABLE [phpbb_tracker_attachments] (
	[attach_id] [int] IDENTITY (1, 1) NOT NULL ,
	[ticket_id] [int] DEFAULT (0) NOT NULL ,
	[post_id] [int] DEFAULT (0) NOT NULL ,
	[poster_id] [int] DEFAULT (0) NOT NULL ,
	[is_orphan] [int] DEFAULT (1) NOT NULL ,
	[physical_filename] [varchar] (255) DEFAULT ('') NOT NULL ,
	[real_filename] [varchar] (255) DEFAULT ('') NOT NULL ,
	[extension] [varchar] (100) DEFAULT ('') NOT NULL ,
	[mimetype] [varchar] (100) DEFAULT ('') NOT NULL ,
	[filesize] [int] DEFAULT (0) NOT NULL ,
	[filetime] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_attachments] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_attachments] PRIMARY KEY  CLUSTERED 
	(
		[attach_id]
	)  ON [PRIMARY] 
GO

CREATE  INDEX [filetime] ON [phpbb_tracker_attachments]([filetime]) ON [PRIMARY]
GO

CREATE  INDEX [ticket_id] ON [phpbb_tracker_attachments]([ticket_id]) ON [PRIMARY]
GO

CREATE  INDEX [post_id] ON [phpbb_tracker_attachments]([post_id]) ON [PRIMARY]
GO

CREATE  INDEX [poster_id] ON [phpbb_tracker_attachments]([poster_id]) ON [PRIMARY]
GO

CREATE  INDEX [is_orphan] ON [phpbb_tracker_attachments]([is_orphan]) ON [PRIMARY]
GO


/*
	Table: 'phpbb_tracker_tickets'
*/
CREATE TABLE [phpbb_tracker_tickets] (
	[ticket_id] [int] IDENTITY (1, 1) NOT NULL ,
	[project_id] [int] DEFAULT (0) NOT NULL ,
	[ticket_title] [varchar] (255) DEFAULT ('') NOT NULL ,
	[ticket_desc] [varchar] (4000) DEFAULT ('') NOT NULL ,
	[ticket_desc_bitfield] [varchar] (255) DEFAULT ('') NOT NULL ,
	[ticket_desc_options] [int] DEFAULT (7) NOT NULL ,
	[ticket_desc_uid] [varchar] (8) DEFAULT ('') NOT NULL ,
	[ticket_status] [int] DEFAULT (0) NOT NULL ,
	[ticket_hidden] [int] DEFAULT (0) NOT NULL ,
	[ticket_assigned_to] [int] DEFAULT (0) NOT NULL ,
	[status_id] [int] DEFAULT (0) NOT NULL ,
	[component_id] [int] DEFAULT (0) NOT NULL ,
	[version_id] [int] DEFAULT (0) NOT NULL ,
	[severity_id] [int] DEFAULT (0) NOT NULL ,
	[priority_id] [int] DEFAULT (0) NOT NULL ,
	[ticket_php] [varchar] (255) DEFAULT ('') NOT NULL ,
	[ticket_dbms] [varchar] (255) DEFAULT ('') NOT NULL ,
	[ticket_user_id] [int] DEFAULT (0) NOT NULL ,
	[ticket_time] [int] DEFAULT (0) NOT NULL ,
	[last_post_user_id] [int] DEFAULT (0) NOT NULL ,
	[last_post_time] [int] DEFAULT (0) NOT NULL ,
	[last_visit_user_id] [int] DEFAULT (0) NOT NULL ,
	[last_visit_time] [int] DEFAULT (0) NOT NULL ,
	[last_visit_username] [varchar] (255) DEFAULT ('') NOT NULL ,
	[last_visit_user_colour] [varchar] (6) DEFAULT ('') NOT NULL ,
	[edit_time] [int] DEFAULT (0) NOT NULL ,
	[edit_reason] [varchar] (255) DEFAULT ('') NOT NULL ,
	[edit_user] [int] DEFAULT (0) NOT NULL ,
	[edit_count] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_tickets] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_tickets] PRIMARY KEY  CLUSTERED 
	(
		[ticket_id]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_posts'
*/
CREATE TABLE [phpbb_tracker_posts] (
	[post_id] [int] IDENTITY (1, 1) NOT NULL ,
	[ticket_id] [int] DEFAULT (0) NOT NULL ,
	[post_desc] [varchar] (4000) DEFAULT ('') NOT NULL ,
	[post_desc_bitfield] [varchar] (255) DEFAULT ('') NOT NULL ,
	[post_desc_options] [int] DEFAULT (7) NOT NULL ,
	[post_desc_uid] [varchar] (8) DEFAULT ('') NOT NULL ,
	[post_user_id] [int] DEFAULT (0) NOT NULL ,
	[post_time] [int] DEFAULT (0) NOT NULL ,
	[edit_time] [int] DEFAULT (0) NOT NULL ,
	[edit_reason] [varchar] (255) DEFAULT ('') NOT NULL ,
	[edit_user] [int] DEFAULT (0) NOT NULL ,
	[edit_count] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_posts] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_posts] PRIMARY KEY  CLUSTERED 
	(
		[post_id]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_components'
*/
CREATE TABLE [phpbb_tracker_components] (
	[component_id] [int] IDENTITY (1, 1) NOT NULL ,
	[project_id] [int] DEFAULT (0) NOT NULL ,
	[component_name] [varchar] (255) DEFAULT ('') NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_components] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_components] PRIMARY KEY  CLUSTERED 
	(
		[component_id]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_history'
*/
CREATE TABLE [phpbb_tracker_history] (
	[history_id] [int] IDENTITY (1, 1) NOT NULL ,
	[ticket_id] [int] DEFAULT (0) NOT NULL ,
	[history_time] [int] DEFAULT (0) NOT NULL ,
	[history_status] [int] DEFAULT (0) NOT NULL ,
	[history_user_id] [int] DEFAULT (0) NOT NULL ,
	[history_assigned_to] [int] DEFAULT (0) NOT NULL ,
	[history_old_status] [int] DEFAULT (0) NOT NULL ,
	[history_new_status] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_history] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_history] PRIMARY KEY  CLUSTERED 
	(
		[history_id]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_version'
*/
CREATE TABLE [phpbb_tracker_version] (
	[version_id] [int] IDENTITY (1, 1) NOT NULL ,
	[project_id] [int] DEFAULT (0) NOT NULL ,
	[version_name] [varchar] (255) DEFAULT ('') NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_version] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_version] PRIMARY KEY  CLUSTERED 
	(
		[version_id]
	)  ON [PRIMARY] 
GO



COMMIT
GO

