<?php
/**
 * Plugin Name: Jack Jamieson reader
 * Plugin URI: jackjamieson.net
 * Description: This is the beginning of my reader plugin.  
 * Version: 0.0
 * Author: Jack Jamieson
 * Author URI: http://jackjamieson.net
 * Text Domain: jjreader
 */
 
 /* Copyright 2017 Jack Jamieson (email : jackjamieson@gmail.com)
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

/* 
	Portions of code are modified from Ashton McAllan's WhisperFollow plugin. 
	(http://acegiak.machinespirit.net/2012/01/25/whisperfollow/).
*/ 

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );



global $jjreader_db_version;
$jjreader_db_version = "1.0a";

/* Enqueue scripts and styles for the reader page */ 
add_action( 'wp_enqueue_scripts', 'jjreader_enqueue_scripts' );
function jjreader_enqueue_scripts() {
	wp_enqueue_script( 'jquery-ui', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.js', array('jquery'), null, true);
	wp_enqueue_script( 'jjreader_js', plugin_dir_url( __FILE__ ).'js/jjreader.js', array('jquery'), null, true);
	

	wp_enqueue_style( 'jquery-ui-style', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.css' );
	wp_enqueue_style( 'jjreader-style', plugin_dir_url( __FILE__ ).'css/jjreader.css' );
	//Add ajax support for the jjreader_js script
	wp_localize_script( 'jjreader_js', 'jjreader_ajax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' )
	));
}

/* Define what page should be displayed when "JJ Reader Settings" is clicked in the dashboard*/
function jjreader_admin() {
    include('jjreader_admin.php');
}

/* Create the menu option "JJ Reader Settings" */ 
function jjreader_admin_actions() {
	/* add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);*/
	add_options_page("JJ Reader Settings", "JJ Reader Settings", 1, "jjreader_settings", "jjreader_admin");
}
/* Hook to run jjreader_admin_actions when WordPress generates the admin menu */ 
add_action('admin_menu', 'jjreader_admin_actions');


/* Display an admin notice to configure the plugin when newly installed */
function initial_setup_admin_notice() {
    ?>
    <div class="notice notice-info is-dismissible">
        <p><?php _e( 'JJ Reader needs to be configured (Add a link to the settings page)', 'sample-text-domain' ); ?></p>
    </div>
    <?php
}


/* Hook to display admin notice */ 
add_action( 'admin_notices', 'initial_setup_admin_notice' );
	

/* Create a new table for the reader settings */ 
function jjreader_create_tables() {
	global $wpdb;
	global $jjreader_db_version;

	/*
	// Create settings table
	$table_settings = $wpdb->prefix . "jjreader_settings";

	$sql = "CREATE TABLE " . $table_settings . " (
		key text DEFAULT '' NOT NULL,
		value text DEFAULT '' NOT NULL
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	*/ 
	
	
	// Create table to store log
	$table_log = $wpdb->prefix . "jjreader_log";

	$sql = "CREATE TABLE " . $table_log . " (
		id int NOT NULL AUTO_INCREMENT,
		date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		log text DEFAULT '' NOT NULL,
		PRIMARY KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	jjreader_log("Created jjreader_log table");
	
	// Create table to store list of sites to be followed
	$table_following = $wpdb->prefix . "jjreader_following";

	$sql = "CREATE TABLE " . $table_following . " (
		id int NOT NULL AUTO_INCREMENT,
		added datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		siteurl text DEFAULT '' NOT NULL,
		feedurl text DEFAULT '' NOT NULL,
		sitetitle text DEFAULT '' NOT NULL,
		feedtype text DEFAULT '' NOT NULL,
		PRIMARY KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	jjreader_log("Created jjreader_following table");
	// Create table to store posts from followed sites

	$table_posts = $wpdb->prefix . "jjreader_posts";

	$sql = "CREATE TABLE " . $table_posts . " (
		id int NOT NULL AUTO_INCREMENT,
		date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		authorname text DEFAULT '' NOT NULL,
		authorurl text DEFAULT '' NOT NULL,
		authoravurl text DEFAULT '' NOT NULL,
		permalink text DEFAULT '' NOT NULL,
		title text DEFAULT '' NOT NULL,
		content mediumtext DEFAULT '' NOT NULL,
		posttype text DEFAULT '' NOT NULL,
		viewed boolean DEFAULT FALSE NOT NULL,
		liked text DEFAULT '' NOT NULL,
		replied text DEFAULT '' NOT NULL,
		reposted text DEFAULT '' NOT NULL,
		rsvped text DEFAULT '' NOT NULL,
		PRIMARY KEY id (id)
	);";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	jjreader_log("Created jjreader_posts table");
		
	add_option( 'jjreader_db_version', $jjreader_db_version );
	jjreader_log("Updated jjreader_db_version field");
}


