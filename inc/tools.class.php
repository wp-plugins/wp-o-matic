<?php

class WPOTools {   
  
  function getOptions($args)
  {
    if (is_array($args))
  	  $r = &$args;
  	else
  		parse_str($args, $r);
  		
  	return $r;
  }
  
  function isAjax() { 
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH']  == 'XMLHttpRequest'); 
  }
    
  function isUnix()
  {
		return in_array(php_uname('s'), array('Linux', 'FreeBSD', 'OpenBSD', 'Darwin', 'SunOS', 'AIX'));
  }        
  
  function getQueryArgs($args, $defaults = array())
  {                                   
    $r = WPOTools::getOptions($args);  		
    $ret = array_merge($defaults, $r);
       
    if(!is_null($ret['page']) && !is_null($ret['perpage']))
    {
      $perpage = $ret['perpage'];
      $page = ($ret['page'] == 0) ? 1 : $ret['page'];
      $page--;

      $start = $page * $perpage;
      $end = $start + $perpage;
      $ret['limit'] = "{$start}, {$end}";
    }
   
    if(!is_null($ret['limit']))
      $ret['limit'] = 'LIMIT ' . $ret['limit'];                               
      
    return $ret;
  }
  
  function insertQuery($table, $params)
  {
    $fields = array_keys($params);
    return "INSERT INTO $table (`".implode('`,`',$fields)."`) VALUES ('".implode("','",$params)."')"  ;
  }
  
  function updateQuery($table, $params, $where) 
  {
    $bits = array();
    foreach(array_keys($params) as $k )
      $bits[] = "`$k`='$params[$k]'";
    return "UPDATE $table SET ".implode(', ',$bits)." WHERE $where";
  }
           
  function addOptions($options)
  {
    foreach($options as $option => $vars)
      add_option($option, $vars[0], $vars[1], (isset($vars[2])) ? $vars[2] : null); 
  } 
  
  function deleteOptions($options)
  {
    foreach($options as $option)
      delete_option($option);
  }
  
  function getInputTextValue($key, $default)
  {
    
  }
  
  function parseImages($text)
  {    
    preg_match_all('/<img(.+?)src=\"(.+?)\"(.*?)>/', $text, $out);
    return $out;
  }
  
  function stripText($text)
  {
    $text = strtolower($text);
 
    // strip all non word chars
    $text = preg_replace('/\W/', ' ', $text);
 
    // replace all white space sections with a dash
    $text = preg_replace('/\ +/', '-', $text);
 
    // trim dashes
    $text = preg_replace('/\-$/', '', $text);
    $text = preg_replace('/^\-/', '', $text);
 
    return $text;
  }
  
  // from somewhere in the internet.. too lazy to do it myself
  // @todo add right copyright
  function calcTime($t, $sT = 0, $sel = 'Y', $includenull = true) {

      $sY = 31536000;
      $sW = 604800;
      $sD = 86400;
      $sH = 3600;
      $sM = 60;

      if($sT) {
          $t = ($sT - $t);
      }

      if($t <= 0) {
          $t = 0;
      }

      $bs[1] = ('1'^'9'); /* Backspace */

      $r = array('string' => '');

      switch(strtolower($sel)) {

          case 'y':
              $y = ((int)($t / $sY));
              $t = ($t - ($y * $sY));
              if($y != 0 || ($y == 0 && $includenull)) $r['string'] .= "{$y} years{$bs[$y]} ";
              $r['years'] = $y;
          case 'w':
              $w = ((int)($t / $sW));
              $t = ($t - ($w * $sW));
              if($w != 0 || ($w == 0 && $includenull)) $r['string'] .= "{$w} weeks{$bs[$w]} ";
              $r['weeks'] = $w;
          case 'd':
              $d = ((int)($t / $sD));
              $t = ($t - ($d * $sD));
              if($d != 0 || ($d == 0 && $includenull)) $r['string'] .= "{$d} days{$bs[$d]} ";
              $r['days'] = $d;
          case 'h':
              $h = ((int)($t / $sH));
              $t = ($t - ($h * $sH));
              if($h != 0 || ($h == 0 && $includenull)) $r['string'] .= "{$h} hours{$bs[$h]} ";
              $r['hours'] = $h;
          case 'm':
              $m = ((int)($t / $sM));
              $t = ($t - ($m * $sM));
              if($m != 0 || ($m == 0 && $includenull)) $r['string'] .= "{$m} minutes{$bs[$m]} ";
              $r['minutes'] = $m;
          case 's':
              $s = $t;
              if($s != 0 || ($s == 0 && $includenull)) $r['string'] .= "{$s} seconds{$bs[$s]} ";
              $r['seconds'] = $s;
          break;
          default:
              return calc_tl($t);
          break;
      }

      return $r;
  }
  
  function stringToArray($string)
  {
    preg_match_all('/
      \s*(\w+)              # key                               \\1
      \s*=\s*               # =
      (\'|")?               # values may be included in \' or " \\2
      (.*?)                 # value                             \\3
      (?(2) \\2)            # matching \' or " if needed        \\4
      \s*(?:
        (?=\w+\s*=) | \s*$  # followed by another key= or the end of the string
      )
    /x', $string, $matches, PREG_SET_ORDER);

    $attributes = array();
    foreach ($matches as $val)
    {
      $attributes[$val[1]] = WPOTools::literalize($val[3]);
    }

    return $attributes;
  }
  
  /**
   * Finds the type of the passed value, returns the value as the new type.
   *
   * @param  string
   * @return mixed
   */
  function literalize($value, $quoted = false)
  {
    // lowercase our value for comparison
    $value  = trim($value);
    $lvalue = strtolower($value);

    if (in_array($lvalue, array('null', '~', '')))
    {
      $value = null;
    }
    else if (in_array($lvalue, array('true', 'on', '+', 'yes')))
    {
      $value = true;
    }
    else if (in_array($lvalue, array('false', 'off', '-', 'no')))
    {
      $value = false;
    }
    else if (ctype_digit($value))
    {
      $value = (int) $value;
    }
    else if (is_numeric($value))
    {
      $value = (float) $value;
    }
    else
    {
      if ($quoted)
      {
        $value = '\''.str_replace('\'', '\\\'', $value).'\'';
      }
    }

    return $value;
  }
}