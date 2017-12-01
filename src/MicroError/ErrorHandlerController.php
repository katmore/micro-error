<?php
namespace MicroError;

class ErrorHandlerController {
   
   /**
    * @return array[]
    */
   public static function enumerate() {
      $errlist = [];
      foreach(self::$_error_list as $err) {
         $errlist[]=$err->get_assoc();
      }
      return $errlist;
   }
   /**
    * @var callable
    */
   private static $_orig_error_handler;
   /**
    * @var \MicroError\SerializedError
    */
   private static $_error_list;
   /**
    * @return array[] Array of any-all encountered errors' data.
    */
   public function get_error_list() {
      return self::$_error_list;
   }
   public static function reset() {
      if (self::$_orig_error_handler) {
         set_error_handler(self::$_orig_error_handler);
      }
      self::$_fatal_handler=null;
   }
   private static $_display_handler;
   /**
    * @param callable $handler callback signature: function(array $errlist,$buffer=null);
    */
   public static function set_display_handler(callable $handler) {
      self::$_display_handler = $handler;
   }
   
   public static function display(array $errlist,$buffer=null) {
      if (!headers_sent()) {
         http_response_code(500);
      }
      if ($handler = self::$_display_handler) return $handler($errlist,$buffer);
      if (!headers_sent()) {
         header('Content-Type: text/html',true);
      }
      echo "<pre><hr><span style='transform: rotate(90deg); display:inline-block;'>:(</span>we are experiencing difficulties<hr></pre>";
      if (!empty(ini_get('display_errors'))) {
         echo static::_serialize_html((object)['error-list'=>$errlist]);
      } else {
         echo "Please contact support or try again.";
      }
      echo "<hr><pre>Generated: ".date("c")."</pre><hr>";
   }
   
   protected static function _filter_exception_trace(array $exceptionTrace) {
      if (!self::show_exception_trace_args) {
         $trace=[];
         foreach($exceptionTrace as $t) {
            $item=[];
            foreach($t as $k=>$v) {
               if ($k!="args") $item[$k]=$v;
            }
            if (!empty($item['class']) && (false !== ( strpos($item['class'], get_called_class()) ))) {
               continue;
            }
            $trace[]=$item;
         }
         return $trace;
      }
      return $exceptionTrace;
   }
   /**
    * @var callable
    */
   private static $_fatal_handler;
   /**
    * @var bool
    */
   private static $_registeredFatalCheck = false;
   
   /**
    * @var bool
    */
   private static $_showArgs = false;
   
   /**
    * Clears all current ob levels, should any exist. If the last error was not a memory error,
    *    all the output buffer values are concatonated.
    *
    * @return string|null Returns concatonated value of all ob levels; or returns <b>(null)</b> if last error was memory error or output buffering did not exist or was all empty.
    */
   public static function ob_get_clean_all() {
      if ($last = error_get_last()) {
         if (!empty($last) && is_array($last) && !empty($last['message'])) {
            if (false!==(strpos(strtolower($last['message']),'memory'))) {
               if ($level = ob_get_level()) for($i=0;$i<$level;$i++) ob_get_clean();
               return null;
            }
         }
      }
      $buff = "";
      if ($level = ob_get_level()) for($i=0;$i<$level;$i++) $buff .= ob_get_clean();
      //ob_end_clean();
      return $buff;
   }
   
   /**
    * Overrides PHPs handling for fatal errors and Exceptions, and optionally non-fatal errors
    *    Upon encountering a fatal error or Exception, this class's error::display() method is invoked
    *    and the script is terminated with the exit() function. Upon encoutering a non-fatal error,
    *    it is saved
    *
    * @param bool $fatalNotice whether or not to produce a fatal error display if NOTICE level errors occur
    * @param bool $fatalNotice whether or not to produce a fatal error display if WARNING level errors occur
    * @param bool $showArgs whether or not to include function arguments in error display (ignored unless the ini value of "display_errors" is "1")
    */
   public static function initialize(bool $fatalNotice = true, bool $fatalWarning = false, bool $showArgs=false) : void {

      static::$_showArgs = $showArgs;
      
      if (!self::$_registeredFatalCheck) {
         self::$_registeredFatalCheck = true;
         register_shutdown_function(function() {
            
            $error = error_get_last();
            
            if (in_array($error['type'],[\E_ERROR,\E_PARSE,\E_CORE_ERROR,\E_COMPILE_ERROR,\E_USER_ERROR],true)) {
               self::ob_get_clean_all();
               $buffer = self::ob_get_clean_all();
               self::$_error_list[] = $error = new static('php-fatal',self::errno2const($error['type']),$error['message'],$error['file'],$error['line'],$error['type']);
               $errlist = self::enumerate();
               if ($fatal_handler) {
                  $fatal_handler($error,$errlist,$buffer);
               } else {
                  self::display($errlist,$buffer);
               }
               exit($errno);
            }
            
         });
      }
      
      set_exception_handler(function(  $e ) use($showArgs) {
         ////($type,$name,$msg,$file,$line,array $trace=null)
         $buffer = self::ob_get_clean_all();
         $trace=[];
         foreach($e->getTrace() as $l) {
            if (isset($l['args'])) unset($l['args']);
            $trace[]=$l;
         }
         self::$_error_list[] = $error = new static('php-exception',get_class($e),$e->getMessage(),$e->getFile(),$e->getLine(),$e->getCode(),$trace);
         
         $errlist = self::enumerate();
         if ($fatal_handler) {
            $fatal_handler($error,$errlist,$buffer);
         } else {
            self::display($errlist,$buffer);
         }
         $status=1;
         if ((sprintf("%d",$e->getCode()))==$e->getCode()) {
            $status=(int)$e->getCode();
         }
         exit($status);
      });
         if (!is_array(self::$_error_list)) self::$_error_list = [];
         $last_handler = set_error_handler(
               function($errno,$errstr,$errfile=null,$errline=null,array $errcontext=null) use(&$fatal_handler,&$nonfatal_handler) {
                  if (!(error_reporting() & $errno)) return;
                  self::$_error_list[] = $error = new static('php-error',self::errno2const($errno),$errstr,$errfile,$errline,$errno);
                  if (static::is_errno_fatal($errno)) {
                     $buffer = self::ob_get_clean_all();
                     $errlist = self::enumerate();
                     if ($fatal_handler) {
                        $fatal_handler($error,$errlist,$buffer);
                     } else {
                        self::display($errlist,$buffer);
                     }
                     exit($errno);
                  }elseif($nonfatal_handler) {
                     $nonfatal_handler($error);
                  }
               },\E_ALL);
         if (!self::$_orig_error_handler) self::$_orig_error_handler = $last_handler;
   }
   
