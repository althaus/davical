/**
* PostgreSQL Functions for CalDAV handling
*
* @package rscds
* @subpackage database
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

CREATE or REPLACE FUNCTION apply_month_byday( TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS TIMESTAMP WITH TIME ZONE AS '
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
  dow := position(substring( byday from ''..$'') in ''SUMOTUWETHFRSA'') / 2;
  temp_txt   := substring(byday from ''([0-9]+)'');
  weeks      := temp_txt::int;

  -- RAISE NOTICE ''DOW: %, Weeks: %(%s)'', dow, weeks, temp_txt;

  IF substring(byday for 1) = ''-'' THEN
    -- Last XX of month, or possibly second-to-last, but unlikely
    mm := extract( ''month'' from in_time);
    yy := extract( ''year'' from in_time);

    -- Start with the last day of the month
    our_answer := (yy::text || ''-'' || (mm+1)::text || ''-01'')::timestamp - ''1 day''::interval;
    dd := extract( ''dow'' from our_answer);
    dd := dd - dow;
    IF dd < 0 THEN
      dd := dd + 7;
    END IF;

    -- Having calculated the right day of the month, we now apply that back to in_time
    -- which contains the otherwise-unobtainable timezone detail (and the time)
    our_answer = our_answer - (dd::text || ''days'')::interval;
    dd := extract( ''day'' from our_answer) - extract( ''day'' from in_time);
    our_answer := in_time + (dd::text || ''days'')::interval;

    IF weeks > 1 THEN
      weeks := weeks - 1;
      our_answer := our_answer - (weeks::text || ''weeks'')::interval;
    END IF;

  ELSE

    -- Shift our date to the correct day of week..
    our_dow := extract( ''dow'' from in_time);
    our_dow := our_dow - dow;
    dd := extract( ''day'' from in_time);
    IF our_dow >= dd THEN
      our_dow := our_dow - 7;
    END IF;
    our_answer := in_time - (our_dow::text || ''days'')::interval;
    dd = extract( ''day'' from our_answer);

    -- Shift the date to the correct week...
    dd := weeks - ((dd+6) / 7);
    IF dd != 0 THEN
      our_answer := our_answer + ((dd::text || ''weeks'')::interval);
    END IF;

  END IF;

  RETURN our_answer;

END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;


CREATE or REPLACE FUNCTION calculate_later_timestamp( TIMESTAMP WITH TIME ZONE, TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS TIMESTAMP WITH TIME ZONE AS '
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
  temp_txt   := substring(repeatrule from ''UNTIL=([0-9TZ]+)(;|$)'');
  IF temp_txt::timestamp with time zone < earliest THEN
    RETURN NULL;
  END IF;

  frequency  := substring(repeatrule from ''FREQ=([A-Z]+)(;|$)'');
  temp_txt   := substring(repeatrule from ''INTERVAL=([0-9]+)(;|$)'');
  length     := temp_txt::int;
  basediff   := earliest - basedate;

  -- RAISE NOTICE ''Frequency: %, Length: %(%), Basediff: %'', frequency, length, temp_txt, basediff;

  -- Calculate the number of past periods between our base date and our earliest date
  IF frequency = ''WEEKLY'' OR frequency = ''DAILY'' THEN
    past_repeats := extract(''epoch'' from basediff)::INT8 / 86400;
    -- RAISE NOTICE ''Days: %'', past_repeats;
    IF frequency = ''WEEKLY'' THEN
      past_repeats := past_repeats / 7;
    END IF;
  ELSE
    past_repeats = extract( ''years'' from basediff );
    IF frequency = ''MONTHLY'' THEN
      past_repeats = (past_repeats *12) + extract( ''months'' from basediff );
    END IF;
  END IF;
  past_repeats = (past_repeats / length) + 1;

  -- Check that we have not exceeded the COUNT= limit
  temp_txt := substring(repeatrule from ''COUNT=([0-9]+)(;|$)'');
  count := temp_txt::int;
  -- RAISE NOTICE ''Periods: %, Count: %(%)'', past_repeats, count, temp_txt;
  IF ( count <= past_repeats ) THEN
    RETURN NULL;
  END IF;

  temp_txt := substring(repeatrule from ''BYSETPOS=([0-9-]+)(;|$)'');
  byday := substring(repeatrule from ''BYDAY=([0-9A-Z,]+-)(;|$)'');
  IF byday IS NOT NULL AND frequency = ''MONTHLY'' THEN
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
    WHEN frequency = ''DAILY'' THEN ''days''
    WHEN frequency = ''WEEKLY'' THEN ''weeks''
    WHEN frequency = ''MONTHLY'' THEN ''months''
    WHEN frequency = ''YEARLY'' THEN ''years''
  END;

  temp_txt   := substring(repeatrule from ''BYMONTHDAY=([0-9,]+)(;|$)'');
  bymonthday := temp_txt::int;

  -- With all of the above calculation, this date should be close to (but less than)
  -- the target, and we should only loop once or twice.
  our_answer := basedate + (past_repeats::text || units)::interval;

  loopcount := 1000;  -- Not really needed, but stops an infinite loop if there is a bug!
  LOOP
    -- RAISE NOTICE ''Testing date: %'', our_answer;
    IF frequency = ''WEEKLY'' THEN
      -- Weekly repeats are only on specific days
      -- I think this is not really right, since a WEEKLY on MO,WE,FR should
      -- occur three times each week and this will only be once a week.
      dow = substring( to_char( our_answer, ''DY'' ) for 2);
      CONTINUE WHEN position( dow in byday ) = 0;
    ELSIF frequency = ''MONTHLY'' AND byday IS NOT NULL THEN
      -- This works fine, except that maybe there are multiple BYDAY
      -- components.  e.g. 1TU,3TU might be 1st & 3rd tuesdays.
      our_answer := apply_month_byday( our_answer, byday );
    ELSIF bymonthday IS NOT NULL AND frequency = ''MONTHLY'' AND bymonthday < 1 THEN
      -- We do not deal with this situation at present
      RAISE NOTICE ''The case of negative BYMONTHDAY is not handled yet.'';
    END IF;

    EXIT WHEN our_answer >= earliest;

    loopcount := loopcount - 1;
    IF loopcount < 0 THEN
      RAISE EXCEPTION ''Could not cope with dates after % using % from %'', earliest, repeatrule, basedate;
      RETURN NULL;
    END IF;

    -- Increment for our next time through the loop...
    our_answer := our_answer + (length::text || units)::interval;
  END LOOP;

  RETURN our_answer;

END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;

CREATE or REPLACE FUNCTION get_permissions( INT, INT ) RETURNS TEXT AS '
DECLARE
  in_from ALIAS FOR $1;
  in_to   ALIAS FOR $2;
  out_confers TEXT;
  tmp_confers TEXT;
  dbg TEXT DEFAULT '''';
BEGIN
  -- dbg := ''S-'';
  SELECT rt1.confers INTO out_confers FROM relationship r1 JOIN relationship_type rt1 USING ( rt_id )
   WHERE NOT rt1.rt_isgroup AND r1.from_user = in_from AND r1.to_user = in_to;
  IF FOUND THEN
    RETURN dbg || out_confers;
  END IF;
  -- RAISE NOTICE ''No simple relationships between % and %'', in_from, in_to;

  SELECT rt1.confers, rt2.confers INTO out_confers, tmp_confers FROM relationship r1 JOIN relationship_type rt1 ON ( r1.rt_id = rt1.rt_id )
              LEFT OUTER JOIN relationship r2 ON ( r1.to_user = r2.from_user ) JOIN relationship_type rt2 ON ( r2.rt_id = rt2.rt_id )
   WHERE rt1.rt_isgroup AND NOT rt2.rt_isgroup AND r1.from_user = in_from AND r2.to_user = in_to;

  IF FOUND THEN
    -- RAISE NOTICE ''Permissions to group % from group %'', out_confers, tmp_confers;
    -- FIXME: This is an oversimplification
    -- dbg := ''C-'';
    IF out_confers = tmp_confers THEN
      RETURN dbg || out_confers;
    ELSE
      IF length( out_confers ) < length( tmp_confers ) THEN
        RETURN dbg || out_confers;
      ELSE
        RETURN dbg || tmp_confers;
      END IF;
    END IF;
  END IF;

  -- RAISE NOTICE ''No complex relationships between % and %'', in_from, in_to;

  SELECT rt1.confers INTO out_confers, tmp_confers FROM relationship r1 JOIN relationship_type rt1 ON ( r1.rt_id = rt1.rt_id )
              LEFT OUTER JOIN relationship r2 ON ( rt1.rt_id = r2.rt_id )
   WHERE rt1.rt_isgroup AND r1.from_user = in_from AND r2.from_user = in_to AND r1.from_user != r2.from_user AND r1.to_user = r2.to_user;

  IF FOUND THEN
    -- dbg := ''H-'';
    -- RAISE NOTICE ''Permissions to shared group % '', out_confers;
    RETURN dbg || out_confers;
  END IF;

  -- RAISE NOTICE ''No common group relationships between % and %'', in_from, in_to;

  RETURN '''';
END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;