/* Check if the database version has changed, and update the database if so */
function jjreader_update_db_check() {
	global $jjreader_db_version;
	if (get_site_option('jjreader_db_version') != $jjreader_db_version) {
		jjreader_create_tables();
	}
}


/* Create the following page */ 
function create_following_page(){
	echo "Creating following page";
    $feedlocation = get_option('jjreader_feedlocation');

        if (!get_page_by_title( $feedlocation )){
        $current_user = wp_get_current_user();
         if ( !($current_user instanceof WP_User) )
             wp_die("Couldn't get current user to create follow page");
        $post = array(
                'comment_status' => 'closed', 
                'ping_status' => 'closed', 
                'post_author' => $current_user->ID,
                'post_content' => '[jjreader_page]', 
                'post_name' => $feedlocation, 
                'post_status' => 'publish', 
                'post_title' => 'JJ Reader', 
                'post_type' => 'page' 
            ); 
            if(wp_insert_post( $post )<1)
                wp_die("Could not create the followpage");
        
        }else{
            wp_die("There is already a page called '" + $feedlocation +"'. Please choose a different name" );
            /* THIS SHOULD BE REPLACED BY FUNCTION TO RENAME THE EXISTING PAGE*/
            
    } 
}


// Function to make the jjreader_page shortcode work
function jjreader_page_shortcode() {
    jjreader_page();
}
add_shortcode('jjreader_page', 'jjreader_page_shortcode');


// The Following page, visible on the front end
function jjreader_page(){
	
	?> 
	
	<div class="reader-settings">
	
	<?php
	
	//Check if the user is logged in with sufficient privileges to EDIT the page
	/* Only editors or admins can edit the list of subscribed sites */ 

	if(current_user_can( 'edit_pages')){
	 	// Only allow editors or above to edit the subscriptions
	 	// (Only editors or above can edit pages)
		jjreader_subscription_editor();
	}
	?>
	</div><!--.reader-settings-->
	<?php
	//Check if the user is logged in with sufficient privileges to VIEW the page
	/* Any logged in user can view the following page */
	jjreader_subscription_viewer();
		
}
// Fetch and display posts from subscribed sites
function jjreader_subscription_viewer(){
	?>
	A button element
	<?php
	echo "Show the feed here";
	
	//Check if the user is logged in with sufficient privileges to create posts
	//( This should go below each post)
	jjreader_reply_actions();

}

// Show interface for adding/removing/editing subscriptions
function jjreader_subscription_editor(){
	?>
	<div id="jjreader-addSite-form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<strong>Add a subscription</strong><br>
        <label for="jjreader-siteurl">Site URL </label><input type="text" name="jjreader-siteurl" value="" size="30"><br>
        <button id="jjreader-addSite-findFeeds" class="ui-button ui-corner-all ui-widget">Find feeds & title</button>
		<br><br>
		<label for="jjreader-feedurl">Feed URL </label><input type="text" name="jjreader-feedurl" value="" size="30"><br>
		<label for="jjreader-sitetitle">Site Title </label><input type="text" name="jjreader-sitetitle" value="" size="30"><br>
		<button id="jjreader-addSite-submit" class="ui-button ui-corner-all ui-widget">Submit</button>
	</div>




	<button id="jjreader-button-addSite" class="ui-button ui-corner-all ui-widget">Add Site</button>
	<?php
}

