<?php
// Add a new subscription
add_action( 'wp_ajax_jjreader_response', 'jjreader_response' );

function jjreader_response (){
	/*
	$type = like, rsvp etc.
	$in-response-to
	$content
	$title
	
	*/
	$my_post = array(
		'post_title' => $_SESSION['booking-form-title'],
		'post_date' => $_SESSION['cal_startdate'],
		'post_content' => 'This is my post.',
		'post_status' => 'publish',
		'post_type' => 'booking',
	);
	$the_post_id = wp_insert_post( $my_post );
	__update_post_meta( $the_post_id, 'my-custom-field', 'my_custom_field_value' );
}


?>