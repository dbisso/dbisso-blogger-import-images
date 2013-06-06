<?php
/*
Core framework class:
Just provides the basic auto-hooking for filters, actions and shortcodes
and a logging function

Version: 2.4
*/
namespace DBisso\Util;

class Hooker {
	var $gtdomain = 'default';
	var $theme_hooks = array();
	var $hooked_class;
	var $added_hooks = array();

	function __construct( $hooked_class = null, $hook_prefix = null ){
		// If a class is specified in the constructor, kick things off
		// otherwise we wait for the hook() method to be called.
		if ( $hooked_class ) $this->hook( $hooked_class, $hook_prefix );
	}

	public function hook( $hooked_class = null, $hook_prefix = null ) {
		$this->hooked_class = $hooked_class;
		if ( !empty( $hook_prefix ) ) $this->hook_prefix = $hook_prefix;
		$this->plugin_dir = dirname(__FILE__);
		$this->log_method = 'error_log';
		$this->krumo_var = array();

		$this->class_reflector = $class_reflector = new \ReflectionClass($this->hooked_class);
		$methods = $class_reflector->getMethods();

		foreach($methods as $method){
			$method_reflector = $class_reflector->getMethod($method->name);
			$statics = $method_reflector->getStaticVariables();
			$priority = isset($statics['wp_hook_priority'])? $statics['wp_hook_priority']:10;
			$args = isset($statics['wp_hook_args'])? $statics['wp_hook_args']:99;
			$hook_name = isset($statics['wp_hook_override'])? $statics['wp_hook_override'] : null;
			$shortcode_name = isset($statics['wp_shortcode_name'])? $statics['wp_shortcode_name'] : preg_replace('|_|','-',$hook_name);
			$method_parts = $this->parse_method_name($method->name);

			if($method_parts){
				list($hook_type, $hook_name, $is_theme_hook) = $method_parts;
				$hook_name = isset($statics['wp_hook_override'])? $statics['wp_hook_override'] : $hook_name;

				if($is_theme_hook) {
					$this->theme_hooks[] = array($hook_type, $hook_name, $method->name, $priority, $args);
					continue;
				}

				$this->add_hook($hook_type, $hook_name, $method->name, $priority, $args);
			}
			//error_log($method->name.' : '.$hook_type.' : '.$name);
		}

		if ( method_exists( $this, 'init' ) ) {
			$this->init();
		}

		add_action('wp_footer', array($this,'wp_footer'));

		add_action('after_setup_theme', array($this, 'add_theme_hooks'), 12);
	}

	public function init(){}

	public function add_theme_hooks(){
		try {
			$hook_prefix = isset( $this->hook_prefix ) ? $this->hook_prefix : $this->class_reflector->getStaticPropertyValue( 'wp_hook_prefix' );

			foreach ( $this->theme_hooks as $theme_hook ) {
				$theme_hook[1] = $hook_prefix . '_' . $theme_hook[1];
				call_user_method_array('add_hook', $this, $theme_hook);
			}
		} catch ( Exception $e ) {}
	}

	private function parse_method_name($name){
		$is_theme_hook = false;

		if(preg_match('|^theme_|', $name)){
			$name = str_replace('theme_', '', $name);
			$is_theme_hook = true;
		}

		if(preg_match('|^(_?[^_]+_)(.*)$|', $name, $matches)){
			$hook_type = trim($matches[1],'_'); //Methods prefixed with _ are always hooked
			$hook_name = $matches[2];
			return array($hook_type, $hook_name, $is_theme_hook);
		}

		return false;
	}

	private function add_hook($hook_type, $hook_name, $method_name, $priority = 10, $args = 99){
		if ( !in_array( $hook_type , array( 'action', 'filter', 'shortcode' ) ) ) return;

		switch($hook_type){
			case 'action':
				add_action($hook_name , array($this->hooked_class, $method_name), $priority, $args);
				break;
			case 'filter':
				add_filter($hook_name, array($this->hooked_class, $method_name), $priority, $args);
				break;
			case 'shortcode':
 				add_shortcode(preg_replace('|_|', '-', $hook_name), array($this->hooked_class, $method_name));
				break;
		}
		$this->added_hooks[$hook_type][$hook_name][$priority][] = $method_name;
	}

	public function get_hooks($type = null) {
		return $type ? $this->added_hooks[$type] : $this->added_hooks;
	}

	public function log($var,$method=null){
		if(!$method) $method = $this->log_method;
		$output = print_r($var,true);
		switch($method){
			case 'error_log':
				error_log($output);
				break;
			case 'html':
				echo '<pre>'.$output.'</pre>';
				break;
			case 'krumo':
				$this->krumo_var[] = $var;
				break;
			case 'echo':
			default:
				echo $output;
				break;
		}
	}

	public function wp_footer(){
		static $wp_hook_priority = 9999;

		if(!isset($this->krumo_var)) return;

		ob_start();
		foreach($this->krumo_var as $var){
			krumo($var);
		}
		$out = ob_get_clean();

		if(strlen($out)){
			echo "<div id='devbar' style='
					background:black;
					color:blue;
					width:90%;
					max-height: 80%;
					overflow-y:scroll;
					border-bottom:1px solid #555;
					position:fixed;
					top:0;left:0;
					font-size:15px;
					font-style:normal;
					padding:4px;
					z-index:9999999'>
					$out
			</div>";
		}
	}

	function _get_or_null($var, $default = null){
		return isset($var)? $var : $default;
	}
}
