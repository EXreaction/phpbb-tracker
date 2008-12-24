/*

 $Id: $

*/

BEGIN TRANSACTION
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
	Table: 'phpbb_tracker_ticket_watch'
*/
CREATE TABLE [phpbb_tracker_ticket_watch] (
	[user_id] [int] DEFAULT (0) NOT NULL ,
	[ticket_id] [int] DEFAULT (0) NOT NULL 
) ON [PRIMARY]
GO

ALTER TABLE [phpbb_tracker_ticket_watch] WITH NOCHECK ADD 
	CONSTRAINT [PK_phpbb_tracker_ticket_watch] PRIMARY KEY  CLUSTERED 
	(
		[user_id],
		[ticket_id]
	)  ON [PRIMARY] 
GO



COMMIT
GO

