
-- This database update converts the permissions into a bitmap stored
-- as an integer to make calculation of merged permissions simpler
-- through simple binary 'AND' 

BEGIN;
SELECT check_db_revision(1,2,5);

\i dba/better_perms.sql

-- DAV Privileges implementation
--
-- RFC 3744 - DAV ACLs
--      1 DAV:read
--   DAV:write (aggregate = 198) 
--      2 DAV:write-properties
--      4 DAV:write-content
--      8 DAV:unlock
--     16 DAV:read-acl
--     32 DAV:read-current-user-privilege-set
--     64 DAV:bind 
--    128 DAV:unbind 
--    256 DAV:write-acl

-- RFC 4791 - CalDAV
--    512 CalDAV:read-free-busy

-- RFC ???? - Scheduling Extensions for CalDAV
-- CALDAV:schedule-deliver (aggregate) => 7168
--   1024 CALDAV:schedule-deliver-invite
--   2048 CALDAV:schedule-deliver-reply
--   4096 CALDAV:schedule-query-freebusy
-- CALDAV:schedule-send (aggregate) => 57344
--   8192 CALDAV:schedule-send-invite
--  16384 CALDAV:schedule-send-reply
--  32768 CALDAV:schedule-send-freebusy

-- RFC 3744 - DAV ACLs
-- DAV:all => all of the above and any new ones someone might invent!

-- DAV:read-acl MUST NOT contain DAV:read, DAV:write, DAV:write-acl, DAV:write-properties, DAV:write-content, or DAV:read-current-user-privilege-set.
-- DAV:write-acl MUST NOT contain DAV:write, DAV:read, DAV:read-acl, DAV:read-current-user-privilege-set.
-- DAV:read-current-user-privilege-set MUST NOT contain DAV:write, DAV:read, DAV:read-acl, or DAV:write-acl.
-- DAV:write MUST NOT contain DAV:read, DAV:read-acl, or DAV:read-current-user-privilege-set.
-- DAV:read MUST NOT contain DAV:write, DAV:write-acl, DAV:write-properties, or DAV:write-content.
-- DAV:write-acl COULD contain DAV:write-properties DAV:write-content DAV:unlock DAV:bind DAV:unbind BUT why would it?
 
-- DAV:write => DAV:bind, DAV:unbind, DAV:write-properties and DAV:write-content

-- RFC 4791 - CalDAV
-- The CALDAV:read-free-busy privilege MUST be aggregated in the DAV:read privilege.

-- RFC ???? - Scheduling Extensions for CalDAV
--  DAV:all MUST contain CALDAV:schedule-send and CALDAV:schedule-deliver
--  CALDAV:schedule-send MUST contain CALDAV:schedule-send-invite, CALDAV:schedule-send-reply, and CALDAV:schedule-send-freebusy;
--  CALDAV:schedule-deliver MUST contain CALDAV:schedule-deliver-invite, CALDAV:schedule-deliver-reply, and CALDAV:schedule-query-freebusy.


-- Me!!!
-- CalDAV:read-free-busy privilege SHOULD contain CALDAV:schedule-query-freebusy
-- => DAV:read privilege SHOULD contain CALDAV:schedule-query-freebusy


-- This legacy conversion function will eventually be removed, once all logic
-- has been converted to use bitmaps, or to use the bits_to_priv() output.
CREATE or REPLACE FUNCTION legacy_privilege_to_bits( TEXT ) RETURNS BIT(24) AS $$
DECLARE
  in_priv ALIAS FOR $1;
  out_bits BIT(24);
