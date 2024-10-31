<?php
/**
 * Save meta boxes
 *
 * @package Posts 2 Posts Relationships
 * @version 1.0.0
 */
 
defined( 'ABSPATH' ) || exit;

// We're inside function save_post(), the incoming parameters are: $post_id, $post, $update

global $wpdb;

// Check if the specified post is a revision
if ( $parent_id = wp_is_post_revision($post_id) ) {
	$post_id = $parent_id;
}

// Security
if ( ! current_user_can( 'edit_post', $post_id ) ) return;
if ( ! isset( $_POST['p2p-relationships-nonce'] ) || 
	 ! wp_verify_nonce( $_POST['p2p-relationships-nonce'], 'metabox_nonce' ) ) return;

// Discard autosave
if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; 

// There is any relationship to save?
if ( ! isset( $_POST['p2p-relationships'] ) || ! isset( $_POST['p2p-relationships']['keys'] ) ) return;

// Get relation keys and sanitize it
$keys = $this->sanitize_array_of_keys( $_POST['p2p-relationships']['keys'] );
if ( count( $keys ) < 1 ) return;

// Info about current saved post:
$this_type    = 'post';
$this_name    = get_post_type ( $post_id );
$this_id      = $post_id;
$this_lang    = $this->get_post_lang ( $post_id );
$this_status  = get_post_status ( $post_id );

foreach ( $keys as $key ) {
		
	// Check key
	if ( !isset( $this->options['relationships'][$key] ) ) continue;
	if ( !isset( $_POST['p2p-relationships'][$key.'_iam'] ) ) continue;

	foreach ( array ('from', 'to') as $iam ) {
		
		if ( !in_array( $iam, $_POST['p2p-relationships'][$key.'_iam'] ) ) continue;
		
		// Delete old relationships
		if ( $iam == 'from' ) {
			
			$data = array (
						'rel_key'   => $key, 
						'from_id'   => $this_id, 
					);

			$wpdb->delete( $wpdb->prefix . 'p2p_relationships', $data );

		} elseif ( $iam == 'to' ) {

			$data = array (
						'rel_key'   => $key, 
						'to_id'     => $this_id, 
					);

			$wpdb->delete( $wpdb->prefix . 'p2p_relationships', $data );
		}
			
		// Get selected elements
		if ( !isset( $_POST['p2p-relationships'][$key.'_'.$iam]) ) continue;
		
		$relations_id = $this->sanitize_array_of_integers( $_POST['p2p-relationships'][$key.'_'.$iam] );
		
		foreach ( $relations_id as $rel_id ) {

			// Info about related post:
			$rel_type    = 'post';
			$rel_name    = get_post_type ( $rel_id );
			$rel_id      = $rel_id;
			$rel_lang    = $this->get_post_lang ( $rel_id );
			$rel_status  = get_post_status ( $rel_id );
			
			if ( $iam == 'from' ) {
				
				$data = array (
							'rel_key'     => $key, 
							'from_type'   => $this_type, 
							'from_name'   => $this_name, 
							'from_id'     => $this_id, 
							'from_status' => $this_status, 
							'from_lang'   => $this_lang, 
							'to_type'     => $rel_type, 
							'to_name'     => $rel_name, 
							'to_id'       => $rel_id, 
							'to_status'   => $rel_status, 
							'to_lang'     => $rel_lang, 
						);
			
			} elseif ( $iam == 'to' ) {
				
				$data = array (
							'rel_key'     => $key, 
							'from_type'   => $rel_type, 
							'from_name'   => $rel_name, 
							'from_id'     => $rel_id, 
							'from_status' => $rel_status, 
							'from_lang'   => $rel_lang, 
							'to_type'     => $this_type, 
							'to_name'     => $this_name, 
							'to_id'       => $this_id, 
							'to_status'   => $this_status, 
							'to_lang'     => $this_lang, 
						);
			}
			$wpdb->insert( $wpdb->prefix . 'p2p_relationships', $data );
		}
	}
}

?>
