<?php
/**
 * Print meta box view only
 *
 * @package Posts 2 Posts Relationships
 * @version 1.0.0
 */
 
defined( 'ABSPATH' ) || exit;

// We're inside function print_meta_box_view():
global $wpdb;

$key = $args['args']['key'];
$iam = $args['args']['iam'];

?>
<div class="p2p-relationships-wrapper view_only">
		<div class="p2p-relationships-selected">
			<ul>
			<?php
			
			$related = false;
			
			if ( $iam == 'from' ) {

				$args = array (
								'key'           => $key,
								'element_id'    => $post->ID,
								'element_type'  => 'post',
								'direction'     => 'from_to',
								'status'        => 'any'
							  );
				$related_ids = $this->get_raw( $args );
				
			} elseif ( $iam == 'to' ) {
				
				$args = array (
								'key'           => $key,
								'element_id'    => $post->ID,
								'element_type'  => 'post',
								'direction'     => 'to_from',
								'status'        => 'any'
							  );
				$related_ids = $this->get_raw( $args );
			}
			
			if ( is_array($related_ids) && count($related_ids) > 0 ) {
								
				// Get post titles and status in the best performant way, keeping order
				$sql =   "SELECT ID, post_title, post_status FROM {$wpdb->prefix}posts" .
						" WHERE ID IN (" . implode( ',' , esc_sql ( $related_ids ) ) . ")" .
						" ORDER BY FIND_IN_SET(ID, '" . implode( ',' , esc_sql ( $related_ids ) ) . "')";
				
				$related_posts = $wpdb->get_results( $sql );
				
				// Need to put the ID as index
				$related_indexed = array();
				foreach ( $related_posts as $related_el ) {
					$related_indexed[ $related_el->ID ] = $related_el;
				}
				
				foreach ( $related_ids as $related_id ) {
					
					if ( isset( $related_indexed[ $related_id ] ) ) {
						
						$name = '';
						if ( $related_indexed[ $related_id ]->post_status != 'publish' ) $name .= '<em class="' . esc_html('status_'.$related_indexed[ $related_id ]->post_status) . '">';
						$name .= $related_indexed[ $related_id ]->post_title;
						if ( $related_indexed[ $related_id ]->post_status != 'publish' ) $name .= '</em> [' . esc_html($related_indexed[ $related_id ]->post_status) . ']';

					} else {
						
						$name = '<strong class="err_deleted">Not found!</strong> <em class="err_deleted">[probably deleted]</em>';
					}

					echo '<li data-id="' . esc_attr($related_id) . '">#' . esc_attr($related_id) . ' ' . esc_html( $name );
					echo '</li>';
					$related = true;
				}
			}
			
			?>
			</ul>
			<?php
			if ( !$related ) echo '<p class="no_relationships" style="display:hidden">' . esc_html__('There are no relationships yet', 'posts-2-posts-relationships') . '</p>';
			?>
		</div>
</div>
<?php
wp_nonce_field( 'metabox_nonce', 'p2p-relationships-nonce' );
echo '<input type="hidden" name="p2p-relationships[keys][]" value="' . esc_attr($key) . '" />';
echo '<input type="hidden" name="p2p-relationships[' . esc_attr($key) . '_iam][]" value="' . esc_attr($iam) . '" />';
?>
