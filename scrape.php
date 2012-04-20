<?php

// Script can only be called from command line
if( ! defined( 'STDIN' ) )
	die( 'This script can only be called from the command line.' );

// Notes:
// * Go back and add Genus
// * Go back and add height/spread
// * Go back and add hardiness

echo "Init\n";

// specify host or domain (needed for wp-includes/ms-settings.php:100)
$_SERVER[ 'HTTP_HOST' ] = 'plants.carolynwillitts.co.uk';

// location of wp-load.php so we have access to database and $wpdb object
require '../wp-load.php';

// Select the plants blog
switch_to_blog( 6 ); // Must match the faked HTTP_HOST value

require_once( ABSPATH . '/wp-admin/includes/file.php' );
require_once( ABSPATH . '/wp-admin/includes/image.php' );
require_once( ABSPATH . '/wp-admin/includes/media.php' );

// Tell the user which DB we're working with
global $wpdb;
echo "Working on blog prefix $wpdb->prefix, which is called " . get_bloginfo() . "\n";
error_reporting(E_ALL ^ E_NOTICE);

require 'libs/simple_html_dom/simple_html_dom.php';

function str_starts_with( $haystack, $needle ) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

/**
 * undocumented function
 *
 * @param  
 * @return void
 **/
function get_dom_for_url( $url ) {
	echo "Get $url\n";
	if ( is_wp_error( $response = wp_remote_get( $url, array( 'timeout' => 30 ) ) ) ) {
		echo "Problem retrieving URL: $url – " . $response->get_error_message();
		return false;
	}
	sleep( 2 );
	$dom = new simple_html_dom();
	$dom->load( $response[ 'body' ] );
	return $dom;
}

/**
 * undocumented function
 *
 * @param  
 * @return void
 **/
function crocus_clean_attr( $prefix = false, $suffix = false, $str ) {
	
	if ( $prefix )
		$str = preg_replace( '/^' . $prefix . '/', '', $str );
	if ( $suffix )
		$str = preg_replace( '/' . $suffix . '$/', '', $str );
	$lines = preg_split('/\r\n|\r|\n/', $str, 2);
	$str = $lines[ 0 ];
	$str = trim( $str );
	return $str;
}

/**
 * undocumented function
 *
 * @param  
 * @return void
 **/
function process_collections() {
	
}

/**
 * undocumented function
 *
 * @param  
 * @return void
 **/
function process_collection( $collection_code, $tax, $term ) {
	global $page_urls, $plants;

	$page_urls = array();
	$content = file_get_contents( dirname( __FILE__ ) . "/dats/plants.dat", $serialised );
	if ( $content )
		$plants = unserialize( $content );
	else
		$plants = array();

	$i = 1;
	// Loop over there results pages, getting each plant URL and some basic information
	while ( true ) {
		$page_url = "http://www.crocus.co.uk/plants/_/vid.$collection_code/start.{$i}/sort.1/";
		if ( ! $dom = get_dom_for_url( $page_url ) ) {
			echo( "Didn't get $page_url \n" );
			continue;
		}
		foreach ( $dom->find( '#results_plants a.more-details' ) as $card ) {
			$plant_href = 'http://www.crocus.co.uk' . $card->href;
			if ( ! isset( $plants[ $plant_href ] ) ) {
				$plants[ $plant_href ] = array( 'taxonomies' => array() );
			}
			if ( ! isset( $plants[ $plant_href ][ $tax ] ) )
				$plants[ $plant_href ][ 'taxonomies' ][ $tax ] = array( $term );
			else
				$plants[ $plant_href ][ 'taxonomies' ][ $tax ][] = $term;
			if ( ! $plants[ $plant_href ][ 'latin-name' ] )
				$plants[ $plant_href ][ 'latin-name' ] = $card->find( '.name', 0 )->plaintext;
			if ( ! $plants[ $plant_href ][ 'excerpt' ] )
				$plants[ $plant_href ][ 'excerpt' ] = $card->find( '.description', 0 )->plaintext;
			echo "Got plant " . $plants[ $plant_href ][ 'latin-name' ] . " @ $plant_href \n";
		}
		// Check for a next link, if there's none then bail
		if ( ! $dom->find( '#results_plants .pagination li.next' ) ) {
			echo "Completed gathering for $term \n";
			break;
		}
		$i++;
	}
	
	$serialised = serialize( $plants );
	file_put_contents( dirname( __FILE__ ) . "/dats/plants.dat", $serialised );

	echo " Got " . count( $plants ) . " plants \n";

	unset( $dom );
}

