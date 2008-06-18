/**
* PostgreSQL Functions for RRULE handling
*
* @package rscds
* @subpackage database
* @author Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst IT Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/

-- How many days are there in a particular month?
CREATE or REPLACE FUNCTION rrule_days_in_month( TIMESTAMP WITH TIME ZONE ) RETURNS INT AS '
DECLARE
  in_time ALIAS FOR $1;
  days INT;
BEGIN
  RETURN date_part( ''days'', date_trunc( ''month'', in_time + interval ''1 month'') - interval ''1 day'' );
END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;


-- Return a SETOF text strings, split on the commas in the original one
CREATE or REPLACE FUNCTION rrule_split_on_commas( TEXT ) RETURNS SETOF TEXT AS '
DECLARE
  in_text ALIAS FOR $1;
  part TEXT;
  cpos INT;
  remainder TEXT;
BEGIN
  remainder := in_text;
  LOOP
    cpos := position( '','' in remainder );
    IF cpos = 0 THEN
      part := remainder;
      EXIT;
    ELSE
      part := substring( remainder for cpos - 1 );
      remainder := substring( remainder from cpos + 1);
      RETURN NEXT part;
    END IF;
  END LOOP;
  RETURN NEXT part;
  RETURN;
END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;

-- Return a SETOF dates within the month of a particular date which match a single BYDAY rule specification
CREATE or REPLACE FUNCTION rrule_month_bydayrule_set( TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS '
DECLARE
  in_time ALIAS FOR $1;
  bydayrule ALIAS FOR $2;
  dow INT;
  index INT;
  first_dow INT;
  each_day TIMESTAMP WITH TIME ZONE;
  this_month INT;
BEGIN
  dow := position(substring( bydayrule from ''..$'') in ''SUMOTUWETHFRSA'') / 2;
  each_day := date_trunc( ''month'', in_time ) + (in_time::time)::interval;
  this_month := date_part( ''month'', in_time );
  first_dow := date_part( ''dow'', each_day );
  each_day := each_day - ( first_dow::text || ''days'')::interval
                       + ( dow::text || ''days'')::interval
                       + CASE WHEN dow > first_dow THEN ''1 week''::interval ELSE ''0s''::interval END;

  IF length(bydayrule) > 2 THEN
    index := (substring(bydayrule from ''^[0-9-]+''))::int;

    -- Possibly we should check that (index != 0) here, which is an error

    IF index = 0 THEN
      RAISE NOTICE ''Ignored invalid BYDAY rule part "%".'', bydayrule;
    ELSIF index > 0 THEN
      -- The simplest case, such as 2MO for the second monday
      each_day := each_day + ((index - 1)::text || '' weeks'')::interval;
    ELSE
      each_day := each_day + ''5 weeks''::interval;
      WHILE date_part(''month'', each_day) != this_month LOOP
        each_day := each_day - ''1 week''::interval;
      END LOOP;
      -- Note that since index is negative, (-2 + 1) == -1, for example
      each_day := each_day + ( (index + 1)::text || '' weeks'')::interval ;
    END IF;

    -- Sometimes (e.g. 5TU or -5WE) there might be no such date in some months
    IF date_part(''month'', each_day) = this_month THEN
      RETURN NEXT each_day;
    END IF;

  ELSE
    -- Return all such days that are within the given month
    WHILE date_part(''month'', each_day) = this_month LOOP
      RETURN NEXT each_day;
      each_day := each_day + ''1 week''::interval;
    END LOOP;
  END IF;

  RETURN;

END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;


-- Return a SETOF dates within the month of a particular date which match a string of BYDAY rule specifications
CREATE or REPLACE FUNCTION rrule_month_byday_set( TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS '
DECLARE
  in_time ALIAS FOR $1;
  byday ALIAS FOR $2;
  dayrule RECORD;
  day RECORD;
BEGIN

  FOR dayrule IN SELECT * FROM rrule_split_on_commas( byday ) LOOP
    FOR day IN SELECT * FROM rrule_month_bydayrule_set( in_time, dayrule.rrule_split_on_commas ) LOOP
      RETURN NEXT day.rrule_month_bydayrule_set;
    END LOOP;
  END LOOP;

  RETURN;

END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;




-- Return a SETOF dates within the month of a particular date which match a string of BYDAY rule specifications
CREATE or REPLACE FUNCTION rrule_month_bymonthday_set( TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS '
DECLARE
  in_time ALIAS FOR $1;
  bymonthday ALIAS FOR $2;
  dayrule RECORD;
  daysinmonth INT;
  month_start TIMESTAMP WITH TIME ZONE;
  dayoffset INT;
BEGIN

  daysinmonth := rrule_days_in_month(in_time);
  month_start := date_trunc( ''month'', in_time ) + (in_time::time)::interval;

  FOR dayrule IN SELECT * FROM rrule_split_on_commas( bymonthday ) LOOP
    dayoffset := dayrule.rrule_split_on_commas::int;
    IF dayoffset = 0 THEN
      RAISE NOTICE ''Ignored invalid BYMONTHDAY part "%".'', dayrule.rrule_split_on_commas;
      dayoffset := 0;
    ELSIF dayoffset > daysinmonth THEN
      dayoffset := 0;
    ELSIF dayoffset < (-1 * daysinmonth) THEN
      dayoffset := 0;
    ELSIF dayoffset > 0 THEN
      RETURN NEXT month_start + ((dayoffset - 1)::text || ''days'')::interval;
    ELSE
      RETURN NEXT month_start + ((daysinmonth + dayoffset)::text || ''days'')::interval;
    END IF;

  END LOOP;

  RETURN;

END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;



-- Return a SETOF dates within the week of a particular date which match a single BYDAY rule specification
CREATE or REPLACE FUNCTION rrule_week_byday_set( TIMESTAMP WITH TIME ZONE, TEXT ) RETURNS SETOF TIMESTAMP WITH TIME ZONE AS '
DECLARE
  in_time ALIAS FOR $1;
  byweekday ALIAS FOR $2;
  dayrule RECORD;
  dow INT;
  our_day TIMESTAMP WITH TIME ZONE;
BEGIN
  our_day := date_trunc( ''week'', in_time ) + (in_time::time)::interval;

  FOR dayrule IN SELECT * FROM rrule_split_on_commas( byweekday ) LOOP
    dow := position(substring( dayrule.rrule_split_on_commas from ''..$'') in ''SUMOTUWETHFRSA'') / 2;
    RETURN NEXT our_day + ((dow - 1)::text || ''days'')::interval;
  END LOOP;

  RETURN;

END;
' LANGUAGE 'plpgsql' IMMUTABLE STRICT;


CREATE or REPLACE FUNCTION event_has_exceptions( TEXT ) RETURNS BOOLEAN AS '
  SELECT $1 ~ ''\nRECURRENCE-ID(;TZID=[^:]+)?:[[:space:]]*[[:digit:]]{8}(T[[:digit:]]{6})?''
' LANGUAGE 'sql' IMMUTABLE STRICT;


