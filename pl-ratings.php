<?php
/*
Plugin Name: Pegleg Ratings
Version: 1.0
Plugin URI: http://www.pegleg.com.au/pegleg-ratings/
Description: A YouTube style rating widget for posts. 
Author: B. Jordan
Author URI: http://www.pegleg.com.au/the-crew/

Copyright (c) 2009
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt

    This file is part of WordPress.
    WordPress is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Required for nonces.
require_once(ABSPATH .'wp-includes/pluggable.php'); 

// Change Log
$current_version = array('1.2.1');

// Database schema version
global $plr_db_version;
$plr_db_version = "1.0";

// Install the plugin.
function plr_activate() {

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	global $wpdb;

	// Create the pl_ratings table.

	$table_name = $wpdb->prefix . "pl_ratings";
	
	if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
	
		$sql = "CREATE TABLE " . $table_name . " (
			user_id int(9) NOT NULL,
			post_id int(9) NOT NULL,
		  	rating  varchar(10) NOT NULL,
		  	time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT plr_uk UNIQUE KEY (user_id, post_id)
		);";

		dbDelta( $sql );
	}
 
	add_option("plr_db_version", $plr_db_version);
}

// Hook for registering the install function upon plugin activation.
register_activation_hook(__FILE__,'plr_activate');

// Install the plugin.
function plr_deactivate() {

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	global $wpdb;

	// Drop the pl_ratings table.
	$table_name = $wpdb->prefix . "pl_ratings";
	$sql = "DROP TABLE IF EXISTS " . $table_name . ";";
	dbDelta( $sql );

	delete_option('plr_db_version');
}

// Hook for registering the uninstall function upon plugin deactivation.
register_deactivation_hook( __FILE__, 'plr_deactivate' );

function plr_set_rating( $args = '' ) { 

	global $wpdb;			

	// count the number of times the user has rated, excluding 
	// the rating from the filter.
	$filter = $args;
	unset( $filter['rating'] );

	$rating_count = plb_count_ratings( $filter );
	
	if ( $rating_count == 0 ) {
		plr_insert_rating( $args );
	} elseif ( $rating_count == 1 ) {
		plr_update_rating( $args );
	}
}

function plr_insert_rating( $args = '' ) {

	global $wpdb;

	$wpdb->insert( $wpdb->prefix . 'pl_ratings', 
		array( 'user_id' => mysql_real_escape_string($args['user_ID']),
			'post_id' => mysql_real_escape_string($args['post_ID']),
			'rating' => mysql_real_escape_string($args['rating'])), 
		array( '%d', '%d', '%s' ) );
}

function plr_update_rating( $args = '' ) { 

	global $wpdb;
	
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'pl_ratings' . ' SET rating = %s WHERE user_id = %d AND post_id = %d', mysql_real_escape_string($args['rating']), mysql_real_escape_string($args['user_ID']), mysql_real_escape_string($args['post_ID']) ) );

	$wpdb->show_errors();
}

function plb_count_ratings( $filter = '' ) {

	global $wpdb;
	
	$sql = 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'pl_ratings WHERE 1=1 ';
	
	// If a post ID was entered.
	if ( array_key_exists('post_ID', $filter) ) {
		$post_ID = $filter['post_ID'];
			
		// Append a condition to the SQL.
		$sql .= " AND post_id = $post_ID";
	}

	// If a user ID was entered.
	if ( array_key_exists('user_ID', $filter) ) {
		$user_ID = $filter['user_ID'];
			
		// Append a condition to the SQL.
		$sql .= " AND user_id = $user_ID";
	}

	// If a rating was entered.
	if ( array_key_exists('rating', $filter) ) {
		$rating = $filter['rating'];
			
		// Append a condition to the SQL.
		$sql .= " AND rating = '$rating'";
	}

	$rating_count = $wpdb->get_var( $wpdb->prepare( $sql, NULL ) );

	return $rating_count;
}

function plr_the_rating( $content ) {

	global $post;

	// If the user is logged in, display the rating control.
	if ( is_user_logged_in() ) {
 
		global $current_user;
		get_currentuserinfo();

		// count the ratings by the current user
		$userRatingCountUp = plb_count_ratings( array( 'post_ID' => $post->ID,
					'user_ID' => $current_user->ID,
					'rating' => 'up' ) );
		$userRatingCountDown = plb_count_ratings( array( 'post_ID' => $post->ID,
					'user_ID' => $current_user->ID,
					'rating' => 'down' ) );

		// Set the button class based on the current user's previous rating, if any
		$upClass   = ( $userRatingCountUp   == 1 ) ? 'rating-up-active' : 'rating-up-inactive';
		$downClass = ( $userRatingCountDown == 1 ) ? 'rating-down-active' : 'rating-down-inactive';

		$content = sprintf('%s
			<div class="rating-widget">
				<a id="rate-up-%s" class="rating-icon %s"></a>
				<a id="rate-down-%s" class="rating-icon %s"></a>
			</div> <!-- /rating-widget -->',
			$content,
			$post->ID,
			$upClass,
			$post->ID,
			$downClass
		);

	}

	// count the total ratings
	$ratingCountUp = plb_count_ratings( array( 'post_ID' => $post->ID,
		'rating' => 'up' ) );
	$ratingCountDown = plb_count_ratings( array( 'post_ID' => $post->ID,
		'rating' => 'down' ) );


	$content = sprintf('%s
		<div class="rating-count">
		Up votes: <span id="rating-count-up-%s" class="rating-count-up">%s</span>
		Down votes: <span id="rating-count-down-%s" class="rating-count-down">%s</span>
		</div>',
		$content,
		$post->ID,
		$ratingCountUp,
		$post->ID,
		$ratingCountDown
	);

	return $content;
}

add_filter('the_content', 'plr_the_rating');

// Link to Pegleg Ratings stylesheet and apply some custom styles
function plr_css() {
	echo "\n".'<link rel="stylesheet" href="'. WP_PLUGIN_URL . '/pegleg-ratings/ratings.css" type="text/css" media="screen" />'."\n";
}

add_action('wp_print_styles', 'plr_css'); // Pegleg Ratings stylesheet 

// embed the javascript file that makes the AJAX request
function plr_init() {
	if (!is_admin()) {

		wp_deregister_script('jquery');
		wp_register_script('jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js', false, '1.3.2', true);
		wp_enqueue_script('jquery');

		wp_enqueue_script( 'plr-ajax-request', plugin_dir_url( __FILE__ ) . 'ajax_rate.js', array('jquery'), '1.1', true );
 	
		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		wp_localize_script( 'plr-ajax-request', 'PeglegRatingsAjax', array( 
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'ratingNonce' => wp_create_nonce('rating-nonce')) );
	}
}

add_action('init', 'plr_init');

add_action( 'wp_ajax_plr-ajax-submit', 'plr_ajax_submit' );

function plr_ajax_submit() {

	global $current_user;
	get_currentuserinfo();

	if ( !is_user_logged_in() )
		return;

	// Verify the nonce.
	$nonce = $_REQUEST['ratingNonce'];
	if (! wp_verify_nonce( $nonce, 'rating-nonce' ) ) die("You bad.");

	// get the submitted parameters
	$args = array(  'user_ID' => mysql_real_escape_string($current_user->ID),
			'post_ID' => mysql_real_escape_string(intval( $_POST['postID'] )),
			'rating' => mysql_real_escape_string($_POST['rating'] ));

	// save the rating
	plr_set_rating( $args );

	// generate the response
	$response = json_encode( array( 'success'   => true, 
					'countup'   => plb_count_ratings( array( 'post_ID' => mysql_real_escape_string(intval($_POST['postID'])), 'rating' => 'up') ),
					'countdown' => plb_count_ratings( array( 'post_ID' => mysql_real_escape_string(intval($_POST['postID'])), 'rating' => 'down') ) ) );

	// response output
	header( "Content-Type: application/json" );
	echo $response;

	exit;
}