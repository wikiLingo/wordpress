<?php
/*
Plugin Name: wikiLingo
Description: Allows you to use wikiLingo in posts, pages, and comments
Version: 1
Author: Robert Plummer
Author URI: http://github.com/wikiLingo
*/
/*  Copyright 2013 Robert Plummer (RobertLeePlummerJr@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/
class WordPress_WikiLingo {

	public $domain = 'wikiLingo';
	public $parser;
	public $path;

	//Version
	static $version ='1';

	//Options and defaults
	static $options = array(
		'post_types'=>array()
	);
	static $option_types = array(
		'post_types'=>'array'
	);

	public function __construct() {
		require_once(dirname(__FILE__)."/vendor/autoload.php");
		$this->parser = new WikiLingo\Parser();
		register_activation_hook(__FILE__,array(__CLASS__, 'install' ));
		register_uninstall_hook(__FILE__,array( __CLASS__, 'uninstall' ));
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	static function install(){
		update_option("wikiLingo_version",self::$version);
		add_option('wikiLingo',self::$options);
	}

	static function uninstall(){
		delete_option("wikiLingo_version");
		delete_option('wikiLingo');
	}


	public function init() {
		$siteurl = trailingslashit(get_option('siteurl'));
		if (DEFINED('WP_PLUGIN_URL')) {
			$this->path = WP_PLUGIN_URL . '/wikiLingo/';
		} else if (DEFINED('WP_PLUGIN_DIR')) {
			$this->path = $siteurl . '/' . WP_PLUGIN_DIR . '/wikiLingo/';
		} else {
			$this->path = $siteurl . 'wp-content/plugins/wikiLingo/';
		}

		//remove tinymce
		//add_action('admin_print_footer_scripts', array($this, 'remove_tinymce'));

		//Allow translations
		load_plugin_textdomain( 'wikiLingo', false, basename(dirname(__FILE__)).'/languages');

		//wikiLingo posts and comments
		add_filter('the_content', array($this, 'the_content'), 5);
		add_filter('get_comment_text',array($this,'get_comment_text'),5);

		//Register scripts
		//add_action('admin_head', array($this, 'admin_head'));
		add_action('wp_footer', array($this,'wp_footer'), 5);
	}

	/*
	* Settings
	*/
	function admin_init(){

		register_setting('writing',$this->domain, array($this,'validate'));
		add_settings_section( $this->domain.'_section', 'wikiLingo', array($this,'settings'), 'writing');
		add_settings_field($this->domain.'_posttypes', __('Enable wikiLingo for:', 'wikiLingo'), array($this,'settings_posttypes'), 'writing', $this->domain.'_section');

		//Remove html tab for wikiLingo posts
		add_filter( 'user_can_richedit', array($this,'can_richedit'), 99 );

		//Add admin scripts
		add_action('admin_enqueue_scripts', array($this,'admin_scripts'),10,1);
	}

	public function can_richedit($bool){
		$screen = get_current_screen();
		$post_type = $screen->post_type;
		if($this->is_wikiLingoable($post_type))
			return false;

		return $bool;
	}

	function settings(){
		//settings_fields('wikiLingo');
		echo '<p>'.__("Select the post types or comments that will support wikiLingo. Comments and bbPress forums can also feature a wikiLingo 'help bar' and previewer. Automatic syntax highlighting can be provided by <a href='http://code.google.com/p/google-code-prettify/' target='_blank'>Prettify</a>.",$this->domain).'</p>';
	}

	function settings_posttypes(){
		$options = get_option($this->domain);
		$savedtypes = (array) $options['post_types'];
		$types=get_post_types(array('public'   => true),'objects'); 
		unset($types['attachment']);

		$id = "id={$this->domain}_posttypes'";
		foreach ($types as $type){
			echo "<label><input type='checkbox' {$id} ".checked(in_array($type->name,$savedtypes),true,false)."name='{$this->domain}[post_types][]' value='$type->name' />{$type->labels->name}</label></br>";
		}
		echo "<label><input type='checkbox' {$id} ".checked(in_array('comment',$savedtypes),true,false)."name='{$this->domain}[post_types][]' value='comment' />Comments</label></br>";	
	}

	function validate($options){
		$clean = array();
		
		foreach (self::$options as $option => $default){
			if(self::$option_types[$option]=='array'){
				$clean[$option] = isset($options[$option]) ? array_map('esc_attr',$options[$option]) : $default;
			}elseif(self::$option_types[$option]=='checkbox'){
				$clean[$option] = isset($options[$option]) ? (int) $options[$option] : $default;
			}
		}

		return $clean;
	}

	

	/*
	* Function to determine if wikiLingo has been enabled for the current post_type or comment
	* If an integer is passed it assumed to be a post (not comment) ID. Otherwise it assumed to be the
	* the post type or 'comment' to test.
	*
	* @param (int|string) post ID or post type name or 'comment'
	* @return (true|false). True if wikiLingo is enabled for this post type. False otherwise.
	* @since 1.0
	*/
	function is_wikiLingoable($id_or_type){
		if(is_int($id_or_type))
			$type = get_post_type($id_or_type);
		else
			$type = esc_attr($id_or_type);

		$options = get_option($this->domain);
		$savedtypes = (array) $options['post_types'];

		return in_array($type,$savedtypes);
	}
	
	function get_option( $option ){
		$options = get_option($this->domain);
		if( !isset( $options[$option] ) )
			return false;
		
		return $options[$option];
	}


	/*
	 * Convert wikiLingo to HTML prior to insertion to database
	 * Also this ensures the prettify styles & scripts are in the queue
	 * When on a home page prettify wont already have been queued.
	 */
	//For comments & pages
	function the_content( $comment ){
		//this is a page
		if( $this->is_wikiLingoable( 'page' ) && isset($_REQUEST['page_id'])){
			$comment = $this->parser->parse( $comment );
		}

		//this is a post
		else if( $this->is_wikiLingoable( 'post' ) ){
			$comment = $this->parser->parse( $comment );
		}

		return $comment;
	}
	function get_comment_text( $comment ){
		if( $this->is_wikiLingoable( 'comment' ) ){
			$comment = $this->parser->parse( $comment );
		}
		return $comment;
	}

	function admin_head()
	{

	}

	function wp_footer($footer)
	{
		echo $this->parser->scripts->renderCss();
		echo $this->parser->scripts->renderScript();
	}

	public function remove_tinymce() {
		if (has_action('admin_print_footer_scripts', 'wp_tiny_mce')) {
			remove_action('admin_print_footer_scripts', 'wp_tiny_mce', 1);
		}
	}

	/*
	* Register the scripts for the PageDown editor
	*/
	function register_scripts() {
		//This script sets the ball rolling with the editor & preview
		//foreach($this->parser->scripts->)

   		//wp_register_script( 'wp-wikiLingo', $plugin_dir . "js/wikiLingo{$min}.js", $wikiLingo_dependancy, self::$version );

		//create a new group of possible syntaxes possible in the WikiLingo to WYSIWYG parser
		$expressionSyntaxes = new WikiLingoWYSIWYG\ExpressionSyntaxes($this->parser->scripts);

		//register expression types so that they can be turned into json and sent to browser
		$expressionSyntaxes->registerExpressionTypes();

		//json encode the parsed expression syntaxes
		$expressionSyntaxesJson = json_encode($expressionSyntaxes->parsedExpressionSyntaxes);

		echo "<script>var expressionSyntaxes = {$expressionSyntaxesJson};</script>";

		//load Medium.js
		wp_enqueue_style('wp-wikiLingo-medium.js', $this->path . 'vendor/mediumjs/mediumjs/medium.css');
		wp_enqueue_script('wp-wikiLingo-medium.js', $this->path . 'vendor/mediumjs/mediumjs/medium.js');

		//load Rangy
		wp_enqueue_script('wp-wikiLingo-rangy-core', $this->path . 'vendor/rangy/rangy/rangy-core.js');
		wp_enqueue_script('wp-wikiLingo-rangy-cssapplier', $this->path . 'vendor/rangy/rangy/rangy-cssclassapplier.js');

		//load undojs
		wp_enqueue_script('wp-wikiLingo-undo.js', $this->path . 'vendor/undojs/undojs/undo.js');

		//load wikiLingo
		wp_enqueue_script('wp-wikiLingo-1', $this->path . 'vendor/wikilingo/wikilingo/editor/WLExpressionUI.js');

		wp_enqueue_script('wp-wikiLingo-2', $this->path . 'vendor/wikilingo/wikilingo/editor/WLPluginEditor.js');
		wp_enqueue_script('wp-wikiLingo-3', $this->path . 'vendor/wikilingo/wikilingo/editor/WLPluginAssistant.js');

		wp_enqueue_style('wp-wikiLingo-bubble', $this->path . 'vendor/wikilingo/wikilingo/editor/bubble.css');
		wp_enqueue_script('wp-wikiLingo-bubble', $this->path . 'vendor/wikilingo/wikilingo/editor/bubble.js');

		wp_enqueue_script('wp-wikiLingo-editor', $this->path . 'wikilingo.editor.js');

		//load CodeMirror
		wp_enqueue_style('wp-wikiLingo-codemirror', $this->path . 'vendor/codemirror/codemirror/lib/codemirror.css');
		wp_enqueue_script('wp-wikiLingo-codemirror', $this->path . 'vendor/codemirror/codemirror/lib/codemirror.js');

		wp_enqueue_style('wp-wikiLingo-codemirror-mode', $this->path . 'vendor/wikilingo/codemirror/wikiLingo.css');
		wp_enqueue_script('wp-wikiLingo-codemirror-mode', $this->path . 'vendor/wikilingo/codemirror/wikiLingo.js');

		//load icons for wikiLingo
		wp_enqueue_style('wp-wikiLingo-icons', $this->path . 'vendor/wikilingo/wikilingo/editor/IcoMoon/sprites/sprites.css');
	}

	function admin_scripts($hook){
		//$screen = get_current_screen();
		//$post_type = $screen->post_type;
    		//if ( ('post-new.php' == $hook || 'post.php' == $hook) && $this->is_wikiLingoable($post_type) ){
				$this->register_scripts();
		//}
	}
}

new WordPress_WikiLingo();
require_once('_WP_Editors.php');