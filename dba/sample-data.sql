-- Some sample data to prime the database...

INSERT INTO roles ( role_no, role_name ) VALUES( 1, 'Admin');
SELECT setval('roles_role_no_seq', 1);

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 1, TRUE, current_date, current_date, 'admin', '**nimda', 'Calendar Administrator', 'calendars@example.net' );
INSERT INTO role_member (user_no, role_no) VALUES( 1, 1);

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 2, TRUE, current_date, current_date, 'andrew', '**x', 'Andrew McMillan', 'andrew@catalyst.net.nz' );
INSERT INTO role_member (user_no, role_no) VALUES( 2, 1);


INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 10, TRUE, current_date, current_date, 'user1', '**user1', 'User 1', 'user1@example.net' );
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 11, TRUE, current_date, current_date, 'user2', '**user2', 'User 2', 'user2@example.net' );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 20, TRUE, current_date, current_date, 'manager1', '**manager1', 'Manager 1', 'manager1@example.net' );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 30, TRUE, current_date, current_date, 'assistant1', '**assistant1', 'Assistant 1', 'assistant1@example.net' );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 100, TRUE, current_date, current_date, 'resource1', '*salt*unpossible', 'Resource 1', 'resource1@example.net' );
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 101, TRUE, current_date, current_date, 'resource2', '*salt*unpossible', 'Resource 2', 'resource2@example.net' );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 200, TRUE, current_date, current_date, 'resmgr1', '*salt*unpossible', 'Resource Managers', 'resource-managers@example.net' );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 300, TRUE, current_date, current_date, 'teamclient1', '*salt*unpossible', 'Team for Client1', 'team-client1@example.net' );

SELECT setval('usr_user_no_seq', 1000);


INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, rt_inverse, confers, prefix_match )
    VALUES( 1, 'Meeting Admin', TRUE, NULL, 'RW', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, rt_inverse, confers, prefix_match )
    VALUES( 2, 'Assisted by', FALSE, 3, 'RW', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, rt_inverse, confers, prefix_match )
    VALUES( 3, 'Assistant to', FALSE, 2, 'RW', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, rt_inverse, confers, prefix_match )
    VALUES( 4, 'Member of team', FALSE, 4, 'R', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, rt_inverse, confers, prefix_match )
    VALUES( 5, 'Meeting Resource', TRUE, NULL, 'RW', '' );

-- The resources for meetings
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 200, 100, 5 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 200, 101, 5 );

-- The people who administer meetings
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 10, 200, 1 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 11, 200, 1 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 30, 200, 1 );

-- Between a Manager and their PA
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 20, 30, 2 );

-- Between a team
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 20, 300, 4 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 10, 300, 4 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 30, 300, 4 );
