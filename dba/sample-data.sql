-- Some sample data to prime the database...
-- base-data.sql should be processed before this

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 2, TRUE, current_date, current_date, 'andrew', '**x', 'Andrew McMillan', 'andrew@catalyst.net.nz' );
INSERT INTO role_member (user_no, role_no) VALUES( 2, 1);


INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 10, TRUE, current_date, current_date, 'user1', '**user1', 'User 1', 'user1@example.net' );
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 11, TRUE, current_date, current_date, 'user2', '**user2', 'User 2', 'user2@example.net' );
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 12, TRUE, current_date, current_date, 'user3', '**user3', 'User 3', 'user3@example.net' );
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 13, TRUE, current_date, current_date, 'user4', '**user4', 'User 4', 'user4@example.net' );
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 14, TRUE, current_date, current_date, 'user5', '**user5', 'User 5', 'user5@example.net' );
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 15, TRUE, current_date, current_date, 'User Six', '**user6', 'User 6', 'user6@example.net' );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 20, TRUE, current_date, current_date, 'manager1', '**manager1', 'Manager 1', 'manager1@example.net' );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 30, TRUE, current_date, current_date, 'assistant1', '**assistant1', 'Assistant 1', 'assistant1@example.net' );


INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 100, TRUE, current_date, current_date, 'resource1', '*salt*unpossible', 'Resource 1', 'resource1@example.net' );
INSERT INTO role_member (user_no, role_no) VALUES( 100, 4);
INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 101, TRUE, current_date, current_date, 'resource2', '*salt*unpossible', 'Resource 2', 'resource2@example.net' );
INSERT INTO role_member (user_no, role_no) VALUES( 101, 4);

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 200, TRUE, current_date, current_date, 'resmgr1', '*salt*unpossible', 'Resource Managers', 'resource-managers@example.net' );
INSERT INTO role_member (user_no, role_no) VALUES( 200, 2);

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 300, TRUE, current_date, current_date, 'teamclient1', '*salt*unpossible', 'Team for Client1', 'team-client1@example.net' );
INSERT INTO role_member (user_no, role_no) VALUES( 300, 2);

SELECT setval('usr_user_no_seq', 1000);

UPDATE usr SET joined = '2009-06-01', updated = '2009-06-02';

INSERT INTO collection (user_no, parent_container, dav_name, dav_etag,
                 dav_displayname, is_calendar, created, modified,
                 public_events_only, publicly_readable, collection_id, resourcetypes )
    SELECT user_no, '/' || username || '/',  '/' || username || '/home/', md5(username),
           username || ' home', TRUE, '2009-06-03', '2009-06-04',
           FALSE, FALSE, user_no, '<DAV::collection/><urn:ietf:params:xml:ns:caldav:calendar/>'
      FROM usr;

INSERT INTO principal (type_id, user_no, displayname, default_privileges)
         SELECT 1, user_no, fullname, privilege_to_bits(ARRAY['read-free-busy','schedule-send','schedule-deliver']) FROM usr
                 WHERE NOT EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Group' AND role_member.user_no = usr.user_no)
                   AND NOT EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Resource' AND role_member.user_no = usr.user_no)
                   AND NOT EXISTS(SELECT 1 FROM principal WHERE principal.user_no = usr.user_no);

INSERT INTO principal (type_id, user_no, displayname, default_privileges)
         SELECT 2, user_no, fullname, privilege_to_bits(ARRAY['read','schedule-send','schedule-deliver']) FROM usr
                 WHERE EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Resource' AND role_member.user_no = usr.user_no)
                   AND NOT EXISTS(SELECT 1 FROM principal WHERE principal.user_no = usr.user_no);

INSERT INTO principal (type_id, user_no, displayname, default_privileges)
         SELECT 3, user_no, fullname, privilege_to_bits(ARRAY['read-free-busy','schedule-send','schedule-deliver']) FROM usr
                 WHERE EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Group' AND role_member.user_no = usr.user_no)
                   AND NOT EXISTS(SELECT 1 FROM principal WHERE principal.user_no = usr.user_no);

SELECT setval('dav_id_seq', 1000);

-- Set the insert sequence to the next number, with a minimum of 1000
SELECT setval('relationship_type_rt_id_seq', (SELECT 10 UNION SELECT rt_id FROM relationship_type ORDER BY 1 DESC LIMIT 1) );

-- The resources for meetings
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 200, 100, 1 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 200, 101, 1 );

-- The people who administer meetings
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 10, 200, 1 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 11, 200, 1 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 30, 200, 1 );

-- Between a PA and their Manager
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 30,  20, 2 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 30,  10, 2 );


-- Between a team
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 20, 300, 3 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 10, 300, 3 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 30, 300, 3 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 300, 20, 3 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 300, 10, 3 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 300, 30, 3 );

-- Granting explicit free/busy permission
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 11,  10, 4 );
INSERT INTO relationship ( from_user, to_user, rt_id ) VALUES( 10,  11, 4 );


UPDATE relationship r SET confers = (SELECT bit_confers FROM relationship_type rt WHERE rt.rt_id=r.rt_id);

INSERT INTO group_member ( group_id, member_id)
              SELECT g.principal_id, m.principal_id
                FROM relationship JOIN principal g ON(to_user=g.user_no AND g.type_id = 3)    -- Group
                                  JOIN principal m ON(from_user=m.user_no AND m.type_id IN (1,2)); -- Person | Resource

INSERT INTO grants ( by_principal, to_principal, privileges, is_group )
   SELECT pby.principal_id AS by_principal, pto.principal_id AS to_principal,
                                  confers AS privileges, pto.type_id > 2 AS is_group
     FROM relationship r JOIN usr f ON(f.user_no=r.from_user)
                         JOIN usr t ON(t.user_no=r.to_user)
                         JOIN principal pby ON(t.user_no=pby.user_no)
                         JOIN principal pto ON(pto.user_no=f.user_no)
     WHERE rt_id < 4 AND pby.type_id < 3;
