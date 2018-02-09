<?php 
	/* Display plugin information on the admin screen */ 
	
    defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

?>


<div id = "yarns_reader-admin">


<div class="wrap">
    <?php    
    	echo "<h2> Yarns Indie Reader </h2>"; 
    ?>
    <div>
        <p>Thank you for installing Yarns Indie Reader!<p>
        <p><strong>Getting started:</strong> To use Yarns Indie Reader, create a page with the content: <strong>[yarns_indie_reader]</strong> <br>

            

            When you view that page, you will see the reader interface.  From there you can subscribe to blogs and websites, and post replies. 
            
            <br><br>

            Please note that you must be logged in to your site to view the reader, and must have author, editor, or admin permissions to modify subscriptions or post replies.



            <p>

    	
        <p>Questions? Please contact Jack Jamieson (<a href="http://jackjamieson.net" target="_blank"> jackjamieson.net</a>)</p>


    </div>
    <div> <strong>Database version: </strong>  <?php echo get_option( "yarns_reader_db_version" ); ?></div>
</div>

<h2> Most recent 100 items from log </h2>

<div class = "yarns_reader-log">
<?php 

	// Access databsae
	global $wpdb;
	// Start at post 0, show 15 posts per page
	$fpage=0;
	$length = 100;

	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'yarns_reader_log` 
		ORDER BY  `ID` DESC 
		LIMIT '.($fpage*$length).' , '.$length.';'
	);
	
	
	if ( !empty( $items ) ) { 
		foreach ( $items as $item ) {
			echo '<div class="yarns_reader-log-item">';
            echo '<span class="yarns_reader-log-item-time">'.$item->date.'</span>';
            echo '<span class="yarns_reader-log-item-content">'.$item->log.'</span>';
			echo '</div>'; 
		}
	}
	
	

?>

</div>

</div><!--#yarns_reader-admin-->