/**
* PostgreSQL Functions for RRULE handling
*
* @package rscds
* @subpackage database
* @author Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*
* Coverage of this function set
*  - COUNT & UNTIL are handled, generally
*  - DAILY frequency, including BYDAY, BYMONTH, BYMONTHDAY
*  - WEEKLY frequency, including BYDAY, BYMONTH, BYMONTHDAY, BYSETPOS
*  - MONTHLY frequency, including BYDAY, BYMONTH, BYSETPOS
*  - YEARLY frequency, including ???
*
* Not covered as yet
*  - DAILY:   BYYEARDAY, BYWEEKNO, BYMONTHDAY, BYSETPOS*
*  - WEEKLY:  BYYEARDAY, BYWEEKNO
*  - MONTHLY: BYYEARDAY, BYMONTHDAY, BYWEEKNO
*  - YEARLY:  BYYEARDAY, BYDAY*, BYSETPOS
*  - SECONDLY
*  - MINUTELY
*  - HOURLY
*
*/

-- Create a composite type for the parts of the RRULE.
DROP TYPE rrule_parts CASCADE;
CREATE TYPE rrule_parts AS (
  base TIMESTAMP WITH TIME ZONE,
  until TIMESTAMP WITH TIME ZONE,
  freq TEXT,
  count INT,
  interval INT,
--  bysecond INT[],
--  byminute INT[],
--  byhour INT[],
  bymonthday INT[],
  byyearday INT[],
  byweekno INT[],
  byday TEXT[],
  bymonth INT[],
  bysetpos INT[],
  wkst TEXT
);


