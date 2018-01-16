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

/*
 MF2 Parser by Barnaby Walters: https://github.com/indieweb/php-mf2
*/


defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/* NOTE: If post kinds is installed, requiring Mf2 causes an error because it is already 
*  included. Therefore will need to add a check and only include if not already available
* (can enqueue do this?)
*/ 
 
// Require the mf2 parser only if it has not already been added by another plugin
if ( ! class_exists( 'Mf2\Parser' ) ) {
    require_once plugin_dir_path( __FILE__ ) .  'lib/Mf2/Parser.php'; // For parsing h-feed
} 
if ( ! class_exists( 'phpUri' ) ) {
	require_once plugin_dir_path( __FILE__ ) .  'lib/phpuri.php'; // For converting relative URIs to absolute 
}


global $jjreader_db_version;
$jjreader_db_version = "1.4"; // Updated database structure


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
	
function jjreader_install() {
	// Activates the plugin and checks for compatible version of WordPress 
	if ( version_compare( get_bloginfo( 'version' ), '2.9', '<' ) ) {
		deactivate_plugins ( basename( __FILE__ ));     // Deactivate plugin
		wp_die( "This plugin requires WordPress version 2.9 or higher." );
	}
		/*
		Determine wordpress version requirements using:
		https://de.wpseek.com/pluginfilecheck/
		*/ 

	//Set up cron job to check for posts
	if ( !wp_next_scheduled( 'jjreader_generate_hook' ) ) {            
		wp_schedule_event( time(), 'twentymins', 'jjreader_generate_hook' );
	}
	//Flush rewrite rules - see: https://codex.wordpress.org/Function_Reference/flush_rewrite_rules
	flush_rewrite_rules( false );
}
	

/* Create a new table for the reader settings */ 
function jjreader_create_tables() {
	global $wpdb;
	global $jjreader_db_version;
	
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
		feedid int DEFAULT 0 NOT NULL,
		sitetitle text DEFAULT '' NOT NULL,
		siteurl text DEFAULT '' NOT NULL,
		title text DEFAULT '' ,
		summary mediumtext DEFAULT '' ,
		content mediumtext DEFAULT '' NOT NULL,
		published datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		updated datetime DEFAULT '0000-00-00 00:00:00' ,
		authorname text DEFAULT '' NOT NULL,
		authorurl text DEFAULT '' NOT NULL,
		authoravurl text DEFAULT '' ,
		permalink text DEFAULT '' NOT NULL,
		location text DEFAULT '' ,
		photo text DEFAULT '' ,
		posttype text DEFAULT '' NOT NULL,
		viewed boolean DEFAULT FALSE NOT NULL,
		liked text DEFAULT '' ,
		replied text DEFAULT '' ,
		reposted text DEFAULT '' ,
		rsvped text DEFAULT '' ,
		PRIMARY KEY id (id)
	);";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	jjreader_log("Created jjreader_posts table");
	jjreader_log("updating db version from ".get_site_option('jjreader_db_version')." to ". $jjreader_db_version);
	update_option( 'jjreader_db_version', $jjreader_db_version );
	jjreader_log("updated db version to ". get_site_option('jjreader_db_version'));
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


	// Access databsae
	global $wpdb;
	// Start at post 0, show 15 posts per page
	$fpage=0;
	$length = 50;
	//$table_following = $wpdb->prefix . "jjreader_posts";
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'jjreader_posts` 
		ORDER BY  `published` DESC 
		LIMIT '.($fpage*$length).' , '.$length.';'
	);
	      
	?>
	<div id = "jjreader-feed-container">
	<?php
	//Iterate through all the posts in the database. Display the first 15 
	if ( !empty( $items ) ) { 
		foreach ( $items as $item ) {
			if ($item->posttype=="h-event"){
				$display_type = "Event";
			}

			//$the_feed = $wpdb->get_results( "SELECT * FROM ".$jjreader_following." WHERE id = \"".$feed_id."\";");
			?>
			<div class="jjreader-feed-item">
				<div class="jjreader-item-meta">		
					<?php
					echo '<a class="jjreader-item-authorname" href="'.$item->siteurl.'">'.$item->sitetitle.'</a> ';
					echo '<a class="jjreader-item-date" href="'.$item->permalink.'">at '.user_datetime($item->published).'</a>';
					echo '<span class="jjreader-item-type">'.$display_type.'</span>';
					?>
				</div>

				<div class="jjreader-item-content">
					<?php
					if ($item->title !=""){
						echo '<a class="jjreader-item-title" href="'.$item->permalink.'">'.$item->title.'</a>';
					}				
					echo $item->content;
					?>
				</div>
				
				<div class="jjreader-item-reponse">
					<?php jjreader_reply_actions($item->posttype); ?>
				</div>


			</div><!--.jjreader-feed-item-->
			<?php
		}
	}
	?>
	
	
	</div>
	<?php

}

