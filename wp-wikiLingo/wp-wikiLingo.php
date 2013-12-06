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

	//Version
	static $version ='1';

	//Options and defaults
	static $options = array(
		'post_types'=>array(),
		'wikiLingobar'=>array()
	);
	static $option_types = array(
		'post_types'=>'array',
		'wikiLingonbar'=>'array',
		'prettify'=>'checkbox',
	);

	public function __construct() {
		require_once(dirname(__FILE__)."/wikiLingo/autoload.php");
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
		//Allow translations
		load_plugin_textdomain( 'wikiLingo', false, basename(dirname(__FILE__)).'/languages');

		//wikiLingo posts and comments
		add_filter('the_content', array($this, 'the_content'), 5);
		add_filter('get_comment_text',array($this,'get_comment_text'),5);

		//Register scripts
		add_action('wp_enqueue_scripts', array($this,'register_scripts'));
	}

	/*
	* Settings
	*/
	function admin_init(){
		register_setting('writing',$this->domain, array($this,'validate'));
		add_settings_section( $this->domain.'_section', 'wikiLingo', array($this,'settings'), 'writing');
		add_settings_field($this->domain.'_posttypes', __('Enable wikiLingo for:', 'wikiLingo'), array($this,'settings_posttypes'), 'writing', $this->domain.'_section');
		add_settings_field($this->domain.'_wikiLingobar', __('Enable wikiLingo help bar for:', 'wikiLingo'), array($this,'settings_wikiLingobar'), 'writing', $this->domain.'_section');

		//Remove html tab for wikiLingo posts
		add_filter( 'user_can_richedit', array($this,'can_richedit'), 99 );

		//Add admin scripts
		if($this->is_bar_enabled('posteditor')){
			add_action('admin_enqueue_scripts', array($this,'admin_scripts'),10,1);
		}
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

	function settings_wikiLingobar(){
		$options = get_option($this->domain);
		$savedtypes = (array) $options['post_types'];
		$barenabled = isset($options['wikiLingobar']) ? $options['wikiLingobar']  : self::$options['wikiLingobar'];
		$types=get_post_types(array('public'   => true),'objects'); 

		$id = "id={$this->domain}_wikiLingobar'";
		//If Forum, Topic, and Replies exist assume BBPress is activated:
		$type_names = array_keys($types);
		$bbpress = array_unique(array_merge($type_names,array('reply','forum','topic'))) === $type_names;

		echo "<label><input type='checkbox' {$id} ".checked(in_array('posteditor',$barenabled),true,false)."name='{$this->domain}[wikiLingobar][]' value='posteditor' />".__('Post editor',$this->domain)."</label></br>";				
		echo "<label><input type='checkbox' {$id} ".checked(in_array('comment',$barenabled)&&in_array('comment',$savedtypes),true,false)."name='{$this->domain}[wikiLingobar][]' value='comment' />".__('Comments',$this->domain)."</label></br>";
		echo "<label><input type='checkbox' {$id} ".checked(in_array('bbpress',$barenabled),true,false).disabled($bbpress,false,false)."name='{$this->domain}[wikiLingobar][]' value='bbpress' />".__('bbPress topics and replies',$this->domain)."</label></br>";
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

	function is_bar_enabled($id_or_type){
		if(is_int($id_or_type))
			$type = get_post_type($id_or_type);
		else
			$type = esc_attr($id_or_type);

		$options = get_option($this->domain);
		$barenabled = (array) $options['wikiLingobar'];

		return in_array($type,$barenabled);
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

	/*
	* Register the scripts for the PageDown editor
	*/
	function register_scripts() {
		//This script sets the ball rolling with the editor & preview
   		//wp_register_script( 'wp-wikiLingo', $plugin_dir . "js/wikiLingo{$min}.js", $wikiLingo_dependancy, self::$version );
	}

	function admin_scripts($hook){
		$screen = get_current_screen();
		$post_type = $screen->post_type;
    		if ( ('post-new.php' == $hook || 'post.php' == $hook) && $this->is_wikiLingoable($post_type) ){
				$this->register_scripts();
		}
	}
}

$wikiLingo = new WordPress_WikiLingo();
