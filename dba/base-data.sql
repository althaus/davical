-- Some sample data to prime the database...

-- FIXME: Only insert the rows if they are not there already.
INSERT INTO roles ( role_no, role_name ) VALUES( 1, 'Admin');
INSERT INTO roles ( role_no, role_name ) VALUES( 2, 'Group');
INSERT INTO roles ( role_no, role_name ) VALUES( 3, 'Public');
INSERT INTO roles ( role_no, role_name ) VALUES( 4, 'Resource');

-- Set the insert sequence to the next number, with a minimum of 10
SELECT setval('roles_role_no_seq', (SELECT 10 UNION SELECT role_no FROM roles ORDER BY 1 DESC LIMIT 1) );

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES ( 1, TRUE, current_date, current_date, 'admin', '**nimda', 'Calendar Administrator', 'calendars@example.net' );

INSERT INTO role_member (user_no, role_no) VALUES(1, 1);

-- Set the insert sequence to the next number, with a minimum of 1000
SELECT setval('usr_user_no_seq', (SELECT 1000 UNION SELECT user_no FROM usr ORDER BY 1 DESC LIMIT 1) );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, confers, prefix_match )
    VALUES( 1, 'Administers Group', TRUE, 'A', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, confers, prefix_match )
    VALUES( 2, 'is Assistant to', FALSE, 'RW', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, confers, prefix_match )
    VALUES( 3, 'Is a member of group', TRUE, 'R', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, confers, prefix_match )
    VALUES( 4, 'Administers Resource', FALSE, 'A', '' );

INSERT INTO relationship_type ( rt_id, rt_name, rt_isgroup, confers, prefix_match )
    VALUES( 5, 'Can see free/busy time of', FALSE, 'F', '' );


-- Set the insert sequence to the next number, with a minimum of 1000
SELECT setval('relationship_type_rt_id_seq', (SELECT 10 UNION SELECT rt_id FROM relationship_type ORDER BY 1 DESC LIMIT 1) );