// Show reply actions if the user has permission to create posts
function jjreader_reply_actions(){
	if(current_user_can( 'publish_posts')){
		echo "reply buttons";
	}
}

//Log changes to the database (adding sites, fetching posts, etc.)
function jjreader_log($message){
	global $wpdb;
	$table_name = $wpdb->prefix . 'jjreader_log';

	$wpdb->insert( 
		$table_name, 
		array( 
			'date' => current_time( 'mysql' ), 
			'log' => $message, 
		) 
	);

}


function add_reader_post($permalink,$title,$content,$authorname='',$authorurl='',$time=0,$avurl=''){
	reader_log("adding post: ".$permalink.": ".$title);
	global $wpdb;
	if($time < 1){
		$time = time();
	}
	$table_name = $wpdb->prefix . "jjreader_posts";
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE permalink LIKE \"".$permalink."\";")<1){
		jjreader_log("no duplicate found");
		$rows_affected = $wpdb->insert( $table_name,
			array(
				'permalink' => $permalink,
				'title' => $title,
				'content' => $content,
				'authorname' => $authorname,
				'authorurl' => $authorurl,
				'time' => date( 'Y-m-d H:i:s', $time),
				'authoravurl' => $avurl,
				'viewed' =>false,
				'type' => "Post",
			 ) );

		if($rows_affected == false){
		
			jjreader_log("could not insert whisper into database!");
			die("could not insert whisper into database!");
		}else{
			jjreader_log("added ".$title." from ".$authorurl);
		}
	}else{
		jjreader_log("duplicate detected");
	}

}

// Redoing the add subscription function

// Add a new subscription
add_action( 'wp_ajax_jjreader_new_subscription', 'jjreader_new_subscription' );
//add_action( 'wp_ajax_read_me_later', array( $this, 'jjreader_new_subscription' ) );
function jjreader_new_subscription($siteurl, $feedurl, $sitetitle, $feedtype){
	echo "test";
	$siteurl = $_POST['siteurl'];
	$feedurl = $_POST['feedurl'];
	$sitetitle = $_POST['sitetitle'];
	$feedtype = $_POST['feedtype'];
	
	
	jjreader_log("adding subscription: ". $feedurl. " @ ". $sitetitle);

	
	global $wpdb;
	/*if($time < 1){
		$time = time();
	}*/
	$table_name = $wpdb->prefix . "jjreader_following";
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE feedurl LIKE \"".$feedurl."\";")<1){
		jjreader_log("no duplicate found");
		$rows_affected = $wpdb->insert( $table_name,
			array(
				'added' => current_time( 'mysql' ), 
				'siteurl' => $siteurl,
				'feedurl' => $feedurl,
				'sitetitle'=> $sitetitle,
				'feedtype' => $feedtype,
			 ) );

		if($rows_affected == false){
		
			jjreader_log("Could not insert subscription info into database.");
			return "Could not insert subscription info into database.";
			die("Could not insert subscription info into database.");
		}else{
			jjreader_log("Success! Added subscription: ". $feedurl. " @ ". $sitetitle);
			return "Success! Added subscription: ". $feedurl. " @ ". $sitetitle;
		}
	}else{
		jjreader_log("This subscription already exists");
		return "This subscription already exists";
	}
	
	wp_die(); // this is required to terminate immediately and return a proper response
}



/* Functions to run upon installation */ 
register_activation_hook(__FILE__,'jjreader_create_tables');

/* Check if the database version has changed when plugin is updated */ 
add_action( 'plugins_loaded', 'jjreader_update_db_check' );

	
?>