   protected static function _serialize_html($input) : string {
      $param = array(
            'top_element'=>'div',
            'parent_element'=>'ul',
            'child_element'=>'li'
      );
      
      $html = "";
      //$html .= '<' . $param['top_element'] . ' data-meta="'.htmlspecialchars($type_info, ENT_QUOTES).'">'."\n";
      $html .= self::_data_to_html($input,$param['parent_element'],$param['child_element'],0 );
      //$html .= '</' . $param['top_element'] . '>'."\n";
      
      return $html;
   }
   protected static function _get_html_meta_value($input) {
      if (is_object($input)) {
         if ("stdClass" == ($className= get_class($input))) {
            return "object";
            //data:application/json;base64,$dump
            // $structure = json_encode(self::_get_structure( $input));
            // //return 'data:application/json;base64,'.base64_encode($structure);
            // return 'data:application/json;'.htmlspecialchars($structure,ENT_QUOTES | ENT_SUBSTITUTE);
         } else {
            return htmlspecialchars($className, ENT_QUOTES | ENT_SUBSTITUTE);
         }
      } else {
         //$type = gettype($input);
         return htmlspecialchars(gettype($input), ENT_QUOTES | ENT_SUBSTITUTE);
      }
   }
   private static function _ident_html($level=1,$size=3) {
      $ident="";
      for($i=0;$i<$size*$level;$i++) $ident.=" ";
      return $ident;
   }
   private static function _data_to_html($data,$parent_element,$child_element,$indent_level=1, $indent_size=3) {
      
      if ($index = is_array($data) || is_object($data)) {
         $i=0;
         $html = self::_ident_html($indent_level,$indent_size)."<$parent_element data-type=\"".self::_get_html_meta_value($data)."\">\n";
         foreach ($data as $key=>$value) {
            $indent_level++;
            $html .= self::_ident_html($indent_level,$indent_size)."<$child_element ";
            if ($index) $html .= "data-index=\"$i\" ";
            $html .= "data-key=\"".htmlspecialchars($key, ENT_QUOTES)."\" data-role=\"item\">";
            if (sprintf("%d",$key)!=$key) {
               $html .= "<span data-role=\"item-key\">".htmlspecialchars($key, ENT_QUOTES)."</span>".":&nbsp;";
            } else {
               $html .= "&nbsp;";
            }
            $value_type_str = "";
            if (is_scalar($value)) {
               $value_type_str = 'data-type="'.gettype($value).'"';
               if (is_bool($value)) {
                  $value_type_str .= ' data-boolean-value="'.($value?'true':'false').'"';
               }
            }
            if (is_null($value)) {
               $value_type_str = 'data-type="null"';
            }
            $html .=
            "<span data-role=\"item-value\" $value_type_str>". self::_data_to_html($value, $parent_element,$child_element,$indent_level);
            $html .= "</span></$child_element><!--/data-item: (".htmlspecialchars($key, ENT_QUOTES).")-->\n";
            $indent_level--;
            $i++;
         }
         $html .= self::_ident_html($indent_level,$indent_size)."</$parent_element>\n";
         return $html;
      } else {
         if (is_string($data) && $data=="") return "''";
         if (is_scalar($data) && ctype_print(str_replace(array("\n","\r"),"",(string) $data))) {
            return htmlspecialchars($data, ENT_QUOTES);
         } else {
            //               if (is_bool($data)) {
            //                  return ($data?"true":"false");
            //               }
            ob_start();
            var_dump($data);
            $dump = ob_get_clean();
            return "(dump) <br><pre>".nl2br(htmlspecialchars($dump, ENT_QUOTES))."</pre>";
         }
      }
      
      
   }
   
}