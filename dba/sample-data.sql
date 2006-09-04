-- Some sample data to prime the database...

INSERT INTO usr ( user_no, active, email_ok, updated, username, password, fullname, email )
    VALUES( 1, TRUE, current_date, current_date, 'andrew', '**x', 'Andrew McMillan', 'andrew@catalyst.net.nz' );

SELECT setval('usr_user_no_seq', 1);
