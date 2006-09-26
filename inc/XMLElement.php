<?php
/**
* A class to assist with construction of XML documents
*
* @package   awl
* @subpackage   XMLElement
* @author    Andrew McMillan <andrew@catalyst.net.nz>
* @copyright Catalyst .Net Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*/
require_once("AWLUtilities.php");

/**
* A class for XML elements which may have attributes, or contain
* other XML sub-elements
*
* @package   awl
*/
class XMLElement {
  var $tagname;
  var $attributes;
  var $content;

  /**
  * Constructor - nothing fancy as yet.
  */
  function XMLElement( $tagname, $content=false, $attributes=false ) {
    $this->tagname=$tagname;
    $this->content=$content;
    $this->attributes = $attributes;
  }

  /**
  * Set an element attribute to a value
  *
  * @param string The attribute name
  * @param string The attribute value
  */
  function SetAttribute($k,$v) {
    if ( gettype($this->attributes) != "array" ) $this->attributes = array();
    $this->attributes[$k] = $v;
  }

  /**
  * Set the whole content to a value
  *
  * @param mixed The element content, which may be text, or an array of sub-elements
  */
  function SetContent($v) {
    $this->content = $v;
  }

  /**
  * Add a sub-element
  *
  * @param object An XMLElement to be appended to the array of sub-elements
  */
  function AddSubTag($v) {
    if ( gettype($this->content) != "array" ) $this->content = array();
    $this->content[] = $v;
  }

  /**
  * Render the document tree into (nicely formatted) XML
  *
  * @param int The indenting level for the pretty formatting of the element
  */
  function Render($indent=0) {
    $r = substr("                        ",0,$indent) . '<' . $this->tagname;
    if ( gettype($this->attributes) == "array" ) {
      /**
      * Render the element attribute values
      */
      foreach( $this->attributes AS $k => $v ) {
        $r .= sprintf( ' %s="%s"', $k, $v );
      }
    }
    if ( (is_array($this->content) && count($this->content) > 0) || strlen($this->content) > 0 ) {
      $r .= ">";
      if ( is_array($this->content) ) {
        /**
        * Render the sub-elements with a deeper indent level
        */
        $r .= "\n";
        foreach( $this->content AS $k => $v ) {
          if ( is_object($v) ) {
            $r .= $v->Render($indent+1);
          }
        }
        $r .= substr("                        ",0,$indent);
      }
      else {
        /**
        * Render the content, with special characters escaped
        *
        * FIXME This should switch to CDATA in some situations.
        */
        $r .= htmlspecialchars($this->content);
      }
      $r .= '</' . $this->tagname.">\n";
    }
    else {
      $r .= "/>\n";
    }
    return $r;
  }
}

?>