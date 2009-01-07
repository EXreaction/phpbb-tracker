/*

 $Id: $

*/

BEGIN TRANSACTION
GO

/*
	Table: 'phpbb_tracker_project_categories'
*/
CREATE TABLE [phpbb_tracker_project_categories] (
	[project_cat_id] [int] IDENTITY (1, 1) NOT NULL ,
	[project_name] [varchar] (255) DEFAULT ('') NOT NULL ,
	[project_name_clean] [varchar] (255) DEFAULT ('') NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_project_categories] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_project_categories] PRIMARY KEY  CLUSTERED 
	(
		[project_cat_id]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_project_watch'
*/
CREATE TABLE [phpbb_tracker_project_watch] (
	[user_id] [int] DEFAULT (0) NOT NULL ,
	[project_id] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_project_watch] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_project_watch] PRIMARY KEY  CLUSTERED 
	(
		[user_id],
		[project_id]
	)  ON [PRIMARY] 
GO


/*
	Table: 'phpbb_tracker_tickets_watch'
*/
CREATE TABLE [phpbb_tracker_tickets_watch] (
	[user_id] [int] DEFAULT (0) NOT NULL ,
	[ticket_id] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_tickets_watch] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_tickets_watch] PRIMARY KEY  CLUSTERED 
	(
		[user_id],
		[ticket_id]
	)  ON [PRIMARY] 
GO



COMMIT
GO

