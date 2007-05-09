@echo off
rem Build the RSCDS database

setlocal

if db%1 EQU db (
    echo Usage: create-database dbnameprefix [adminpassword [pguser]]
    exit /B 1
)
set DBNAME=%1-rscds
set ADMINPW=%2

set DBADIR=%CD%

set PGDIR=%PGLOCALEDIR%\..\..\bin
IF usr%3 NEQ usr ( set USERNAME=%3 )

rem FIXME: Need to check that the database was actually created.
%PGDIR%\createdb -E UTF8 -T template0 -U %USERNAME% %DBNAME% 
if %ERRORLEVEL% NEQ 0 ( 
    echo Unable to create database
    exit /B 2
)

rem This will fail if the language already exists, but it should not
rem because we created from template0.
%PGDIR%\createlang -U %USERNAME% plpgsql %DBNAME%

rem Test if egrep is available
rem You can download egrep.exe for Windows e.g. from UnxUtils: http://unxutils.sourceforge.net/):
egrep 2>NULL
if ERRORLEVEL 3 (
    rem No egrep
    %PGDIR%\psql -q -f %DBADIR%/rscds.sql %DBNAME% 2>&1 -U %USERNAME%
) ELSE (
    rem egrep is available
    %PGDIR%\psql -q -f %DBADIR%/rscds.sql %DBNAME% 2>&1 -U %USERNAME% | egrep -v "(^CREATE |^GRANT|^BEGIN|^COMMIT| NOTICE: )"
)
del NULL
%PGDIR%\psql -q -f %DBADIR%/grants.sql %DBNAME% 2>&1 -U %USERNAME% | egrep -v "(^GRANT)"

%PGDIR%\psql -q -f %DBADIR%/caldav_functions.sql %DBNAME% -U %USERNAME%

%PGDIR%\psql -q -f %DBADIR%/base-data.sql %DBNAME% -U %USERNAME%

rem We can override the admin password generation for regression testing predictability
rem if [ %ADMINPW}" = "" ] ; then
rem   #
rem   # Generate a random administrative password.  If pwgen is available we'll use that,
rem   # otherwise try and hack something up using a few standard utilities
rem   ADMINPW="`pwgen -Bcny 2>/dev/null | tr \"\\\'\" '^='`"
rem fi
rem 
rem if [ "$ADMINPW" = "" ] ; then
rem   # OK.  They didn't supply one, and pwgen didn't work, so we hack something
rem   # together from /dev/random ...
rem   ADMINPW="`dd if=/dev/urandom bs=512 count=1 2>/dev/null | tr -c -d "[:alnum:]" | cut -c2-9`"
rem fi
rem 
rem   # Right.  We're getting desperate now.  We'll have to use a default password
rem   # and hope that they change it to something more sensible.
IF pw%ADMINPW% EQU pw ( set ADMINPW=please change this password )
rem fi

%PGDIR%\psql -q -c "UPDATE usr SET password = '**%ADMINPW%' WHERE user_no = 1;" %DBNAME% -U %USERNAME%

echo The password for the 'admin' user has been set to "%ADMINPW%"

rem The supported locales are in a separate file to make them easier to upgrade
%PGDIR%\psql -q -f %DBADIR%/supported_locales.sql %DBNAME% -U %USERNAME%

echo DONE

:END

endlocal

