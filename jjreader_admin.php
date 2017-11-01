<?php 
	/* Display plugin information on the admin screen */ 
	


    if($_POST['jjreader_hidden'] == 'Y') {
    	// Store the old feed location for reference
    	$old_feedlocation = get_option('jjreader_feedlocation');
        //Send form data
        $feedlocation = $_POST['jjreader_feedlocation'];
        update_option('jjreader_feedlocation', $feedlocation);
        // Create rename/following page if the feedlocation has changed
        if ($old_feedlocation != $feedlocation){
	        create_following_page();
	    }
        ?>
        <div class="updated"><p><strong><?php _e('Options saved.' ); ?></strong></p></div>
        <?php
    } else {
        $feedlocation = get_option('jjreader_feedlocation');
        //Normal page display
    }
?>



<div class="wrap">
    <?php    
    	echo "<h2>" . __( 'JJ Reader Settings', 'jjreader_feedlocation' ) . "</h2>"; 
    ?>
    <div> <strong>Database version: </strong>  <?php echo get_option( "jjreader_db_version" ); ?></div>

    
 
    <form name="jjreader-form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
        <input type="hidden" name="jjreader_hidden" value="Y">
        <?php    echo "<h4>" . __( 'Feed location:', 'jjreader_feedlocation' ) . "</h4>"; ?>
        <p><?php _e("'Following' page: " ); ?><?php echo site_url(); ?>/ <input type="text" name="jjreader_feedlocation" value="<?php echo $old_feedlocation; ?>" size="30"><?php _e(" e.g., following" ); ?></p>
         
     
        <p class="submit">
        <input type="submit" name="Submit" value="<?php _e('Update Options', 'jjreader_feedlocation' ) ?>" />
        </p>
    </form>
</div>

