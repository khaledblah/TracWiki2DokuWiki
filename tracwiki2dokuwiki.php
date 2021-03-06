<?php 
/**
 * TracWiki2DokuWiki
 * (c) Alex N J, 2009. Free to use, but use at your own risk.
 * modified by Oliver Kopp, 2012
 *
 * Originally from http://blog.alexnj.com/php/a-php-class-to-convert-trac-wiki-to-dokuwiki-format
 */
class TracWiki2DokuWiki {

  private $mvCommands;
  
  /**
   * takes $markup and returns an array with 
   *   * the conversion result and 
   *   * necessary mv commands for attachments
   */
  public function convert( $markup ) {
    $class = new ReflectionClass( 'TracWiki2DokuWiki' );
                
    $callableMethods = array();
    foreach( $class->getMethods() as $method ) {
      $callableMethods[] = $method->getName();
    }

    $page = "";
    $this->mvCommands = "";

    foreach(preg_split("/(\r?\n)/", $markup) as $line) {
      foreach( $callableMethods as $methodName ) {
        if( preg_match( '/^replace/', $methodName ) ) {
          $line = call_user_func_array(array( $this, $methodName ), array( $line ));
        }
      }
      $page = $page . "$line\n";
    }

    return array("page" => $page, "mvCommands" => $this->mvCommands);
  }

  /** functions starting with replace_ called in their order of appearance **/

  private function replace_headings( $line ) {
    $line = preg_replace( "/^= ([^=]+)(=)?/mu", "====== $1======", $line, 1, $count );
    if ($count>0) return $line;
    $line = preg_replace( "/^== ([^=]+)(==)?/mu", "===== $1=====", $line, 1, $count );
    if ($count>0) return $line;
    $line = preg_replace( "/^=== ([^\=]+)(===)?/mu", "==== $1====", $line, 1, $count );
    if ($count>0) return $line;
    $line = preg_replace( "/^==== ([^=]+)(====)?/mu", "== $1==", $line, 1, $count );
    if ($count>0) return $line;
    $line = preg_replace( "/^===== ([^=]+)(=====)?/mu", "= $1=", $line, 1, $count );
    return $line;
  }

  private function replace_inline_code( $line ) {
    return( preg_replace( "/{{{([^}]+)}}}/u", "''$1''", $line ) );
  }

  private function replace_code_block( $line ) {
    $line =  preg_replace( "/{{{\s*/u", "<code>", $line );
    return( preg_replace( "/}}}\s*/u", "</code>\n", $line ) );
  }

  private function replace_bold_italic( $line ) {
    return( preg_replace( "/'''''([^']+)'''''/u", "**//$1//**", $line ) );
  }

  private function replace_bold( $line ) {
    return( preg_replace( "/'''([^']+)'''/u", "**$1**", $line ) );
  }

  private function replace_italic( $line ) {
    return( preg_replace( "/''([^']+)''/u", "//$1//", $line ) );
  }

  private function replace_strikethrough($line) {
    return(preg_replace( "/~~([^~]+)~~/u", "<del>$1</del>", $line));
  }

  private function replace_superscript($line) {
    return(preg_replace( "/\^([^\^]+)\^/u", "<sup>$1</sup>", $line));
  }

  private function replace_subscript($line) {
    return(preg_replace( "/,,([^,]+),,/u", "<sub>$1</sub>", $line));
  }

  private function replace_br( $line ) {
    $matches = array();
    if ($count = preg_match_all('/\[\[BR\]\]([^\n]+)/iU', $line, $matches)) {
      // search for occourrences of [[BR]] where there is no newline after, a
      // newline character will be added. This works for multiple occourences
      // of [[BR]] in one line, too.
      for($i = 0; $i < $count; $i++) {
        $search = preg_quote($matches[0][$i]);
        $replace = preg_quote("\\\\\\\n") . preg_quote($matches[1][$i]);
        $line = preg_replace('/' . $search . '/', $replace, $line);
      }
    }
    return( preg_replace("/\[\[BR\]\]/i", "\\\\\\", $line) );
  }

  private function replace_toc( $line ) {
    return(preg_replace("/\[\[TOC\]\]/i", "", $line));
  }

  private function replace_page_outline( $line ) {
    return(preg_replace("/\[\[PageOutline\(.+\)\]\]/i", "", $line));
  }

  private function replace_ignore_CamelCase( $line ) {
    $line = preg_replace("/^!/", "", $line);
    $line = preg_replace("/ !([A-Z])/", " $1", $line);
    return $line;
  }
  
