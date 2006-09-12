-- Some sample data to prime the database...

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 1, TRUE, current_date, current_date, 'admin', '**nimda', 'Calendar Administrator', 'calendars@example.net' );

SELECT setval('usr_user_no_seq', 1);