/**
 * undocumented function
 *
 * @param  
 * @return void
 **/
function scrape() {
	global $page_urls, $plants, $wpdb;

	echo "Loading plants \n";

	$content = file_get_contents( dirname( __FILE__ ) . "/dats/plants.dat", $serialised );
	$plants = unserialize( $content );

	echo "Scraping " . count( $plants ) . " plants \n";

	$pnum = 0;
	foreach ( $plants as $plant_url => & $plant ) {
		$pnum++;
		echo "[$pnum/" . count( $plants ) . "] Get $plant_url\n";

		unset( $dom );
		$dom = false;

		$sql = " SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_plant_url' AND meta_value = %s ";
		if ( $post_id = $wpdb->get_var( $wpdb->prepare( $sql, $plant_url ) ) ) {
			echo "We already have ($post_id) $plant_url\n";
			continue;
		}

		if ( ! $dom = get_dom_for_url( $plant_url ) ) {
			echo "SW: Didn't get $plant_url \n";
			continue;
		}
		$plant[ 'latin-name' ] = $dom->find( '#content_main h2', 0 )->plaintext;
		if ( ! $plant[ 'taxonomies' ][ 'genus' ] = $dom->find( '#content_main h2 em', 0 )->plaintext )
			$plant[ 'taxonomies' ][ 'genus' ] = $dom->find( '#content_main h2 i', 0 )->plaintext;
		$plant[ 'common-name' ] = $dom->find( '#latin-name', 0 )->plaintext;
		// Attributes
		if ( $atts = $dom->find( '#card ul', 0 ) ) {
			foreach ( $atts->find( 'li' ) as $att ) {
				if ( str_starts_with( $att->plaintext, 'Position:' ) ) {
					$plant[ 'taxonomies' ][ 'position' ] = crocus_clean_attr( 'Position:', null, $att->plaintext );
				} else if ( str_starts_with( $att->plaintext, 'Soil:' ) ) {
					$plant[ 'taxonomies' ][ 'soil' ] = crocus_clean_attr( 'Soil:', null, $att->plaintext );
				} else if ( str_starts_with( $att->plaintext, 'Rate of growth:' ) ) {
					$plant[ 'taxonomies' ][ 'rate-of-growth' ] = crocus_clean_attr( 'Rate of growth:', null, $att->plaintext );
				} else if ( str_starts_with( $att->plaintext, 'Flowering period:' ) ) {
					$flowering_period = crocus_clean_attr( 'Flowering period:', null, $att->plaintext );
					$plant[ 'taxonomies' ][ 'flowering-period' ] = $flowering_period;
					$plant[ 'taxonomies' ][ 'flowering-months' ] = array();
					if ( preg_match( '/(\w+) to (\w+)/i', $flowering_period, $matches ) ) {
						$start = date( 'm', strtotime( $matches[ 1 ] ) );
						$end = date( 'm', strtotime( $matches[ 2 ] ) );
						while ( $start <= $end ) {
							$plant[ 'taxonomies' ][ 'flowering-months' ][] = date( 'F', mktime( 0, 0, 0, $start, 10 ) );
							$start++;
						}
					} else {
						$plant[ 'taxonomies' ][ 'flowering-months' ] = $flowering_period;
					}
				} else if ( str_starts_with( $att->plaintext, 'Hardiness:' ) ) {
					$plant[ 'taxonomies' ][ 'hardiness' ] = crocus_clean_attr( 'Hardiness:', null, $att->plaintext );
					// Description follows hardiness
					$lines = preg_split( '/\r\n|\r|\n/', $att->plaintext );
					array_shift( $lines );
					$plant[ 'description' ] = implode( "\n\n", $lines );
				} else if ( str_starts_with( $att->plaintext, 'Garden care:' ) ) {
					$plant[ 'garden-care' ] = crocus_clean_attr( 'Garden care:', null, $att->plaintext );
				}
			}
		}
		foreach ( $dom->find( '#cardheightspread li' ) as $att ) {
			if ( str_starts_with( $att->plaintext, 'Eventual Height:' ) ) {
				$plant[ 'taxonomies' ][ 'eventual-height' ] = crocus_clean_attr( 'Eventual Height:', null, $att->plaintext );
			} else if ( str_starts_with( $att->plaintext, 'Eventual Spread:' ) ) {
				$plant[ 'taxonomies' ][ 'eventual-spread' ] = crocus_clean_attr( 'Eventual Spread:', null, $att->plaintext );
			}
		}
		// Related plants
		$plant[ 'related' ] = array();
		foreach ( $dom->find( '.goes-with_item' ) as $related ) {
			$href = $related->find( 'a', 0 )->href;
			if ( $href )
				$plant[ 'related' ][] = 'http://www.crocus.co.uk' . $href;
		}
		$plant[ 'images' ] = array();
		foreach ( $dom->find( '#photos a[rel^=lightbox]' ) as $img ) {
			$plant[ 'images' ][] = 'http://www.crocus.co.uk' . $img->href;
		}
		// print_r( $plant );
		
		$post_data = array(
			'post_title' => $plant[ 'latin-name' ],
			'post_excerpt' => $plant[ 'excerpt' ],
			'post_content' => $plant[ 'description' ],
			'post_type' => 'plant',
			'post_status' => 'publish',
		);
		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			echo "ERROR inserting $plant_url : " . $post_id->get_error_message() . "\n";
			exit;
			break;
		} else {
			// echo "Inserted @ $post_id \n";
		}
		$meta_elements = array( 'common-name', 'garden-care', 'related' );
		foreach( $meta_elements as $element ) {
			$meta_key = '_' . str_replace('-', '_', $element );
			update_post_meta( $post_id, $meta_key, $plant[ $element ] );
		}
		update_post_meta( $post_id, '_plant_url', $plant_url );
		
		foreach ( $plant[ 'images' ] as $image_url ) {
			$file = $image_url;
			// Download file to temp location
			$tmp = download_url( $file );
			// echo "Got file $tmp\n";

			// Set variables for storage
			// fix file filename for query strings
			preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $file, $matches);
			$file_array['name'] = basename($matches[0]);
			$file_array['tmp_name'] = $tmp;

			// echo "Sorted out names \n";

			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
				unlink($file_array['tmp_name']);
				$file_array['tmp_name'] = '';
				echo "Could not temporarily store attachment " . $tmp->get_error_message() . ": " . print_r( $tmp, true ) . "\n";
				exit;
			}

			// echo "Stored temporarily \n";

			// do the validation and storage stuff
			$attachment_id = media_handle_sideload( $file_array, $post_id );
			// echo "Handled sideload \n";
			// If error storing permanently, unlink
			if ( is_wp_error($attachment_id) ) {
				unlink($file_array['tmp_name']);
				// var_dump( $tmp );
				// var_dump( $file_array );
				// var_dump( $attachment_id );
				// wp_delete_post( $post_id, true );
				echo "Could not permanently store attachment: " . $attachment_id->get_error_message() . "\n";
				// exit;
			} else {
				if ( ! function_exists( 'has_post_thumbnail' ) ) {
					echo "Function has_post_thumbnail does not exist! \n";
					exit;
				}

				if ( ! has_post_thumbnail( $post_id ) ) {
					// echo "Try to set post thumbnail for $post_id as $attachment_id \n";
					set_post_thumbnail( $post_id, $attachment_id );
				}

				// echo "Set post thumbnail \n";

				foreach ( $plant[ 'taxonomies' ] as $tax => $term_data )
					wp_set_object_terms( $post_id, $term_data, $tax );
				// echo "Setup plant " . $plant[ 'latin-name' ] . " \n";
			}

			unset( $scrape_attachment );

		}
	}

	echo "Completed scrape\n";
}

/**
 * Insert the data into posts.
 *
 * @param  
 * @return void
 **/
function load_plants() {
	global $plants;
	
}

// /**
//  * Hooks the WP init action late
//  *
//  * @param  
//  * @return void
//  **/
// function crocus_init(  ) {
// 	echo "Start scraping!";

	global $plants;
	// process_collection( 201, 'flowering-season', 'spring' );
	// process_collection( 202, 'flowering-season', 'summer' );
	// process_collection( 203, 'flowering-season', 'autumn' );
	// process_collection( 204, 'flowering-season', 'winter' );
	// REMEMBER TO SWITCH BLOG AGAIN!!
	scrape();
// }
// add_action( 'init', 'crocus_init', 99 );
// echo "Setup complete";

?>