<?php
/**
* DAViCal Timezone Service handler - capabilitis
*
* @package   davical
* @subpackage   tzservice
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

header('Content-Type: application/xml; charset="utf-8"');
?>
<?xml version="1.0" encoding="utf-8" ?>
<capabilities xmlns="urn:ietf:params:xml:ns:timezone-service">
  <info>
    <primary-source>Olson:2011m</primary-source>
    <contact>mailto:tzs@example.org</contact>
  </info>

  <operation>
    <action>list</action>
    <description>List timezone identifiers and localized forms
    </description>
    
    <accept-parameter>
      <name>lang</name>
      <required>false</required>
      <multi>true</multi>
      <description>Specify desired localized form(s)</description>
    </accept-parameter>
    
    <accept-parameter>
      <name>changedsince</name>
      <required>false</required>
      <multi>false</multi>
      <description>Limit result to timezones changed since the
       given date
      </description>
    </accept-parameter>
    
    <accept-parameter>
      <name>returnall</name>
      <required>false</required>
      <multi>false</multi>
      <description>If present inactive timezones will be returned.
      </description>
    </accept-parameter>
  </operation>
  
  <operation>
    <action>get</action>
    <description>
     Returns one or more timezones as specified by the
     tzid parameter.
    </description>
    
    <accept-parameter>
      <name>format</name>
      <required>false</required>
      <multi>false</multi>
      <value>text/calendar</value>
      <value>application/calendar+xml</value>
      <description>Specify required format for timezone.
      </description>
    </accept-parameter>
    
    <accept-parameter>
      <name>lang</name>
      <required>false</required>
      <multi>true</multi>
      <description>Specify desired localized form(s)</description>
    </accept-parameter>
    
    <accept-parameter>
      <name>tzid</name>
      <required>true</required>
      <multi>true</multi>
      <description>Specify desired timezone identifiers
      </description>
    </accept-parameter>
  </operation>
  
  <operation>
    <action>expand</action>
    <description>
     Expands the specified timezone(s) into local onset and UTC
     offsets
    </description>
    
    <accept-parameter>
      <name>tzid</name>
      <required>true</required>
      <multi>true</multi>
      <description>Specify desired timezone identifiers</description>
    </accept-parameter>
    
    <accept-parameter>
      <name>start</name>
      <required>false</required>
      <multi>false</multi>
      <description>
       Specify start of the period of interest. If omitted the
       current year is assumed.
      </description>
    </accept-parameter>
    
    <accept-parameter>
      <name>end</name>
      <required>false</required>
      <multi>false</multi>
      <description>
       Specify end of the period of interest.
       If omitted the current year + 10 is assumed.
      </description>
    </accept-parameter>
  </operation>
  
  <operation>
    <action>capabilities</action>
    <description>Gets the capabilities of the server</description>
  </operation>
</capabilities>
<?php 
exit(0);