// Show interface for adding/removing/editing subscriptions
function jjreader_subscription_editor(){
	?>
	<button id="jjreader-button-refresh" class="ui-state-default ui-corner-all" title=".ui-icon-arrowrefresh-1-e">Refresh feed<span class="ui-icon ui-icon-arrowrefresh-1-e"></span></button>
	<button id="jjreader-button-addSite" class="ui-button ui-corner-all ui-widget">Add Subscription</button>
	<div id="jjreader-addSite-form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<strong>Add a subscription</strong><br>
        <label for="jjreader-siteurl">Site URL </label><input type="text" name="jjreader-siteurl" value="" size="30"><br>
        <button id="jjreader-addSite-findFeeds" class="ui-button ui-corner-all ui-widget">Find feeds & title</button>
		<br><br>
		<form class="jjreader-feedpicker jjreader-hidden "></form
		<label for="jjreader-feedurl">Feed URL </label><input type="text" name="jjreader-feedurl" value="" size="30"><br>
		<label for="jjreader-sitetitle">Site Title </label><input type="text" name="jjreader-sitetitle" value="" size="30"><br>
		<div>Feed type:<span class="jjreader-feed-type"></span></div>
		<button id="jjreader-addSite-submit" class="ui-button ui-corner-all ui-widget">Submit</button>
	</div>
	<?php
}