BEGIN
  out_bits := 0::BIT(24);
  IF in_priv ~* 'A' THEN
    out_bits = ~ out_bits;
    RETURN out_bits;
  END IF;

  -- The CALDAV:read-free-busy privilege MUST be aggregated in the DAV:read privilege.
  --    1 DAV:read
  --  512 CalDAV:read-free-busy
  -- 4096 CALDAV:schedule-query-freebusy
  IF in_priv ~* 'R' THEN
    out_bits := out_bits | 4609::BIT(24);
  END IF;
  
  -- DAV:write => DAV:write MUST contain DAV:bind, DAV:unbind, DAV:write-properties and DAV:write-content
  --    2 DAV:write-properties
  --    4 DAV:write-content
  --   64 DAV:bind 
  --  128 DAV:unbind 
  IF in_priv ~* 'W' THEN
    out_bits := out_bits |   198::BIT(24);
  END IF;
  
  --   64 DAV:bind 
  IF in_priv ~* 'B' THEN
    out_bits := out_bits | 64::BIT(24);
  END IF;
  
  --  128 DAV:unbind 
  IF in_priv ~* 'U' THEN
    out_bits := out_bits | 128::BIT(24);
  END IF;

  --  512 CalDAV:read-free-busy
  -- 4096 CALDAV:schedule-query-freebusy
  IF in_priv ~* 'F' THEN
    out_bits := out_bits | 4608::BIT(24);
  END IF;
  
  RETURN out_bits;
END 
$$
LANGUAGE 'PlPgSQL' IMMUTABLE STRICT;


ALTER TABLE relationship_type ADD COLUMN bit_confers BIT(24) DEFAULT legacy_privilege_to_bits('RW');
UPDATE relationship_type SET bit_confers = legacy_privilege_to_bits(confers);

ALTER TABLE relationship ADD COLUMN confers BIT(24) DEFAULT legacy_privilege_to_bits('F');
UPDATE relationship r SET confers = bit_confers FROM relationship_type rt WHERE rt.rt_id=r.rt_id;

ALTER TABLE collection ADD COLUMN default_privileges BIT(24) DEFAULT legacy_privilege_to_bits('F');

INSERT INTO principal_type (principal_type_id, principal_type_desc) VALUES( 1, 'Person' );
INSERT INTO principal_type (principal_type_id, principal_type_desc) VALUES( 2, 'Resource' );
INSERT INTO principal_type (principal_type_id, principal_type_desc) VALUES( 3, 'Group' );

-- web needs SELECT,INSERT,UPDATE,DELETE
DROP TABLE principal CASCADE;
CREATE TABLE principal (
  principal_id SERIAL PRIMARY KEY,
  type_id INT8 NOT NULL REFERENCES principal_type(principal_type_id) ON UPDATE CASCADE ON DELETE RESTRICT DEFERRABLE,
  user_no INT8 NULL REFERENCES usr(user_no) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  displayname TEXT,
  active BOOLEAN,
  default_privileges BIT(24)
);

INSERT INTO principal (type_id, user_no, displayname, active, default_privileges)
         SELECT 1, user_no, fullname, active, privilege_to_bits(ARRAY['read-free-busy','schedule-send','schedule-deliver']) FROM usr
                 WHERE NOT EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Group' AND role_member.user_no = usr.user_no) 
                   AND NOT EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Resource' AND role_member.user_no = usr.user_no) ; 

INSERT INTO principal (type_id, user_no, displayname, active, default_privileges)
         SELECT 2, user_no, fullname, active, privilege_to_bits(ARRAY['read','schedule-send','schedule-deliver']) FROM usr
                 WHERE EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Resource' AND role_member.user_no = usr.user_no); 

INSERT INTO principal (type_id, user_no, displayname, active, default_privileges)
         SELECT 3, user_no, fullname, active, privilege_to_bits(ARRAY['read-free-busy','schedule-send','schedule-deliver']) FROM usr
                 WHERE EXISTS(SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_name = 'Group' AND role_member.user_no = usr.user_no); 

UPDATE collection SET default_privileges = CASE
                        WHEN publicly_readable THEN privilege_to_bits(ARRAY['read'])
                        ELSE (SELECT default_privileges FROM principal WHERE principal.user_no = collection.user_no)
                  END;  

-- Allowing identification of group members.
DROP TABLE group_member CASCADE;
CREATE TABLE group_member (
  group_id INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  member_id INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE
);
CREATE UNIQUE INDEX group_member_pk ON group_member(group_id,member_id);
CREATE INDEX group_member_sk ON group_member(member_id);
INSERT INTO group_member ( group_id, member_id)
              SELECT g.principal_id, m.principal_id
                FROM relationship JOIN principal g ON(to_user=g.user_no AND g.type_id = 3)    -- Group
                                  JOIN principal m ON(from_user=m.user_no AND m.type_id = 1); -- Person

