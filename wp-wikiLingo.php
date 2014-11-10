<?php
/*
Plugin Name: wikiLingo
Description: Allows you to use wikiLingo in posts, pages, and comments
Version: 1
Author: Robert Plummer
Author URI: http://github.com/wikiLingo
*/
/*  Copyright 2014 Robert Plummer (RobertLeePlummerJr@gmail.com)

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
	public static $scripts;
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
		self::$scripts = new WikiLingo\Utilities\Scripts();
		$this->parser = new WikiLingo\Parser(self::$scripts);
		self::$scripts->addScript('var $ = jQuery;');

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

		//Allow translations
		load_plugin_textdomain( 'wikiLingo', false, basename(dirname(__FILE__)).'/languages');

		//wikiLingo posts and comments
		add_filter('the_content', array($this, 'the_content'), 5);
		add_filter('get_comment_text',array($this,'get_comment_text'),5);

		//Register scripts
		//add_action('admin_head', array($this, 'admin_head'));
		add_action('wp_footer', array($this,'wp_footer'), 5);

		//This script sets the ball rolling with the editor & preview

		wp_enqueue_script('wp-jQuery-ui', $this->path . 'vendor/jquery/jquery-ui/ui/jquery-ui.js', array(), false, true);
		wp_enqueue_style('wp-jQuery-ui', $this->path . 'vendor/jquery/jquery-ui/themes/base/jquery.ui.all.css');

	}

	/*
	* Settings
	*/
	function admin_init(){

		register_setting('writing',$this->domain, array($this,'validate'));
		add_settings_section( $this->domain.'_section', 'wikiLingo', array($this,'settings'), 'writing');
		add_settings_field($this->domain.'_posttypes', __('Enable wikiLingo for:', 'wikiLingo'), array($this,'settings_posttypes'), 'writing', $this->domain.'_section');

		//Remove html tab for wikiLingo posts
		//add_filter( 'user_can_richedit', array($this,'can_richedit'), 99 );

		//Add admin scripts
		add_action('admin_enqueue_scripts', array($this,'register_scripts'),10,1);
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
		echo '<p>'.__("Select the post types or comments that will support wikiLingo",$this->domain).'</p>';
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
	
	function get_option( $option ){
		$options = get_option($this->domain);
		if( !isset( $options[$option] ) )
			return false;
		
		return $options[$option];
	}

	//For comments & pages
	function the_content( $content ){
		$comment = $this->parser->parse( $content );

		$current_user = wp_get_current_user();
		if (in_array('administrator', $current_user->roles)) {
			add_action('wp_footer', array($this,'register_scripts'), 5);

			wp_enqueue_script('wp-wikiLingo-inline-editor', $this->path . 'wikiLingoInlineEditor.js');

			$siteUrl = get_site_url();

			$this->parser->scripts
				->addScript(<<<JS
$('article').each(function() {
	var article = this,
		articleId = article.getAttribute('id').replace('post-', '') * 1,
		editableArea = $(this).find('.entry-content')[0];

	$(article).find('a.post-edit-link').click(function() {
		wikiLingoInlineEditor($(this), $(this).parent().parent(), articleId, editableArea, '$siteUrl');
		return false;
	});
});
JS
);
		}

		return $comment;
	}
	function get_comment_text( $comment ){
		$comment = $this->parser->parse( $comment );

		return $comment;
	}

	function wp_footer($footer)
	{
		echo self::$scripts->renderCss();
		echo self::$scripts->renderScript();
	}

	/*
	* Register the scripts for the PageDown editor
	*/
	function register_scripts() {

		//create a new group of possible syntaxes possible in the WikiLingo to WYSIWYG parser
		$expressionSyntaxes = new WikiLingoWYSIWYG\ExpressionSyntaxes(self::$scripts);

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

		wp_enqueue_style('wp-wikiLingo-editor', $this->path . 'wikiLingoEditor.css');
		wp_enqueue_script('wp-wikiLingo-editor', $this->path . 'wikiLingoEditor.js');


		//load CodeMirror
		wp_enqueue_style('wp-wikiLingo-codemirror', $this->path . 'vendor/codemirror/codemirror/lib/codemirror.css');
		wp_enqueue_script('wp-wikiLingo-codemirror', $this->path . 'vendor/codemirror/codemirror/lib/codemirror.js');

		wp_enqueue_style('wp-wikiLingo-codemirror-mode', $this->path . 'vendor/wikilingo/codemirror/wikiLingo.css');
		wp_enqueue_script('wp-wikiLingo-codemirror-mode', $this->path . 'vendor/wikilingo/codemirror/wikiLingo.js');

		//load icons for wikiLingo
		wp_enqueue_style('wp-wikiLingo-icons', $this->path . 'vendor/wikilingo/wikilingo/editor/IcoMoon/sprites/sprites.css');
	}
}

new WordPress_WikiLingo();

//remove tinymce
require_once('_WP_Editors.php');