// Show reply actions if the user has permission to create posts
function jjreader_reply_actions($post_type){

	// Check if post_kinds plugin is installed (https://wordpress.org/plugins/indieweb-post-kinds/)
	// If so, set post_kinds to true, if not, set to false
	if ( in_array( 'indieweb-post-kinds/indieweb-post-kinds.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		$post_kinds = True;
	} else {
		$post_kinds = False;
	} 
	
	if(current_user_can( 'publish_posts')){
		?>
		<button class="jjreader-like ui-button ui-corner-all ui-widget"><span class="ui-icon ui-icon-heart"></span>Like</button>

		<button class="jjreader-reply ui-button ui-corner-all ui-widget"><span class="ui-icon ui-icon-arrowreturnthick-1-w"></span>Reply</button>
		<?php 
		if ($post_type=="h-event"){
			?>
		<span class ="jjreader-rsvp-buttons">RSVP: 
			<button class="jjreader-rsvp-yes ui-button ui-corner-all ui-widget">Yes</button>
			<button class="jjreader-rsvp-no ui-button ui-corner-all ui-widget">No</button>
			<button class="jjreader-rsvp-interested ui-button ui-corner-all ui-widget">Interested</button>
			<button class="jjreader-rsvp-yes ui-button ui-corner-all ui-widget">Maybe</button>
		</span>
		<?php } ?>
		<div class="jjreader-reply-input jjreader-hidden">
			<input class ="jjreader-reply-title" placeholder = "Enter a reply title (if desired)"></input>
			<textarea class ="jjreader-reply-text" placeholder="Enter your reply here" ></textarea>
			<button class="jjreader-reply-submit ui-button ui-corner-all ui-widget"><span class="ui-icon"></span>Submit</button>
		</div>

		<?php
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

/* 
** Remove titles for posts where the title is equal to the content (e.g. notes, asides, microblogs)
****
**** In many rss feeds and h-feeds, the only indication of whether a title is redunant is that it duplicates 
****
*/
function clean_the_title($title,$content,$content_plain=''){
	//jjreader_log("content vs title");
	//jjreader_log("content = ".$content);
	//jjreader_log("title = ".$title);

	$clean_title = html_entity_decode($title); // First convert html entities to text (to ensure consistent comparision)
	$clean_title = strip_tags(rtrim($clean_title,".")); // remove trailing "..."
	$clean_title = strip_tags(trim($clean_title)); // remove white space on either side
	$clean_title = htmlentities($clean_title, ENT_QUOTES); // Convert quotation marks to HTML entities
	$clean_title = str_replace(array("\r", "\n"), '', $clean_title); // remove line breaks from title
	$clean_title = str_replace("&nbsp;", "", $clean_title); // replace $nbsp; with a space character
	$clean_title = str_replace(array("\r", "\n"), '', $clean_title); // remove line breaks from title
	
	$clean_content = html_entity_decode($content); // First convert html entities to text (to ensure consistent comparision)
	$clean_content = strip_tags(rtrim($clean_content,".")); // remove trailing "..."
	$clean_content = strip_tags(trim($clean_content)); // remove white space on either side
	$clean_content = htmlentities($clean_content, ENT_QUOTES); // Convert quotation marks to HTML entities
	$clean_content = str_replace("&nbsp;", "", $clean_content); // replace $nbsp; with a space character
	$clean_content = str_replace(array("\r", "\n"), '', $clean_content); // remove line breaks from CONTENT
	//jjreader_log("clean_content vs clean_title");
	//jjreader_log("clean_content = " .$clean_content);
	//jjreader_log("clearn_title = " . $clean_title);
	if (strpos($clean_content,$clean_title)===0 ){
		$title="";
	} 

	// Also compare to content_plain if it exists.  ($content_plain is a plain text version, whereas $content has html)
	if ($content_plain != ''){
		$clean_content = html_entity_decode($content_plain); // First convert html entities to text (to ensure consistent comparision)
		$clean_content = strip_tags(rtrim($clean_content,".")); // remove trailing "..."
		$clean_content = strip_tags(trim($clean_content)); // remove white space on either side
		$clean_content = htmlentities($clean_content, ENT_QUOTES); // Convert quotation marks to HTML entities
		$clean_content = str_replace("&nbsp;", "", $clean_content); // replace $nbsp; with a space character
		$clean_content = str_replace(array("\r", "\n"), '', $clean_content); // remove line breaks from CONTENT
		//jjreader_log("COMPARISON #2: plain content: [". $clean_content . "] and title: [".$clean_title."]");
		if (strpos($clean_content,$clean_title)===0 ){
			$title="";
		} 
	}
	return $title;
}
/*
** Add a post to the jjreader_posts table in the database
*/
function add_reader_post($feedid,$title,$summary,$content,$published=0,$updated=0,$authorname='',$authorurl='',$avurl='',$permalink,$location,$photo,$type,$siteurl,$sitetitle){


	jjreader_log("adding post: ".$permalink.": ".$title);
	global $wpdb;
	//jjreader_log("published = " . $published);
	if($published < 1){
		$published = time();
	}
	//jjreader_log("published2 = " . $published);
	$published = date('Y-m-d H:i:s',$published);
	//jjreader_log("published3 = " . $published);
	$updated = date('Y-m-d H:i:s',$updated);


	
	//If the author url is not known, then just use the site url
	if (empty($authorurl)){$authorurl = $siteurl;}
	// If the content begins with the title, then the post is probably an aside, so 
	// the title should be dropped. 	
	
	
	// Add the post (if it doesn't already exist)
	$table_name = $wpdb->prefix . "jjreader_posts";
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE permalink LIKE \"".$permalink."\";")<1){

		$rows_affected = $wpdb->insert( $table_name,
			array(	
				'feedid'=>$feedid,
				'sitetitle'=>$sitetitle,
				'siteurl'=>$siteurl,
				'title' => $title,
				'summary'=> $summary,
				'content' => $content,
				'published'=> $published,
				'updated'=> $updated,
				//'published'=> UTC_datetime($published),
				//'updated'=> UTC_datetime($updated),
				'authorname' => $authorname,
				'authorurl' => $authorurl,
				'authoravurl' => $avurl,
				'permalink' => $permalink,
				'location'=> $location,
				'photo'=> $photo,
				'posttype' => $type,
				'viewed' =>false,
			 ) );

		if($rows_affected == false){
			jjreader_log("could not insert post into database!");
			die("could not insert post into database!" .$permalink.": ".$title);
		}else{
			jjreader_log("added ".$permalink.": ".$title);
		}
	}else{
		jjreader_log("post already exists: " .$permalink.": ".$title);
	}	
}

/*
** Add a new subscription
*/ 
add_action( 'wp_ajax_jjreader_new_subscription', 'jjreader_new_subscription' );
//add_action( 'wp_ajax_read_me_later', array( $this, 'jjreader_new_subscription' ) );
function jjreader_new_subscription($siteurl, $feedurl, $sitetitle, $feedtype){
	jjreader_log("adding a new subscription");
	$siteurl = $_POST['siteurl'];
	$feedurl = $_POST['feedurl'];
	$sitetitle = $_POST['sitetitle'];
	$feedtype = $_POST['feedtype'];
	jjreader_log("adding subscription: ". $feedurl. " @ ". $sitetitle);
	
	global $wpdb;
	$table_name = $wpdb->prefix . "jjreader_following";
	// Check if the site is already subscribed
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
			echo "Could not insert subscription info into database.";
			die("Could not insert subscription info into database.");
		}else{
			jjreader_log("Success! Added subscription: ". $feedurl. " @ ". $sitetitle);
			echo "Success! Added subscription: ". $feedurl. " @ ". $sitetitle;
		}
	}else{
		jjreader_log("You are already subscribed to " . $feedurl);
		echo "You are already subscribed to " . $feedurl;
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Post a response to an item from the feed
*/
add_action( 'wp_ajax_jjreader_response', 'jjreader_response' );
function jjreader_response ($response_type, $in_reply_to, $reply_to_title, $reply_to_content, $title, $content){	
	$response_type = $_POST['response_type'];
	$in_reply_to = $_POST['in_reply_to'];
	$reply_to_title = $_POST['reply_to_title'];
	$reply_to_content = $_POST['reply_to_content'];
	$post_title = $_POST['title'];
	$post_content = $_POST['content'];


	jjreader_log("Response: " . $response_type . " — " . $in_reply_to);
	jjreader_log("[".$response_type."]");

	jjreader_log("post_title: ". $post_title);
	jjreader_log("post_content: ". $post_content);

	//If the post has a title, then we will use the title for display. If not, use the url
	// for display
	if ($reply_to_title){
		$display_title = $reply_to_title;
	} else {
		$display_title = $in_reply_to;
	}
	$attribution = '<em><a href="'.$in_reply_to.'">'.$display_title.'</a></em>';

	if ($response_type == "reply" ){
		jjreader_log("response type = reply");
		$content = "Reply to " . $attribution . "<br><br>"; 
		$content .= $post_content;
		$title = $post_title;
		if (strlen($title)>0){
			//If the reply has a title, it's post kind is 'post'
			$post_type = "post";
			$post_kind = "article";
		} else {
			//If the reply does not have a title, it's post kind is 'aside'	
			$post_type = "aside";
			$post_kind = "note";		
		}
	} elseif ($response_type == "like"){
		$content = "Liked " . $attribution;
		$title = "Liked ". $display_title;
		$post_type = "link";
		$post_kind = "like";
	} 

	//jjreader_log("title: ". $title);
	//jjreader_log("content: ". $content);

	jjreader_log("posting response");
	$my_post = array(
		'post_title' => $title,
		'post_content' => $content,
		'post_status' => 'draft',
	);
	$the_post_id = wp_insert_post( $my_post );
	jjreader_log(" response posted: " . $the_post_id);
	//__update_post_meta( $the_post_id, 'my-custom-field', 'my_custom_field_value' );
	
	// Set the post format once the post has been created 
	set_post_format( $the_post_id , $post_type);
	
	// If the post kinds plugin is installed, set the post kind
	if (function_exists('set_post_kind')){
		/* Assign a kind to a post
		 	This uses the set_post_kind function, which is part of the post kinds plugin. 
			Do this in the form "set_post_kind( $post, $kind)"
				 See below for notes:
				 * @param int|object $post The post for which to assign a kind.
				 * @param string     $kind A kind to assign. Using an empty string or array will default to note.
				 * @return mixed WP_Error on error. Array of affected term IDs on success.
				 
			//function set_post_kind( $post, $kind ) {
			//   return Kind_Taxonomy::set_post_kind( $post, $kind );
			//	}
		*/ 
 		set_post_kind( $the_post_id , $post_kind);
	}
	
	echo $the_post_id;
	wp_die($the_post_id); // this is required to terminate immediately and return a proper response
}

/*
** Identify and return feeds at a given url 
*/
add_action( 'wp_ajax_jjreader_findFeeds', 'jjreader_findFeeds' );
//add_action( 'wp_ajax_read_me_later', array( $this, 'jjreader_new_subscription' ) );
function jjreader_findFeeds($siteurl){
	$siteurl = $_POST['siteurl'];
	jjreader_log("Searching for feeds and site title at ". $siteurl);

	$html = file_get_contents($siteurl); //get the html returned from the following url
	$dom = new DOMDocument();
	libxml_use_internal_errors(TRUE); //disable libxml errors
	if(!empty($html)){ //if any html is actually returned
		$dom->loadHTML($html);
		$thetitle = $dom->getElementsByTagName("title");
		//echo $thetitle[0]->nodeValue;
		$returnArray[] = array("type"=>"title", "data"=>$thetitle[0]->nodeValue);
		
		
		$website_links = $dom->getElementsByTagName("link");
		// Check for feeds as <link> elements
		$found_hfeed = FALSE; 

		if($website_links->length > 0){
			foreach($website_links as $row){
				if (isRSS($row->getAttribute("type"))){
					// Convert relative feed URL to absolute URL if needed
					$feedurl = phpUri::parse($siteurl)->join($row->getAttribute("href"));
					//Return the feed type and absolute feed url 
					$returnArray[] = array("type"=>$row->getAttribute("type"), "data"=>$feedurl);
				}
				/*if ($row->getAttribute("type")=='application/rss+xml'||
					$row->getAttribute("type")=='application/atom+xml'||
					$row->getAttribute("type")=='text/xml' ) {

				}*/
				elseif($row->getAttribute("type")=='text/html'){
					$returnArray[] = array("type"=>"h-feed", "data"=>$feedurl);
					$found_hfeed = TRUE; 
				}
			}
		}

		// Also here check for h-feed in the actual html
			//$html = '<a class="h-card" href="https://waterpigs.co.uk/">Barnaby Walters</a>';
			//$mf = Mf2\fetch($siteurl);
			$mf = Mf2\parse($html,$siteurl);
			$output_log ="Output: <br>";

			foreach ($mf['items'] as $mf_item) {
				if ($found_hfeed == FALSE) {
					$output_log .= "A {$mf_item['type'][0]} called {$mf_item['properties']['name'][0]}<br>";
					if ("{$mf_item['type'][0]}"=="h-feed"||  // check 1
						"{$mf_item['type'][0]}"=="h-entry"){
						//Found an h-feed (probably)
						$returnArray[] = array("type"=>"h-feed", "data"=>$siteurl);
						$found_hfeed = TRUE; 
					} else {
						$output_log .="Searching children... <br>";

						foreach($mf_item['children'] as $child){
							if ($found_hfeed == FALSE) {
								$output_log .= "A CHILD {$child['type'][0]} called {$child['properties']['name'][0]}<br>";
								if ("{$child['type'][0]}"=="h-feed"|| // check 1
									"{$child['type'][0]}"=="h-entry"){
									//Found an h-feed (probably)
									$returnArray[] = array("type"=>"h-feed", "data"=>$siteurl);
									$found_hfeed = TRUE; 
								}
							}
						}
					}
				}

			}
			jjreader_log($output_log);

		echo json_encode($returnArray);
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}





/*
** Defines the interval for the cron job (5 minutes) 
*/
function jjreader_cron_definer($schedules){
	$schedules['fivemins'] = array(
		'interval'=> 300,
		'display'=>  __('Once Every 5 Minutes')
	);

	$schedules['twentymins'] = array(
		'interval'=> 1200,
		'display'=>  __('Once Every 5 Minutes')
	);
	return $schedules;
}

/*
** Aggregator function (run using cron job or by refresh button)
*/
add_action( 'wp_ajax_jjreader_aggregator', 'jjreader_aggregator' );
function jjreader_aggregator() {
	jjreader_log("aggregator was run");
	global $wpdb;
	$table_following = $wpdb->prefix . "jjreader_following";
	//Iterate through each item in the 'following' table.
	foreach( $wpdb->get_results("SELECT * FROM ".$table_following.";") as $key => $row) {
		$feedurl = $row->feedurl;
		$feedtype = $row->feedtype;
		$sitetitle = $row->sitetitle;
		$siteurl = $row->siteurl;
		$feedid = $row->id;
		jjreader_log("checking for new posts in ". $feedurl);

		/****   RSS FEEDS ****/
		if (isRss($feedtype)){
			jjreader_log ($feedurl . " is an rss feed (or atom, etc)");

			$feed = jjreader_fetch_feed($feedurl,$feedtype);
		
			if(is_wp_error($feed)){
				jjreader_log($feed->get_error_message());
				trigger_error($feed->get_error_message());
				jjreader_log("Feed read Error: ".$feed->get_error_message());
			} else {
				jjreader_log("Feed read success.");
			}
			$feed->enable_cache(false);
			$feed->strip_htmltags(false);   
			$items = $feed->get_items();
			usort($items,'date_sort');
			foreach ($items as $item){
				//jjreader_log("got ".$item->get_title()." from ". $item->get_feed()->get_title()."<br/>");
				$title = $item->get_title();


				$summary = html_entity_decode ($item->get_description());
				$content = html_entity_decode ($item->get_content());
				//ORIGINAL $published=$item->get_date("U");
				$published=$item->get_date('U');

				$updated=0;
				//Remove the title if it is equal to the post content (e.g. asides, notes, microblogs)
				$title = clean_the_title($title,$content);
				// Several fallback options to set author name/site title
				$authorname = $sitetitle;  // This uses the site title entered by the user (not the site title specified in the site feed)
				$avurl='';
				$permalink=$item->get_permalink();
				$location='';
				$photo='';//$item->get_image_tags();
				$type='rss';
				try{
					add_reader_post($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle);
				}catch(Exception $e){
					jjreader_log("Exception occured: ".$e->getMessage());
				}
			}

		} /****  H-FEEDS ****/ 
		elseif ($feedtype == "h-feed"){
			$feed = jjreader_fetch_hfeed($feedurl,$feedtype);
			foreach ($feed as $item){
				
				$title = $item['name'];
				$summary=$item['summary'];
				$content=$item['content'];
				$published = $item['published'];
				$updated = $item['updated'];
				$authorname = $item['author'];
				$authorurl = ""; // none for now — TO DO: Fetch avatar from h-card if possible, or just use siteurl
				$avurl = ""; // none for now — TO DO: Fetch avatar from h-card if possible
				$permalink = $item['url'];
				$location='';
				$photo='';
				//$location = $item['location'];
				//$photo = $item['photo'];
				$siteurl=$item['siteurl'];
				$feedurl = $url;
				$type = $item['type'];
				

				try{
					add_reader_post($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle);
					//add_reader_post($permalink, $title, $content, $authorname, $authorurl, $time, $avurl, $siteurl, $feedurl, $type);
				}catch(Exception $e){
					jjreader_log("Exception occured: ".$e->getMessage());
				}
			}
			jjreader_log ($feedurl . " is an h-feed");
		}
		remove_filter( 'wp_feed_cache_transient_lifetime', 'jjreader_feed_time' );
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Fetch an RSS FEED and return its content
*/
function jjreader_fetch_feed($url,$feedtype) {
	require_once (ABSPATH . WPINC . '/class-feed.php');
	$feed = new SimplePie();
	//jjreader_log("Url is fetchable");
	$feed->set_feed_url($url);
	$feed->set_cache_class('WP_Feed_Cache');
	$feed->set_file_class('WP_SimplePie_File');
	$feed->set_cache_duration(30);
	$feed->enable_cache(false);
	$feed->set_useragent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.77 Safari/535.7');//some people don't like us if we're not a real boy	
	$feed->init();
	$feed->handle_content_type();
	
	//jjreader_log("Feed:".print_r($feed,true));

	if ( $feed->error() )
		$errstring = implode("\n",$feed->error());
		//if(strlen($errstring) >0){ $errstring = $feed['data']['error'];}
		if(stristr($errstring,"XML error")){
			jjreader_log('simplepie-error-malfomed: '.$errstring.'<br/><code>'.htmlspecialchars ($url).'</code>');
		}elseif(strlen($errstring) >0){
			jjreader_log('simplepie-error: '.$errstring);
		}else{
			//jjreader_log('simplepie-error-empty: '.print_r($feed,true).'<br/><code>'.htmlspecialchars ($url).'</code>');
		}
	return $feed;
}

/*
** Fetch an H-FEED and return its content
*/
function jjreader_fetch_hfeed($url,$feedtype) {
	//Parse microformats at the feed-url
	$mf = Mf2\fetch($url);
	//Identify the h-feed within the parsed MF2
	$hfeed = "";
	$hfeed_path = "children"; // (default) in most cases, items within the h-feed will be 'children' 
	//Check if one of the top-level items is an h-feed
	foreach ($mf['items'] as $mf_item) {
		if ($hfeed == "") {
			if ("{$mf_item['type'][0]}"=="h-feed"){
				$hfeed = $mf_item;	
				jjreader_log("h-feed found: 1");
			
			} else {
				//If h-feed has not been found, check for a child-level h-feed
				foreach($mf_item['children'] as $child){
					if ($hfeed == "") {
						if ("{$child['type'][0]}"=="h-feed"){
							$hfeed = $child;	
							jjreader_log("h-feed found: 2");			
						}
					}
				}
			}
		}
	}
	//If no h-feed was found, check for h-entries. If h-entry is found, then consider its parent the h-feed
	foreach ($mf['items'] as $mf_item) {
		if ($hfeed == "") {
			if ("{$mf_item['type'][0]}"=="h-entry"){
				$hfeed = $mf;		
				$hfeed_path	="items";
				jjreader_log("h-feed found: 3");
			} else {
				//If h-entries have not been found, check for a child-level h-entry
				foreach($mf_item['children'] as $child){
					if ($hfeed == "") {
						if ("{$child['type'][0]}"=="h-entry"){
							$hfeed = $mf_item;		
							jjreader_log("h-feed found: 4");		
						}
					}
				}
			}
		}
	}

	//At this point, only proceed if h-feed has been found
	if ($hfeed == "") {
		//do nothing
		jjreader_log("no h-feed found");
	} else {
		jjreader_log("Parsing h-feed at: ".$hpath);
		$site_url = $url;
		$counter = 0;

		foreach ($hfeed[$hfeed_path] as $item) {
			$counter ++;
			jjreader_log("parsing h-feed item #".$counter.": "."{$item['properties']['name'][0]}");
			$item_name = "{$item['properties']['name'][0]}";
			$item_type = "{$item['type'][0]}";
			$item_summary = "{$item['properties']['summary'][0]}";
			$item_published = strtotime("{$item['properties']['published'][0]}");
			jjreader_log("published date = " .$item_published);
			//gmdate('d.m.Y H:i:s',strtotime($datetime)));
			//$item_published = UTC_datetime($item_published);
			$item_updated = strtotime("{$item['properties']['updated'][0]}");
			//$item_updated = UTC_datetime($item_updated);
			$item_location = json_encode("{$item['properties']['location'][0]}");
				//Note that location can be an h-card
			$item_url = "{$item['properties']['url'][0]}";
			$item_uid = "{$item['properties']['uid'][0]}";
			$item_syndication = json_encode("{$item['properties']['syndication'][0]}");
			$item_inreplyto = "{$item['properties']['in-reply-to'][0]}";
			$item_author = "{$item['properties']['author'][0]}";

				$log_entry = "<ol><li>item_name = ". $item_name ."</li>";
				$log_entry .= "<li>item_type = ". $item_type ."</li>";
				$log_entry .= "<li>item_summary = ". $item_summary ."</li>";
				$log_entry .= "<li>item_published = ". $item_published ."</li>";
				$log_entry .= "<li>item_updated = ". $item_updated ."</li>";
				$log_entry .= "<li>item_location = ". $item_location ."</li>";
				$log_entry .= "<li>item_url = ". $item_url ."</li>";
				$log_entry .= "<li>item_uid = ". $item_uid ."</li>";
				$log_entry .= "<li>item_syndication = ". $item_syndication ."</li>";
				$log_entry .= "<li>item_inreplyto = ". $item_inreplyto ."</li>";
				$log_entry .= "<li>item_author = ". $item_author ."</li>";


			//handle h-entry
			if ("{$item['type'][0]}" == "h-entry"){
				//jjreader_log ("found h-entry");
				//jjreader_log("{$item['properties']['url'][0]}");
				$item_content = "{$item['properties']['content'][0]['html']}";
				$item_content_plain = "{$item['properties']['content'][0]['value']}";
				$log_entry .= "<li>item_content = ". $item_content ."</li>";
				$log_entry .= "<li>item_content_plain = ". $item_content_plain ."</li>";

			}

			//handle h-event
			if ("{$item['type'][0]}" == "h-event"){
				//jjreader_log ("found h-event");
				//jjreader_log("{$item['properties']['url'][0]}");

				$item_featured = "{$item['properties']['featured'][0]}";
				$item_content = "";
				//When
				//Where    //Note that location can be an h-card
				//Host
				//Summary
				//Image
			}

			// Log the parsed h-feed  for debugging
			jjreader_log($log_entry);


			//Remove the title if it is equal to the post content (e.g. asides, notes, microblogs)
				$item_name = clean_the_title($item_name,$item_content,$item_content_plain);

			$hfeed_items [] = array (
				"name"=>$item_name,
				"type"=>$item_type,
				"summary"=>$item_summary,
				"content"=>$item_content,
				"location"=>$item_location,
				"published" =>$item_published,
				"updated" =>$item_updated,
				"url"=>$item_url,
				"uid"=>$item_url,
				"author"=>$item_author,
				"syndication"=>$item_syndication,
				"in-reply-to"=>$item_inreplyto,
				"author"=>$item_author,
				"featured"=>$item_featured,
				"siteurl"=>$site_url
			);
		}

		jjreader_log("hfeed items = " . json_decode($hfeed_items));

		return $hfeed_items;
	}
	return "h-feed found";
}



/* 
** Returns true is the feed is of type rss 
*/

function isRSS($feedtype){
	$rssTypes = array ('application/rss+xml','application/atom+xml','application/rdf+xml','application/xml','text/xml','text/xml','text/rss+xml','text/atom+xml');
    if (in_array($feedtype,$rssTypes)){
    	return True;
    }
}


/* 
** Returns a datetime formatted with user's preferences (for timezone, date format, & time format)
*/

function User_datetime($datetime){
	$output_log = "Converting datetime... \n";
	$user_datetime_format = get_option('date_format') . " " . get_option('time_format');
	$user_datetime = get_date_from_gmt($datetime, $user_datetime_format);
	return $user_datetime ;
}



/*
** Runs upon deactivating the plugin
*/
function jjreader_deactivate() {
	// on deactivation remove the cron job 
	if ( wp_next_scheduled( 'jjreader_generate_hook' ) ) {
		wp_clear_scheduled_hook( 'jjreader_generate_hook' );
	}
	jjreader_log("deactivated plugin");
}


/* Functions to run upon installation */ 
register_activation_hook(__FILE__,'jjreader_install');
register_activation_hook(__FILE__,'jjreader_create_tables');
add_filter('cron_schedules','jjreader_cron_definer');
add_action( 'jjreader_generate_hook', 'jjreader_aggregator' );

/* Functions to run upon deactivation */ 
register_deactivation_hook( __FILE__, 'jjreader_deactivate' );



/* Check if the database version has changed when plugin is updated */ 
add_action( 'plugins_loaded', 'jjreader_update_db_check' );

/* Hook to display admin notice */ 
add_action( 'admin_notices', 'initial_setup_admin_notice' );
	
?>