-- Create a function to parse the RRULE into it's composite type
CREATE or REPLACE FUNCTION parse_rrule_parts( TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS rrule_parts AS $$
DECLARE
  basedate   ALIAS FOR $1;
  repeatrule ALIAS FOR $2;
  result rrule_parts%ROWTYPE;
  tempstr TEXT;
BEGIN
  result.base       := basedate;
  result.until      := substring(repeatrule from 'UNTIL=([0-9TZ]+)(;|$)');
  result.freq       := substring(repeatrule from 'FREQ=([A-Z]+)(;|$)');
  result.count      := substring(repeatrule from 'COUNT=([0-9]+)(;|$)');
  result.interval   := COALESCE(substring(repeatrule from 'INTERVAL=([0-9]+)(;|$)')::int, 1);
  result.wkst       := substring(repeatrule from 'WKST=(MO|TU|WE|TH|FR|SA|SU)(;|$)');

  /**
  * We can do the array conversion as a simple cast, since the strings are simple numbers, with no commas
  */
  result.byday    := ('{' || substring(repeatrule from 'BYDAY=(([+-]?[0-9]{0,2}(MO|TU|WE|TH|FR|SA|SU),?)+)(;|$)') || '}')::text[];

  result.byyearday  := ('{' || substring(repeatrule from 'BYYEARDAY=([0-9,+-]+)(;|$)') || '}')::int[];
  result.byweekno   := ('{' || substring(repeatrule from 'BYWEEKNO=([0-9,+-]+)(;|$)') || '}')::int[];
  result.bymonthday := ('{' || substring(repeatrule from 'BYMONTHDAY=([0-9,+-]+)(;|$)') || '}')::int[];
  result.bymonth    := ('{' || substring(repeatrule from 'BYMONTH=(([+-]?[0-1]?[0-9],?)+)(;|$)') || '}')::int[];
  result.bysetpos   := ('{' || substring(repeatrule from 'BYSETPOS=(([+-]?[0-9]{1,3},?)+)(;|$)') || '}')::int[];

--  result.bysecond   := list_to_array(substring(repeatrule from 'BYSECOND=([0-9,]+)(;|$)'))::int[];
--  result.byminute   := list_to_array(substring(repeatrule from 'BYMINUTE=([0-9,]+)(;|$)'))::int[];
--  result.byhour     := list_to_array(substring(repeatrule from 'BYHOUR=([0-9,]+)(;|$)'))::int[];

  RETURN result;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


-- Return a SETOF dates within the month of a particular date which match a string of BYDAY rule specifications
CREATE or REPLACE FUNCTION rrule_month_byday_set( TIMESTAMP WITH TIME ZONE, TEXT[] ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  in_time ALIAS FOR $1;
  byday ALIAS FOR $2;
  dayrule TEXT;
  i INT;
  dow INT;
  index INT;
  first_dow INT;
  each_day TIMESTAMP WITH TIME ZONE;
  this_month INT;
  results TIMESTAMP WITH TIME ZONE[];
BEGIN

  IF byday IS NULL THEN
    -- We still return the single date as a SET
    RETURN NEXT in_time;
    RETURN;
  END IF;

  i := 1;
  dayrule := byday[i];
  WHILE dayrule IS NOT NULL LOOP
    dow := position(substring( dayrule from '..$') in 'SUMOTUWETHFRSA') / 2;
    each_day := date_trunc( 'month', in_time ) + (in_time::time)::interval;
    this_month := date_part( 'month', in_time );
    first_dow := date_part( 'dow', each_day );

    -- Coerce each_day to be the first 'dow' of the month
    each_day := each_day - ( first_dow::text || 'days')::interval
                        + ( dow::text || 'days')::interval
                        + CASE WHEN dow < first_dow THEN '1 week'::interval ELSE '0s'::interval END;

    -- RAISE NOTICE 'From "%", for % finding dates. dow=%, this_month=%, first_dow=%', each_day, dayrule, dow, this_month, first_dow;
    IF length(dayrule) > 2 THEN
      index := (substring(dayrule from '^[0-9-]+'))::int;

      IF index = 0 THEN
        RAISE NOTICE 'Ignored invalid BYDAY rule part "%".', bydayrule;
      ELSIF index > 0 THEN
        -- The simplest case, such as 2MO for the second monday
        each_day := each_day + ((index - 1)::text || ' weeks')::interval;
      ELSE
        each_day := each_day + '5 weeks'::interval;
        WHILE date_part('month', each_day) != this_month LOOP
          each_day := each_day - '1 week'::interval;
        END LOOP;
        -- Note that since index is negative, (-2 + 1) == -1, for example
        index := index + 1;
        IF index < 0 THEN
          each_day := each_day + (index::text || ' weeks')::interval ;
        END IF;
      END IF;

      -- Sometimes (e.g. 5TU or -5WE) there might be no such date in some months
      IF date_part('month', each_day) = this_month THEN
        results[date_part('day',each_day)] := each_day;
        -- RAISE NOTICE 'Added "%" to list for %', each_day, dayrule;
      END IF;

    ELSE
      -- Return all such days that are within the given month
      WHILE date_part('month', each_day) = this_month LOOP
        results[date_part('day',each_day)] := each_day;
        each_day := each_day + '1 week'::interval;
        -- RAISE NOTICE 'Added "%" to list for %', each_day, dayrule;
      END LOOP;
    END IF;

    i := i + 1;
    dayrule := byday[i];
  END LOOP;

  FOR i IN 1..31 LOOP
    IF results[i] IS NOT NULL THEN
      RETURN NEXT results[i];
    END IF;
  END LOOP;

  RETURN;

END;
$$ LANGUAGE 'plpgsql' IMMUTABLE;


-- Return a SETOF dates within the month of a particular date which match a string of BYDAY rule specifications
CREATE or REPLACE FUNCTION rrule_month_bymonthday_set( TIMESTAMP WITH TIME ZONE, INT[] ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  in_time ALIAS FOR $1;
  bymonthday ALIAS FOR $2;
  month_start TIMESTAMP WITH TIME ZONE;
  daysinmonth INT;
  i INT;
BEGIN

  month_start := date_trunc( 'month', base_date ) + (base_date::time)::interval;
  daysinmonth := date_part( 'days', (month_start + interval '1 month') - interval '1 day' );

  FOR i IN 1..31 LOOP
    EXIT WHEN bymonthday[i] IS NULL;

    CONTINUE WHEN bymonthday[i] > daysinmonth;
    CONTINUE WHEN bymonthday[i] < (-1 * daysinmonth);

    IF bymonthday[i] > 0 THEN
      RETURN NEXT month_start + ((bymonthday[i] - 1)::text || 'days')::interval;
    ELSIF bymonthday[i] < 0 THEN
      RETURN NEXT month_start + ((daysinmonth + bymonthday[i])::text || 'days')::interval;
    ELSE
      RAISE NOTICE 'Ignored invalid BYMONTHDAY part "%".', bymonthday[i];
    END IF;
  END LOOP;

  RETURN;

END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


-- Return a SETOF dates within the week of a particular date which match a single BYDAY rule specification
CREATE or REPLACE FUNCTION rrule_week_byday_set( TIMESTAMP WITH TIME ZONE, TEXT[] ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  in_time ALIAS FOR $1;
  byday ALIAS FOR $2;
  dayrule TEXT;
  dow INT;
  our_day TIMESTAMP WITH TIME ZONE;
  i INT;
BEGIN

  IF byday IS NULL THEN
    -- We still return the single date as a SET
    RETURN NEXT in_time;
    RETURN;
  END IF;

  our_day := date_trunc( 'week', in_time ) + (in_time::time)::interval;

  i := 1;
  dayrule := byday[i];
  WHILE dayrule IS NOT NULL LOOP
    dow := position(dayrule in 'SUMOTUWETHFRSA') / 2;
    RETURN NEXT our_day + ((dow - 1)::text || 'days')::interval;
    i := i + 1;
    dayrule := byday[i];
  END LOOP;

  RETURN;

END;
$$ LANGUAGE 'plpgsql' IMMUTABLE;


CREATE or REPLACE FUNCTION event_has_exceptions( TEXT ) RETURNS BOOLEAN AS $$
  SELECT $1 ~ E'\nRECURRENCE-ID(;TZID=[^:]+)?:[[:space:]]*[[:digit:]]{8}(T[[:digit:]]{6})?'
$$ LANGUAGE 'sql' IMMUTABLE STRICT;


------------------------------------------------------------------------------------------------------
-- Test the weekday of this date against the array of weekdays from the byday rule
------------------------------------------------------------------------------------------------------
CREATE or REPLACE FUNCTION test_byday_rule( TIMESTAMP WITH TIME ZONE, TEXT[] ) RETURNS BOOLEAN AS $$
DECLARE
  testme ALIAS FOR $1;
  byday ALIAS FOR $2;
  i INT;
  dow TEXT;
BEGIN
  IF byday IS NOT NULL THEN
    dow := substring( to_char( testme, 'DY') for 2 from 1);
    FOR i IN 1..7 LOOP
      IF byday[i] IS NULL THEN
        RETURN FALSE;
      END IF;
      EXIT WHEN dow = byday[i];
    END LOOP;
  END IF;
  RETURN TRUE;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE;


------------------------------------------------------------------------------------------------------
-- Test the month of this date against the array of months from the rule
------------------------------------------------------------------------------------------------------
CREATE or REPLACE FUNCTION test_bymonth_rule( TIMESTAMP WITH TIME ZONE, INT[] ) RETURNS BOOLEAN AS $$
DECLARE
  testme ALIAS FOR $1;
  bymonth ALIAS FOR $2;
  i INT;
  month INT;
BEGIN
  IF bymonth IS NOT NULL THEN
    month := date_part( 'month', testme );
    FOR i IN 1..12 LOOP
      IF bymonth[i] IS NULL THEN
        RETURN FALSE;
      END IF;
      EXIT WHEN month = bymonth[i];
    END LOOP;
  END IF;
  RETURN TRUE;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE;


------------------------------------------------------------------------------------------------------
-- Test the month of this date against the array of months from the rule
------------------------------------------------------------------------------------------------------
CREATE or REPLACE FUNCTION test_bymonthday_rule( TIMESTAMP WITH TIME ZONE, INT[] ) RETURNS BOOLEAN AS $$
DECLARE
  testme ALIAS FOR $1;
  bymonthday ALIAS FOR $2;
  i INT;
  dom INT;
BEGIN
  IF bymonthday IS NOT NULL THEN
    dom := date_part( 'day', testme);
    FOR i IN 1..31 LOOP
      IF bymonthday[i] IS NULL THEN
        RETURN FALSE;
      END IF;
      EXIT WHEN dom = bymonthday[i];
    END LOOP;
  END IF;
  RETURN TRUE;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE;


------------------------------------------------------------------------------------------------------
-- Return another day's worth of events
------------------------------------------------------------------------------------------------------
CREATE or REPLACE FUNCTION daily_set( TIMESTAMP WITH TIME ZONE, rrule_parts ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  after ALIAS FOR $1;
  rrule ALIAS FOR $2;
BEGIN
  -- Since we don't do BYHOUR, BYMINUTE or BYSECOND yet this becomes trivial
  RETURN NEXT after;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


------------------------------------------------------------------------------------------------------
-- Return another week's worth of events
------------------------------------------------------------------------------------------------------
CREATE or REPLACE FUNCTION weekly_set( TIMESTAMP WITH TIME ZONE, rrule_parts ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  after ALIAS FOR $1;
  rrule ALIAS FOR $2;
  valid_date TIMESTAMP WITH TIME ZONE;
  curse REFCURSOR;
  setpos INT;
  i INT;
BEGIN

  OPEN curse SCROLL FOR SELECT r FROM rrule_week_byday_set(after, rrule.byday ) r;

  IF rrule.bysetpos IS NULL THEN
    LOOP
      FETCH curse INTO valid_date;
      EXIT WHEN NOT FOUND;
      RETURN NEXT valid_date;
    END LOOP;
  ELSE
    i := 1;
    setpos := rrule.bysetpos[i];
    WHILE setpos IS NOT NULL LOOP
      IF setpos > 0 THEN
        FETCH ABSOLUTE setpos FROM curse INTO valid_date;
      ELSE
        setpos := setpos + 1;
        MOVE LAST IN curse;
        FETCH RELATIVE setpos FROM curse INTO valid_date;
      END IF;
      IF next_base IS NOT NULL THEN
        RETURN NEXT valid_date;
      END IF;
      i := i + 1;
      setpos := rrule.bysetpos[i];
    END LOOP;
  END IF;

END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


------------------------------------------------------------------------------------------------------
-- Return another month's worth of events
------------------------------------------------------------------------------------------------------
CREATE or REPLACE FUNCTION monthly_set( TIMESTAMP WITH TIME ZONE, rrule_parts ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  after ALIAS FOR $1;
  rrule ALIAS FOR $2;
  valid_date TIMESTAMP WITH TIME ZONE;
  curse REFCURSOR;
  setpos INT;
  i INT;
BEGIN

  -- Need to investigate whether it is legal to set both of these, and whether
  -- we are correct to UNION the results, or whether we should INTERSECT them.
  OPEN curse SCROLL FOR SELECT r FROM rrule_month_byday_set(after, rrule.byday ) r
                  UNION SELECT r FROM rrule_month_bymonthday_set(after, rrule.bymonthday ) r
                  ORDER BY 1;

  IF rrule.bysetpos IS NULL THEN
    LOOP
      FETCH curse INTO valid_date;
      EXIT WHEN NOT FOUND;
      RETURN NEXT valid_date;
    END LOOP;
  ELSE
    i := 1;
    setpos := rrule.bysetpos[i];
    WHILE setpos IS NOT NULL LOOP
      IF setpos > 0 THEN
        FETCH ABSOLUTE setpos FROM curse INTO valid_date;
      ELSE
        setpos := setpos + 1;
        MOVE LAST IN curse;
        FETCH RELATIVE setpos FROM curse INTO valid_date;
      END IF;
      IF valid_date IS NOT NULL THEN
        RETURN NEXT valid_date;
      END IF;
      i := i + 1;
      setpos := rrule.bysetpos[i];
    END LOOP;
  END IF;

END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;



CREATE or REPLACE FUNCTION yearly_set( TIMESTAMP WITH TIME ZONE, rrule_parts ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  after ALIAS FOR $1;
  rrule ALIAS FOR $2;
  current_base TIMESTAMP WITH TIME ZONE;
  rr rrule_parts;
  i INT;
BEGIN

  IF rrule.bymonth IS NOT NULL THEN
    -- As far as I can see there is extremely little difference between YEARLY;BYMONTH and MONTHLY;BYMONTH except the effect of BYSETPOS
    rr := rrule;
    rr.bysetpos := NULL;
    FOR i IN 1..12 LOOP
      EXIT WHEN rr.bymonth[i] IS NULL;
      current_base := date_trunc( 'year', after ) + ((rr.bymonth[i] - 1)::text || ' months')::interval + (after::time)::interval;
      RETURN QUERY SELECT r FROM monthly_set(current_base,rr) r;
    END LOOP;
  ELSE
    -- We don't yet implement byweekno, byblah
    RETURN NEXT after;
  END IF;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


CREATE or REPLACE FUNCTION event_instances( TIMESTAMP WITH TIME ZONE, TEXT )
                                         RETURNS SETOF TIMESTAMP WITH TIME ZONE AS $$
DECLARE
  basedate ALIAS FOR $1;
  repeatrule ALIAS FOR $2;
  loopcount INT;
  loopmax INT;
  base_day TIMESTAMP WITH TIME ZONE;
  current_base TIMESTAMP WITH TIME ZONE;
  current TIMESTAMP WITH TIME ZONE;
  rrule rrule_parts%ROWTYPE;
BEGIN
  loopcount := 0;
  loopmax := 500;

  SELECT * INTO rrule FROM parse_rrule_parts( basedate, repeatrule );
  IF rrule.count IS NOT NULL THEN
    loopmax := rrule.count;
  END IF;

  current_base := basedate;
  base_day := date_trunc('day',basedate);
  WHILE loopcount < loopmax LOOP
    IF rrule.freq = 'DAILY' THEN
      FOR current IN SELECT d FROM daily_set(current_base,rrule) d WHERE d >= base_day LOOP
        IF test_byday_rule(current,rrule.byday) AND test_bymonthday_rule(current,rrule.bymonthday) AND test_bymonth_rule(current,rrule.bymonth) THEN
          EXIT WHEN rrule.until IS NOT NULL AND current > rrule.until;
          RETURN NEXT current;
          loopcount := loopcount + 1;
          EXIT WHEN loopcount >= loopmax;
        END IF;
      END LOOP;
      current_base := current_base + (rrule.interval::text || ' days')::interval;
    ELSIF rrule.freq = 'WEEKLY' THEN
      FOR current IN SELECT w FROM weekly_set(current_base,rrule) w WHERE w >= base_day LOOP
        IF test_bymonthday_rule(current,rrule.bymonthday) AND test_bymonth_rule(current,rrule.bymonth) THEN
          EXIT WHEN rrule.until IS NOT NULL AND current > rrule.until;
          RETURN NEXT current;
          loopcount := loopcount + 1;
          EXIT WHEN loopcount >= loopmax;
        END IF;
      END LOOP;
      current_base := current_base + (rrule.interval::text || ' weeks')::interval;
    ELSIF rrule.freq = 'MONTHLY' THEN
      FOR current IN SELECT m FROM monthly_set(current_base,rrule) m WHERE m >= base_day LOOP
        IF test_bymonth_rule(current,rrule.bymonth) THEN
          EXIT WHEN rrule.until IS NOT NULL AND current > rrule.until;
          RETURN NEXT current;
          loopcount := loopcount + 1;
          EXIT WHEN loopcount >= loopmax;
        END IF;
      END LOOP;
      current_base := current_base + (rrule.interval::text || ' months')::interval;
    ELSIF rrule.freq = 'YEARLY' THEN
      FOR current IN SELECT y FROM yearly_set(current_base,rrule) y WHERE y >= base_day LOOP
        EXIT WHEN rrule.until IS NOT NULL AND current > rrule.until;
        RETURN NEXT current;
        loopcount := loopcount + 1;
        EXIT WHEN loopcount >= loopmax;
      END LOOP;
      current_base := current_base + (rrule.interval::text || ' years')::interval;
    ELSE
      RAISE NOTICE 'A frequency of "%" is not handled', rrule.freq;
      RETURN;
    END IF;
    EXIT WHEN rrule.until IS NOT NULL AND current > rrule.until;
  END LOOP;
  -- RETURN QUERY;
END;
$$ LANGUAGE 'plpgsql' IMMUTABLE STRICT;