  private function replace_table_heading( $line ) {
    // match manually bolded headings
    $line = preg_replace( "/\|\|[ ]*'''([^']*)'''/", "^ $1", $line, -1, $count);
    if ($count > 0) {
      $line = trim($line);
      if (strpos($line,"||")==strlen($line)-2)
        // if first occurence of "||" are the last two characters, they have to be replaced by "^"
        $line = substr($line, 0, -2) . "^";
    }

    // real trac headings: format: ||= Table Header =||=Header =||
    $matches = array();
    if ($count = preg_match_all("/\|\|=([^=]+)=/", $line, $matches)) {
      for($i = 0; $i < $count; $i++) {
        $search = preg_quote($matches[0][$i], '/');
        $replace = '^ ' . preg_quote($matches[1][$i]) . ' ';
        $line = preg_replace('/' . $search . '/', $replace, $line);
      }
    }

    # $line = preg_replace("/\|\|=([^=]+)=(.*)/", "^ $1 $2", $line, -1, $count);
    if ($count > 0) {
      $line = trim($line);
      if (strpos($line,"||")==strlen($line)-2)
        // if first occurence of "||" are the last two characters, they can be removed
        // $line = substr($line, 0, -2);
        $line = str_replace('||', '^', $line);
    }
    return $line;
    //$line =  preg_replace( "/\|\*\*/", "^", $line );
    //$line =  preg_replace( "/\*\*\|/", "^", $line );
    //return( preg_replace( "/[\*]*\^[\*]*/", "^", $line ) );
  }

  private function replace_table( $line ) {
    // insert spaces for empty cells
    $line = preg_replace("/\|\|\|\|/", "|| ||", $line);
    
    // real conversion
    return( preg_replace( "/\|\|/u", "|", $line ) );
  }

  private static function tracFileName($fn) {
    $fn = rawurlencode($fn);
    return $fn;
  }
  
  public static function tracFileName2DokuWikiFileName($fn) {
    $fn = strtolower($fn);
    $fn = str_replace("%20", "_", $fn);
    $fn = rawurldecode($fn);
    $fn = iconv("UTF-8","ASCII//TRANSLIT", $fn);
    return $fn;
  }
  
  private function replace_image( $line ) {
    return preg_replace("/\[\[Image\(([^,]+)(,.*)?\)\]\]/", "{{:\$1}}", $line);
  }

  private function replace_anchor( $line ) {
    // this requires the anchor plugin: https://www.dokuwiki.org/plugin:anchor
    return preg_replace("/\[\[?=#(.*)\]?\]/", "{{anchor:\$1}}", $line);
  }

  private function workOnAttachment($pattern, &$line) {
    if ($count = preg_match_all($pattern, $line, $hits)) {
      for ($i=0; $i<$count; $i++) {
        $curHit = $hits[1][$i];
        $curHit = TracWiki2DokuWiki::tracFileName($curHit);
        $fileName = TracWiki2DokuWiki::tracFileName2DokuWikiFileName($curHit);
        if ($curHit == $fileName) {
          print("Attachment: $curHit\n");
        } else {
          print("Attachment: $curHit -> $fileName\n");
          $this->mvCommands = $this->mvCommands . "mv \"$curHit\" $fileName\n";
        }
        $line = preg_replace($pattern, "{{:$fileName?linkonly|$2}}", $line, 1);
      }
      return true;
    };
    return false;
  }

  private function replace_link( $line ) {
    if ($this->workOnAttachment("/\[attachment:[\"']([^\"]+)[\"'] *([^\]]*)\]/u", $line)) return $line;
    if ($this->workOnAttachment("/\[attachment:.*:([^ ]+) ([^\]]+)\]/u", $line)) return $line;
    if ($this->workOnAttachment("/\[attachment:([^ ]+) ([^\]]+)\]/u", $line)) return $line;
    if ($this->workOnAttachment("/\[attachment:([^ ]+)\]/u", $line)) return $line;
    // urlencode a "|" character in URIs
    $line = preg_replace("/http(s)?:\/\/(.*)\|/", "http$1://$2" . urlencode("|"), $line);
    // remove [wiki: prefix
    $line = preg_replace("/\[wiki:/", "[", $line);
    // links without description
    $prefix = WIKI_PREFIX . ':';
    if (preg_match('/\[http(s)?([^ \]]+)\]/u', $line)) {
      $prefix = '';
    }
    $line = preg_replace("/\[([^ \]]+)\]/u", "[[" . $prefix . "$1]]", $line );

    // links with description
    /*
    $matches = array();
    if ($count = preg_match_all("/\[?\[([^ \]]+) ([^\]]+)\]\]?/uU", $line, $matches)) {
      error_log(__FILE__ . ': ' . __LINE__);
      error_log(print_r($matches, TRUE));
      for($i = 0; $i < $count; $i++) {
        $search = preg_quote($matches[0][$i]);
        $replace = preg_quote($matches[1][$i]);
        // $line = preg_replace('/' . $search . '/', $replace, $line);
      }
    }
    */
    if (preg_match('/\[?\[http(s)?([^ \]]+) ([^\]]+)\]\]?/u', $line)) {
      $prefix = '';
    }
    $line = preg_replace("/\[?\[([^ \]]+) ([^\]]+)\]\]?/u", "[[" . $prefix . "$1|$2]]", $line);
    

    if (strpos($line, "http") === FALSE) {
      // replace the forward slash "/" with ":" in links
      $line = preg_replace_callback("/\[\[(.*)\]\]/u", "replace_namespace", $line );
    }
    return $line;
  }

  private function replace_itemlists($line) {
    $line = preg_replace("/^( +)\* /", "$1 * ", $line);
    $line = preg_replace("/^\* /", "  * ", $line);
    return $line;
  }

  private function replace_numberedlists($line) {
    return( preg_replace("/^( +)1\. /", "$1 - ", $line) );
  }
}

function replace_namespace($match) {
  return preg_replace('/([^\/])\/{1}([^\/])/', '$1:$2', $match[0]);
}

?>

