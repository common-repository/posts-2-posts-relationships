<?php
/**
 * Print meta box
 *
 * @package Posts 2 Posts Relationships
 * @version 1.0.0
 */
 
defined( 'ABSPATH' ) || exit;

// We're inside function print_meta_box_edit():
global $wpdb;

$key = $args['args']['key'];
$iam = $args['args']['iam'];

$reverse = ($iam == 'from') ? 'to' : 'from';

?>
<div class="p2p-relationships-wrapper edit p2p-relationships-ajax" data-what="choose-relations"
	data-key="<?php echo esc_attr( $key ); ?>" data-iam="<?php echo esc_attr( $iam ); ?>">
	<?php
			
	// Types combo
	$types = $this->options['relationships'][$key][$reverse]['object_name'];
	$types_filter_html = '';
	
	if ( is_array($types) && count($types) > 1 ) {
					
		foreach ( $types as $type ) {
								
			$object = get_post_type_object( $type);
			if ( !is_null( $object ) ) {
				
				$types_filter_html .= '<option value="' . esc_attr($type) . '">' . esc_html($object->label) . '</option>';
			}
		}
	}

	// Taxonomies combo
	$taxonomies = $this->options['relationships'][$key][$reverse]['tax_filter'];
	$tax_filter_html = '';
	
	if ( $taxonomies ) {
		
		if ( !is_array($taxonomies) ) $taxonomies = array ($taxonomies);
		
		foreach ( $taxonomies as $tax ) {
			
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
			
			if ( is_array($terms) && count($terms) > 0 ) {
				
				$taxonomy = get_taxonomy( $tax);
				$tax_filter_html .= '<optgroup label="' . esc_attr($taxonomy->label) . '">';
				
				foreach ( $terms as $term ) {
					$tax_filter_html .= '<option value="' . esc_attr($tax.'-tax_term-'.$term->term_id) . '">' . esc_html( $term->name . ' (' . $term->count . ')' ) . '</option>';
				}
				$tax_filter_html .= '</optgroup>';
			}
		}
	}
	
	if ( $types_filter_html != '' || $tax_filter_html != '' || $this->options['relationships'][$key][$reverse]['search_box'] ) {
		
		echo '<div class="filters">';
			echo '<div class="left">';
		
				// Search filter
				if ( $this->options['relationships'][$key][$reverse]['search_box'] ) {
					
					echo '<input type="text" value="" placeholder="' . esc_html__( 'Search...', 'posts-2-posts-relationships' ) . '" class="search" />';
				}
			echo '</div>';
			echo '<div class="right">';

				if ( $types_filter_html != '' ) {
					echo '<select class="type"><option value="">' . esc_html__( 'Filter by type', 'posts-2-posts-relationships' ) . '</option>' . $types_filter_html . '</select>';
				}

				if ( $tax_filter_html != '' ) {
					echo '<select class="taxonomy"><option value="">' . esc_html__( 'Filter by taxonomy', 'posts-2-posts-relationships' ) . '</option>' . $tax_filter_html . '</select>';
				}
			echo '</div>';
		echo '</div>';
	}
	?>
	<div class="two_cols">
		<div class="p2p-relationships-chooser p2p-relationships-ajax-in">
		</div>
		<div class="p2p-relationships-selected">
			<p class="no_relationships" style="display:hidden"><?php esc_html_e('There are no relationships yet', 'posts-2-posts-relationships'); ?></p>
			<ul class="sortable">
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
						if ( $related_indexed[ $related_id ]->post_status != 'publish' ) $name .= '<em class="status_' . esc_attr($related_indexed[ $related_id ]->post_status) . '">';
						$name .= esc_html($related_indexed[ $related_id ]->post_title);
						if ( $related_indexed[ $related_id ]->post_status != 'publish' ) $name .= '</em> [' . esc_html($related_indexed[ $related_id ]->post_status) . ']';

					} else {
						
						$name = '<em class="err_deleted">Not found!</em> [probably deleted]';
					}

					echo '<li data-id="' . esc_attr($related_id) . '">#' . esc_attr($related_id) . ' ' . $name;
					echo '<a href="#" class="remove"><span class="dashicons dashicons-dismiss"></span></a>';
					echo '<input type="hidden" name="p2p-relationships['.esc_attr($key.'_'.$iam).'][]" value="' . esc_attr($related_id) . '">';
					echo '</li>';
				}
			}
			?>
			</ul>
		</div>
	</div>
	<div class="buttons">
		<a href="#" class="button select_all" disabled="disabled"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Select all', 'posts-2-posts-relationships' ); ?></a>
		<a href="#" class="button unselect_all" disabled="disabled"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Unselect all', 'posts-2-posts-relationships' ); ?></a>
		<a href="#" class="button add_sel" disabled="disabled"><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add selected', 'posts-2-posts-relationships' ); ?></a>
		<a href="#" class="button remove_sel" disabled="disabled"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Remove selected', 'posts-2-posts-relationships' ); ?></a>
	</div>
</div>
<?php
wp_nonce_field( 'metabox_nonce', 'p2p-relationships-nonce' );
echo '<input type="hidden" name="p2p-relationships[keys][]" value="' . esc_attr($key) . '" />';
echo '<input type="hidden" name="p2p-relationships['.esc_attr($key.'_iam').'][]" value="' . esc_attr($iam) . '" />';
?>
