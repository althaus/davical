/**
* PostgreSQL Functions for CalDAV handling
*
* @package rscds
* @subpackage database
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

CREATE or REPLACE FUNCTION apply_month_byday( TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  in_time ALIAS FOR $1;
  byday ALIAS FOR $2;
  weeks INT;
  dow INT;
  temp_txt TEXT;
  dd INT;
  mm INT;
  yy INT;
  our_dow INT;
  our_answer TIMESTAMP WITH TIME ZONE;
BEGIN
  dow := position(substring( byday from '..$') in 'SUMOTUWETHFRSA') / 2;
  temp_txt   := substring(byday from '([0-9]+)');
  weeks      := temp_txt::int;

  -- RAISE NOTICE 'DOW: %, Weeks: %(%s)', dow, weeks, temp_txt;

  IF substring(byday for 1) = '-' THEN
    -- Last XX of month, or possibly second-to-last, but unlikely
    mm := extract( 'month' from in_time);
    yy := extract( 'year' from in_time);

    -- Start with the last day of the month
    our_answer := (yy::text || '-' || (mm+1)::text || '-01')::timestamp - '1 day'::interval;
    dd := extract( 'dow' from our_answer);
    dd := dd - dow;
    IF dd < 0 THEN
      dd := dd + 7;
    END IF;

    -- Having calculated the right day of the month, we now apply that back to in_time
    -- which contains the otherwise-unobtainable timezone detail (and the time)
    our_answer = our_answer - (dd::text || 'days')::interval;
    dd := extract( 'day' from our_answer) - extract( 'day' from in_time);
    our_answer := in_time + (dd::text || 'days')::interval;

    IF weeks > 1 THEN
      weeks := weeks - 1;
      our_answer := our_answer - (weeks::text || 'weeks')::interval;
    END IF;

  ELSE

    -- Shift our date to the correct day of week..
    our_dow := extract( 'dow' from in_time);
    our_dow := our_dow - dow;
    dd := extract( 'day' from in_time);
    IF our_dow >= dd THEN
      our_dow := our_dow - 7;
    END IF;
    our_answer := in_time - (our_dow::text || 'days')::interval;
    dd = extract( 'day' from our_answer);

    -- Shift the date to the correct week...
    dd := weeks - ((dd+6) / 7);
    IF dd != 0 THEN
      our_answer := our_answer + ((dd::text || 'weeks')::interval);
    END IF;

  END IF;

  RETURN our_answer;

END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


CREATE or REPLACE FUNCTION calculate_later_timestamp( TIMESTAMP WITH TIME ZONE, TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  earliest ALIAS FOR $1;
  basedate ALIAS FOR $2;
  repeatrule ALIAS FOR $3;
  frequency TEXT;
  temp_txt TEXT;
  length INT;
  count INT;
  byday TEXT;
  bymonthday INT;
  basediff INTERVAL;
  past_repeats INT8;
  units TEXT;
  dow TEXT;
  our_answer TIMESTAMP WITH TIME ZONE;
  loopcount INT;
BEGIN
  IF basedate > earliest THEN
    RETURN basedate;
  END IF;

  temp_txt   := substring(repeatrule from 'UNTIL=([0-9TZ]+)(;|$)');
  IF temp_txt IS NOT NULL AND temp_txt::timestamp with time zone < earliest THEN
    RETURN NULL;
  END IF;

  frequency  := substring(repeatrule from 'FREQ=([A-Z]+)(;|$)');
  IF frequency IS NULL THEN
    RETURN NULL;
  END IF;

  past_repeats = 0;
  length = 1;
  temp_txt   := substring(repeatrule from 'INTERVAL=([0-9]+)(;|$)');
  IF temp_txt IS NOT NULL THEN
    length     := temp_txt::int;
    basediff   := earliest - basedate;

    -- RAISE NOTICE 'Frequency: %, Length: %(%), Basediff: %', frequency, length, temp_txt, basediff;

    -- Calculate the number of past periods between our base date and our earliest date
    IF frequency = 'WEEKLY' OR frequency = 'DAILY' THEN
      past_repeats := extract('epoch' from basediff)::INT8 / 86400;
      -- RAISE NOTICE 'Days: %', past_repeats;
      IF frequency = 'WEEKLY' THEN
        past_repeats := past_repeats / 7;
      END IF;
    ELSE
      past_repeats = extract( 'years' from basediff );
      IF frequency = 'MONTHLY' THEN
        past_repeats = (past_repeats *12) + extract( 'months' from basediff );
      END IF;
    END IF;
    IF length IS NOT NULL THEN
      past_repeats = (past_repeats / length) + 1;
    END IF;
  END IF;

  -- Check that we have not exceeded the COUNT= limit
  temp_txt := substring(repeatrule from 'COUNT=([0-9]+)(;|$)');
  IF temp_txt IS NOT NULL THEN
    count := temp_txt::int;
    -- RAISE NOTICE 'Periods: %, Count: %(%), length: %', past_repeats, count, temp_txt, length;
    IF ( count <= past_repeats ) THEN
      RETURN NULL;
    END IF;
  ELSE
    count := NULL;
  END IF;

  temp_txt := substring(repeatrule from 'BYSETPOS=([0-9-]+)(;|$)');
  byday := substring(repeatrule from 'BYDAY=([0-9A-Z,]+-)(;|$)');
  IF byday IS NOT NULL AND frequency = 'MONTHLY' THEN
    -- Since this could move the date around a month we go back one
    -- period just to be extra sure.
    past_repeats = past_repeats - 1;

    IF temp_txt IS NOT NULL THEN
      -- Crudely hack the BYSETPOS onto the front of BYDAY.  While this
      -- is not as per rfc2445, RRULE syntax is so complex and overblown
      -- that nobody correctly uses comma-separated BYDAY or BYSETPOS, and
      -- certainly not within a MONTHLY RRULE.
      byday := temp_txt || byday;
    END IF;
  END IF;

  past_repeats = past_repeats * length;

  units := CASE
    WHEN frequency = 'DAILY' THEN 'days'
    WHEN frequency = 'WEEKLY' THEN 'weeks'
    WHEN frequency = 'MONTHLY' THEN 'months'
    WHEN frequency = 'YEARLY' THEN 'years'
  END;

  temp_txt   := substring(repeatrule from 'BYMONTHDAY=([0-9,]+)(;|$)');
  bymonthday := temp_txt::int;

  -- With all of the above calculation, this date should be close to (but less than)
  -- the target, and we should only loop once or twice.
  our_answer := basedate + (past_repeats::text || units)::interval;

  IF our_answer IS NULL THEN
    RAISE EXCEPTION 'our_answer IS NULL! basedate:% past_repeats:% units:%', basedate, past_repeats, units;
  END IF;


  loopcount := 500;  -- Desirable to stop an infinite loop if there is something we cannot handle
  LOOP
    -- RAISE NOTICE 'Testing date: %', our_answer;
    IF frequency = 'DAILY' THEN
      IF byday IS NOT NULL THEN
        LOOP
          dow = substring( to_char( our_answer, 'DY' ) for 2);
          EXIT WHEN byday ~* dow;
          -- Increment for our next time through the loop...
          our_answer := our_answer + (length::text || units)::interval;
        END LOOP;
      END IF;
    ELSIF frequency = 'WEEKLY' THEN
      -- Weekly repeats are only on specific days
      -- This is really not right, since a WEEKLY on MO,WE,FR should
      -- occur three times each week and this will only be once a week.
      dow = substring( to_char( our_answer, 'DY' ) for 2);
    ELSIF frequency = 'MONTHLY' THEN
      IF byday IS NOT NULL THEN
        -- This works fine, except that maybe there are multiple BYDAY
        -- components.  e.g. 1TU,3TU might be 1st & 3rd tuesdays.
        our_answer := apply_month_byday( our_answer, byday );
      ELSE
        -- If we did not get a BYDAY= then we kind of have to assume it is the same day each month
        our_answer := our_answer + '1 month'::interval;
      END IF;
    ELSIF bymonthday IS NOT NULL AND frequency = 'MONTHLY' AND bymonthday < 1 THEN
      -- We do not deal with this situation at present
      RAISE NOTICE 'The case of negative BYMONTHDAY is not handled yet.';
    END IF;

    EXIT WHEN our_answer >= earliest;

    -- Give up if we have exceeded the count
    IF ( count IS NOT NULL AND past_repeats > count ) THEN
      RETURN NULL;
    ELSE
      past_repeats := past_repeats + 1;
    END IF;

    loopcount := loopcount - 1;
    IF loopcount < 0 THEN
      RAISE NOTICE 'Giving up on repeat rule "%" - after 100 increments from % we are still not after %', repeatrule, basedate, earliest;
      RETURN NULL;
    END IF;

    -- Increment for our next time through the loop...
    our_answer := our_answer + (length::text || units)::interval;

  END LOOP;

  RETURN our_answer;

END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


CREATE or REPLACE FUNCTION usr_is_role( INT, TEXT ) RETURNS BOOLEAN AS $$
  SELECT EXISTS( SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_member.user_no=$1 AND roles.role_name=$2 )
$$ LANGUAGE 'sql' IMMUTABLE STRICT;

CREATE or REPLACE FUNCTION get_permissions( INT, INT ) RETURNS TEXT AS $$
DECLARE
  in_from ALIAS FOR $1;
  in_to   ALIAS FOR $2;
  out_confers TEXT;
  tmp_confers1 TEXT;
  tmp_confers2 TEXT;
  tmp_txt TEXT;
  dbg TEXT DEFAULT '';
  r RECORD;
  counter INT;
BEGIN
  -- Self can always have full access
  IF in_from = in_to THEN
    RETURN 'A';
  END IF;

  -- dbg := 'S-';
  SELECT rt1.confers INTO out_confers FROM relationship r1 JOIN relationship_type rt1 USING ( rt_id )
                    WHERE r1.from_user = in_from AND r1.to_user = in_to AND NOT usr_is_role(r1.to_user,'Group');
  IF FOUND THEN
    RETURN dbg || out_confers;
  END IF;
  -- RAISE NOTICE 'No simple relationships between % and %', in_from, in_to;

  out_confers := '';
  FOR r IN SELECT rt1.confers AS r1, rt2.confers AS r2 FROM relationship r1 JOIN relationship_type rt1 USING(rt_id)
              JOIN relationship r2 ON r1.to_user=r2.from_user JOIN relationship_type rt2 ON r2.rt_id=rt2.rt_id
         WHERE r1.from_user=in_from AND r2.to_user=in_to
           AND EXISTS( SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_member.user_no=r1.to_user AND roles.role_name='Group')
           AND NOT EXISTS( SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_member.user_no=r2.to_user AND roles.role_name='Group')
           AND NOT EXISTS( SELECT 1 FROM role_member JOIN roles USING(role_no) WHERE role_member.user_no=r1.from_user AND roles.role_name='Group')
  LOOP
    -- RAISE NOTICE 'Permissions to group % from group %', r.r1, r.r2;
    -- FIXME: This is an oversimplification
    -- dbg := 'C-';
    tmp_confers1 := r.r1;
    tmp_confers2 := r.r2;
    IF tmp_confers1 != tmp_confers2 THEN
      IF tmp_confers1 ~* 'A' THEN
        -- Ensure that A is expanded to all supported privs before being used as a mask
        tmp_confers1 := 'AFBRWU';
      END IF;
      IF tmp_confers2 ~* 'A' THEN
        -- Ensure that A is expanded to all supported privs before being used as a mask
        tmp_confers2 := 'AFBRWU';
      END IF;
      -- RAISE NOTICE 'Expanded permissions to group % from group %', tmp_confers1, tmp_confers2;
      tmp_txt = '';
      FOR counter IN 1 .. length(tmp_confers2) LOOP
        IF tmp_confers1 ~* substring(tmp_confers2,counter,1) THEN
          tmp_txt := tmp_txt || substring(tmp_confers2,counter,1);
        END IF;
      END LOOP;
      tmp_confers2 := tmp_txt;
    END IF;
    FOR counter IN 1 .. length(tmp_confers2) LOOP
      IF NOT out_confers ~* substring(tmp_confers2,counter,1) THEN
        out_confers := out_confers || substring(tmp_confers2,counter,1);
      END IF;
    END LOOP;
  END LOOP;
  IF out_confers ~* 'A' OR (out_confers ~* 'B' AND out_confers ~* 'F' AND out_confers ~* 'R' AND out_confers ~* 'W' AND out_confers ~* 'U') THEN
    out_confers := 'A';
  END IF;
  IF out_confers != '' THEN
    RETURN dbg || out_confers;
  END IF;

  -- RAISE NOTICE 'No complex relationships between % and %', in_from, in_to;

  SELECT rt1.confers INTO out_confers, tmp_confers1 FROM relationship r1 JOIN relationship_type rt1 ON ( r1.rt_id = rt1.rt_id )
              LEFT OUTER JOIN relationship r2 ON ( rt1.rt_id = r2.rt_id )
       WHERE r1.from_user = in_from AND r2.from_user = in_to AND r1.from_user != r2.from_user AND r1.to_user = r2.to_user
         AND NOT EXISTS( SELECT 1 FROM relationship r3 WHERE r3.from_user = r1.to_user )
          AND usr_is_role(r1.to_user,'Group');

  IF FOUND THEN
    -- dbg := 'H-';
    -- RAISE NOTICE 'Permissions to shared group % ', out_confers;
    RETURN dbg || out_confers;
  END IF;

  -- RAISE NOTICE 'No common group relationships between % and %', in_from, in_to;

  RETURN '';
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


-- Function to convert a PostgreSQL date into UTC + the format used by iCalendar
CREATE or REPLACE FUNCTION to_ical_utc( TIMESTAMP WITH TIME ZONE ) RETURNS TEXT AS $$
  SELECT to_char( $1 at time zone 'UTC', 'YYYYMMDD"T"HH24MISS"Z"' )
$$ LANGUAGE 'sql' IMMUTABLE STRICT;

-- Function to set an arbitrary DAV property
CREATE or REPLACE FUNCTION set_dav_property( TEXT, INTEGER, TEXT, TEXT ) RETURNS BOOLEAN AS $$
DECLARE
  path ALIAS FOR $1;
  user ALIAS FOR $2;
  key ALIAS FOR $3;
  value ALIAS FOR $4;
  tmp_int INT;
BEGIN
  -- Check that there is either a resource, collection or user at this location.
  IF NOT EXISTS( SELECT 1 FROM caldav_data WHERE dav_name = path UNION SELECT 1 FROM collection WHERE dav_name = path ) THEN
    RETURN FALSE;
  END IF;
  SELECT changed_by INTO tmp_int FROM property WHERE dav_name = path AND property_name = key;
  IF FOUND THEN
    UPDATE property SET changed_by=user, changed_on=current_timestamp, property_value=value WHERE dav_name = path AND property_name = key;
  ELSE
    INSERT INTO property ( dav_name, changed_by, changed_on, property_name, property_value ) VALUES( path, user, current_timestamp, key, value );
  END IF;
  RETURN TRUE;
END;
$$ LANGUAGE 'plpgsql' STRICT;

-- List a user's relationships as a text string
CREATE or REPLACE FUNCTION relationship_list( INTEGER ) RETURNS TEXT AS $$
DECLARE
  user ALIAS FOR $1;
  r RECORD;
  rlist TEXT;
BEGIN
  rlist := '';
  FOR r IN SELECT rt_name, fullname FROM relationship
                          LEFT JOIN relationship_type USING(rt_id) LEFT JOIN usr tgt ON to_user = tgt.user_no
                          WHERE from_user = user
  LOOP
    rlist := rlist
             || CASE WHEN rlist = '' THEN '' ELSE ', ' END
             || r.rt_name || '(' || r.fullname || ')';
  END LOOP;
  RETURN rlist;
END;
$$ LANGUAGE 'plpgsql';

DROP FUNCTION rename_davical_user( TEXT, TEXT );
DROP TRIGGER usr_modified ON usr CASCADE;
CREATE or REPLACE FUNCTION usr_modified() RETURNS TRIGGER AS $$
DECLARE
  oldpath TEXT;
  newpath TEXT;
BEGIN
  -- in case we trigger on other events in future
  IF TG_OP = 'UPDATE' THEN
    IF NEW.username != OLD.username THEN
      oldpath := '/' || OLD.username || '/';
      newpath := '/' || NEW.username || '/';
      UPDATE collection
        SET parent_container = replace( parent_container, oldpath, newpath),
            dav_name = replace( dav_name, oldpath, newpath)
      WHERE substring(dav_name from 1 for char_length(oldpath)) = oldpath;
    END IF;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER usr_modified AFTER UPDATE ON usr
    FOR EACH ROW EXECUTE PROCEDURE usr_modified();


DROP TRIGGER collection_modified ON collection CASCADE;
CREATE or REPLACE FUNCTION collection_modified() RETURNS TRIGGER AS $$
DECLARE
BEGIN
  -- in case we trigger on other events in future
  IF TG_OP = 'UPDATE' THEN
    IF NEW.dav_name != OLD.dav_name THEN
      UPDATE caldav_data
        SET dav_name = replace( dav_name, OLD.dav_name, NEW.dav_name)
      WHERE substring(dav_name from 1 for char_length(OLD.dav_name)) = OLD.dav_name;
    END IF;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER collection_modified AFTER UPDATE ON collection
    FOR EACH ROW EXECUTE PROCEDURE collection_modified();


DROP TRIGGER caldav_data_modified ON caldav_data CASCADE;
CREATE or REPLACE FUNCTION caldav_data_modified() RETURNS TRIGGER AS $$
DECLARE
  coll_id caldav_data.collection_id%TYPE;
BEGIN
  IF TG_OP = 'UPDATE' THEN
    IF NEW.caldav_data = OLD.caldav_data AND NEW.collection_id = OLD.collection_id THEN
      -- Nothing for us to do
      RETURN NEW;
    END IF;
  END IF;

  IF TG_OP = 'INSERT' OR TG_OP = 'UPDATE' THEN
    -- On insert or update modified, we set the NEW collection tag to the md5 of the
    -- etag of the updated row which gives us something predictable for our regression
    -- tests, but something different from the actual etag of the new event.
    UPDATE collection
       SET modified = current_timestamp, dav_etag = md5(NEW.dav_etag)
     WHERE collection_id = NEW.collection_id;
    IF TG_OP = 'INSERT' THEN
      RETURN NEW;
    END IF;
  END IF;

  IF TG_OP = 'DELETE' THEN
    -- On delete we set the OLD collection tag to the md5 of the old path & the old
    -- etag, which again gives us something predictable for our regression tests.
    UPDATE collection
       SET modified = current_timestamp, dav_etag = md5(OLD.dav_name::text||OLD.dav_etag)
     WHERE collection_id = OLD.collection_id;
    RETURN OLD;
  END IF;

  IF NEW.collection_id != OLD.collection_id THEN
    -- If we've switched the collection_id of this event, then we also need to update
    -- the etag of the old collection - as we do for delete.
    UPDATE collection
       SET modified = current_timestamp, dav_etag = md5(OLD.dav_name::text||OLD.dav_etag)
     WHERE collection_id = OLD.collection_id;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER caldav_data_modified AFTER INSERT OR UPDATE OR DELETE ON caldav_data
    FOR EACH ROW EXECUTE PROCEDURE caldav_data_modified();


CREATE or REPLACE FUNCTION proxy_list( INT ) RETURNS SETOF grants AS $$ 
  SELECT by_principal, dav_name, to_principal, privileges, is_group FROM grants
            WHERE by_principal = $1 AND NOT is_group AND (privileges & 7::BIT(24))::INT::BOOLEAN
  UNION SELECT by_principal, dav_name, member_id, privileges, is_group FROM grants JOIN group_member ON (to_principal=group_id)
            WHERE by_principal = $1 and is_group AND (privileges & 7::BIT(24))::INT::BOOLEAN
  UNION SELECT by_principal, dav_name, to_principal, privileges, is_group FROM grants
            WHERE to_principal = $1 AND NOT is_group AND (privileges & 7::BIT(24))::INT::BOOLEAN
  UNION SELECT by_principal, dav_name, member_id, privileges, is_group FROM grants JOIN group_member ON (to_principal=group_id)
            WHERE member_id = $1 and is_group AND (privileges & 7::BIT(24))::INT::BOOLEAN
$$ LANGUAGE 'SQL' STRICT;
