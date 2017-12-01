<?php
namespace MicroError;

class SerializedError {
   private $_type;
   public function get_type() {
      return $this->_type;
   }
   
   private $_name;
   public function get_name() {
      return $this->_name;
   }
   
   private $_msg;
   public function get_msg() {
      return $this->_msg;
   }
   
   private $_file;
   /**
    * @return string
    */
   public function get_file() {
      return $this->_file;
   }
   
   private $_code;
   public function get_code() {
      return $this->_code;
   }
   /**
    * @var int
    */
   private $_line;
   /**
    * @retrun int
    */
   public function get_line() {
      return $this->_line;
   }
   
   /**
    * @var array
    */
   private $_trace;
   /**
    * @return array
    */
   public function get_trace() {
      return $this->_trace;
   }
   /**
    * map of const names and wheather they are fatal
    */
   const fatalmap = [
         'E_ERROR'=>true,
         'E_WARNING'=>false,
         'E_PARSE'=>true,
         'E_NOTICE'=>false,
         'E_CORE_ERROR'=>true,
         'E_CORE_WARNING'=>false,
         'E_COMPILE_ERROR'=>true,
         'E_COMPILE_WARNING'=>false,
         'E_USER_ERROR'=>true,
         'E_USER_NOTICE'=>false,
         'E_STRICT'=>false,
         'E_RECOVERABLE_ERROR'=>false,
         'E_DEPRECATED'=>false,
         'E_USER_DEPRECATED' =>false,
   ];
   /*
    * primitive error and exception handling
    * @param int $errno
    */
   public static function errno2const($errno) {
      foreach ( self::fatalmap as $name=>$fatal ) {
         if (constant($name) == $errno) return $name;
      }
      
   }
   /**
    * @param int $errno
    * @return bool
    */
   public static function is_errno_fatal($errno) {
      $fatal = self::fatalmap;
      if (
            ($const = self::errno2const($errno))
            &&
            (isset($fatal[$const]))
            ) return $fatal[$const];
            return true;
   }
   /**
    * @param string $type
    * @param string $name
    * @param string $msg
    * @param string $file
    * @param int $line
    * @param bool | array[] | object[] $trace OPTIONAL. Default (bool) true. Specifies backtrace generation.
    *    If (bool) true, a
    * @param array $options OPTIONAL
    */
   public function __construct($type=null,$name=null,$msg=null,$file=null,$line=null,$code=null,$trace=true,array $options=null) {
      if ($type === null) return;
      $this->_type = $type;
      $this->_name = $name;
      $this->_msg = $msg;
      $this->_file = $file;
      $this->_code = $code;
      if ($line!==null) {
         $this->_line = (int) $line;
      }
      if (is_array($trace)) {
         $this->_trace = $trace;
      } else {
         if ($trace!==false) {
            if (!empty($options['backtrace_show_args'])) {
               $traceopt = null;
            } else {
               $traceopt = \DEBUG_BACKTRACE_IGNORE_ARGS;
            }
            $this->_trace = self::generate_trace(null,$traceopt);
         }
      }
   }
   
   /**
    * Wrapper for debug_print_backtrace(). Generates a PHP backtrace.
    *
    * @param int $offset Optional. Number of backtrace lines to ignore.
    * @param int $options Bitmask for the options to debug_print_backtrace().
    * @return array[] Each line from debug_print_backtrace().
    * @see debug_print_backtrace() Used to generate the backtrace.
    */
   public static function generate_trace($offset=0,$options=null) {
      
      ob_start();
      
      $oldHandler=set_error_handler(function($errno, $errstr, $errfile, $errline)  {});
      debug_print_backtrace($options);
      if ($oldHandler) set_error_handler($oldHandler);
      $data = ob_get_clean();
      $data = explode("\n", $data);
      $trace = array ();
      $i = - 1;
      $offset=$offset+1;
      foreach ( $data as $line ) {
         if ("#" == substr($line, 0, 1)) {
            $i ++;
            if ($offset>$i) continue;
            /*
             * don't include actvp\deploy\error_handler in trace
             */
            if (false !== ( strpos($line, get_called_class()) )) {
               continue;
            }
            /*
             * remove numeric identifyer of trace (superfluous because it can be
             * inferred by order within trace array) add to trace array
             */
            $trace[] = trim(str_replace("#$i ", "", $line));
         }
      }
      return $trace;
   }
   
   /**
    * @return array
    */
   public function get_assoc() {
      $data=[];
      foreach(['type','name','code','msg','file','line','trace',] as $k) {
         $data[$k] = $this->{"get_$k"}();
      }
      return $data;
   }
   
}


