<?php

// /Users/simon/Projects/Crocus/htdocs/wp-content/uploads/2012/04/
// /Users/simon/Desktop/attachments.xml
// /Users/simon/Dropbox/Public/120409–Plants/

$xml = simplexml_load_file( '/Users/simon/Desktop/attachments.xml' );
// print_r( $dom );

$source_path = '/Users/simon/Projects/Crocus/htdocs/';
$dest_path = '/Users/simon/Dropbox/Public/120409–Plants/';

// print_r( $xml );

$i = 0;
for( $i = 0 ; $i < count( $xml->channel->item ); $i++ ) {
	$item = & $xml->channel->item[ $i ];
	$source = str_replace( 'http://crocus.simonwheatley.co.uk/', $source_path, $item->guid );
	$dest = str_replace( 'http://crocus.simonwheatley.co.uk/wp-content/uploads/2012/04/', $dest_path, $item->guid );
	echo "copy( $source, $dest );\n";
	copy( $source, $dest );
}

// echo $movies->movie[0]->plot;


?>