DROP TABLE dav_resource_type CASCADE;
DROP TABLE dav_resource CASCADE;
DROP TABLE privilege CASCADE;

CREATE TABLE grants (
  by_principal INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  dav_name TEXT,
  to_principal INT8 REFERENCES principal(principal_id) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE,
  privileges BIT(24),
  is_group BOOLEAN,
  PRIMARY KEY (dav_name, to_principal)
) WITHOUT OIDS;


INSERT INTO grants ( by_principal, dav_name, to_principal, privileges, is_group )
   SELECT pby.principal_id AS by_principal, '/' ||t.username||'/' AS dav_name, pto.principal_id AS to_principal,
                                  confers AS privileges, pto.type_id > 2 AS is_group
     FROM relationship r JOIN usr f ON(f.user_no=r.from_user)
                         JOIN usr t ON(t.user_no=r.to_user)
                         JOIN principal pby ON(t.user_no=pby.user_no)
                         JOIN principal pto ON(pto.user_no=f.user_no)
     WHERE rt_id < 4 AND pby.type_id < 3;


CREATE or REPLACE FUNCTION get_permissions_new( INT, INT ) RETURNS BIT(24) AS $$
DECLARE
  in_accessor ALIAS FOR $1;
  in_grantor  ALIAS FOR $2;
  out_conferred BIT(24);
BEGIN
  out_conferred := 0::BIT(24);
  -- Self can always have full access
  IF in_grantor = in_accessor THEN
    RETURN ~ out_conferred;
  END IF;

  SELECT bit_or(subquery.privileges) INTO out_conferred FROM
     (SELECT privileges FROM grants WHERE by_principal = in_grantor AND to_principal = in_accessor AND NOT is_group
                       UNION
      SELECT privileges FROM grants JOIN group_member ON (to_principal=group_id AND member_id=in_accessor)
                       WHERE by_principal = in_grantor AND is_group
     ) AS subquery ;
  IF out_conferred IS NULL THEN
    SELECT default_privileges INTO out_conferred FROM principal WHERE principal_id = in_grantor;
  END IF;

  RETURN out_conferred;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;

-- A list of the principals who can proxy to this principal
CREATE or REPLACE FUNCTION i_proxy_to( INT ) RETURNS SETOF grants AS $$ 
SELECT by_principal, dav_name, to_principal, privileges, is_group FROM grants WHERE by_principal = $1 AND NOT is_group
     UNION
SELECT by_principal, dav_name, member_id, privileges, is_group FROM grants
              JOIN group_member ON (to_principal=group_id) where by_principal = $1 and is_group;
$$ LANGUAGE 'SQL' STRICT;

-- A list of the principals who this principal can proxy
CREATE or REPLACE FUNCTION proxied_by( INT ) RETURNS SETOF grants AS $$ 
SELECT by_principal, dav_name, to_principal, privileges, is_group FROM grants WHERE to_principal = $1 AND NOT is_group
     UNION
SELECT by_principal, dav_name, member_id, privileges, is_group FROM grants
              JOIN group_member ON (to_principal=group_id) where member_id = $1 and is_group;
$$ LANGUAGE 'SQL' STRICT;

CREATE or REPLACE FUNCTION proxy_list( INT ) RETURNS SETOF grants AS $$ 
SELECT by_principal, dav_name, to_principal, privileges, is_group FROM grants WHERE by_principal = $1 AND NOT is_group
     UNION
SELECT by_principal, dav_name, member_id, privileges, is_group FROM grants
              JOIN group_member ON (to_principal=group_id) where by_principal = $1 and is_group
     UNION
SELECT by_principal, dav_name, to_principal, privileges, is_group FROM grants WHERE to_principal = $1 AND NOT is_group
     UNION
SELECT by_principal, dav_name, member_id, privileges, is_group FROM grants
              JOIN group_member ON (to_principal=group_id) where member_id = $1 and is_group;
$$ LANGUAGE 'SQL' STRICT;

SELECT new_db_revision(1,2,6, 'Juin' );

COMMIT;
ROLLBACK;

