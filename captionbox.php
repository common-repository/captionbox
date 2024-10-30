<?php
/*
Plugin Name: CaptionBox
Plugin URI: http://speakertext.com/captionbox
Description: Automatically load CaptionBox on your Wordpress site, and optionally load transcripts automatically. Requires PHP5 and Wordpress 2.7 or later.
Version: 1.1.2
Author: Tyler Kieft
Author URI: http://speakertext.com

Some stuff taken from 
http://wordpress.org/extend/plugins/brolly-wordpress-plugin-template/
	
*/

// Sets up plugin configuration and routing based on names of plugin folder and files.

$plugin_name = 'captionbox';
$plugin_file = $plugin_name . '.php';
$plugin_class = 'CaptionBox';
$plugin_class_file = $plugin_class . '.class.php'; 
$plugin_prefix = $plugin_name . '_'; // define the plugin prefix we are going to use for naming all classes, ids, actions etc... this is done to avoid conflicts with other plugins
$plugin_dir = trailingslashit( get_bloginfo('wpurl') ) . PLUGINDIR . '/' . $plugin_name;

// Include the class file
if (!class_exists($plugin_class)) {		
	require_once(dirname(__FILE__).'/'.$plugin_class_file);	
}

//Create a new instance of the class file
if (class_exists($plugin_class)) {
  $captionbox = new $plugin_class($plugin_prefix, $plugin_name, $plugin_dir);
}


//Setup actions, hooks and filters
if(isset($captionbox)){

	// Activation function
	register_activation_hook(__FILE__,array($captionbox, 'activate'));
	
	// Deactivation function
	register_deactivation_hook(__FILE__,array($captionbox, 'deactivate'));
	
	// create custom plugin settings menu
	add_action('admin_menu', array($captionbox, 'create_menu'));
	
	// call register settings function
	add_action('admin_init', array($captionbox, 'register_settings'));
	
	add_action('wp_print_scripts', array($captionbox, 'add_captionbox_scripts'));
	add_action('wp_print_styles', array($captionbox, 'add_captionbox_styles'));

	add_filter('the_content', array($captionbox, 'filter_the_content'), 1000);

}
	
?>