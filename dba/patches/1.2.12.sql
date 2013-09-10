
-- Minor enhancement:	Add columns to time_zone table to support timezone protocol changes.

BEGIN;
SELECT check_db_revision(1,2,11);


CREATE TABLE calendar_attendee_email_status (
  email_status_id SERIAL PRIMARY KEY,
  description TEXT NOT NULL
);

INSERT INTO calendar_attendee_email_status (description)
  VALUES ('accepted'),
  ('wait for invitation sending'),
  ('invitation mail already sent'),
  ('wait for schedule changed sending'),
  ('schedule change mail already sent'),
  ('maybe'),
  ('unaccept');



ALTER TABLE calendar_attendee
  ADD COLUMN email_status INT REFERENCES calendar_attendee_email_status(email_status_id) DEFAULT 1 NOT NULL ;


ALTER TABLE calendar_attendee
  ADD COLUMN is_remote BOOLEAN DEFAULT FALSE;



SELECT new_db_revision(1,2,12, 'Septembre' );

COMMIT;
ROLLBACK;

