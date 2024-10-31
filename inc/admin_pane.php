<?php
/**
 * The options pane plugin
 *
 * @package Posts 2 Posts Relationships
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// We're inside function admin_pane()

global $wpdb;

// Hi, guys! Catch this filter to change available posts and taxonomies
$exclude_posts       = apply_filters ( 'p2p-relationships-excluded-post-types', array_merge(
											array ('wp_block'),	get_post_types( array('show_ui' => false) ) ) );
$exclude_taxonomies  = apply_filters ( 'p2p-relationships-excluded-taxonomies', get_taxonomies( array('show_ui' => false) ) );

// Post types
$all_post_types = get_post_types( array(), 'objects' );

// Taxonomy filter
$taxonomies_filter =array();
foreach ( $all_post_types as $pt) {
	
	// We will skip taxonomies of excluded posts
	if ( in_array( $pt->name, $exclude_posts ) ) continue;
	
	$taxonomies = get_object_taxonomies ( $pt->name );
	
	foreach ( $taxonomies as $taxonomy ) {
		
		// We will skip excluded taxonomies
		if ( in_array( $taxonomy, $exclude_taxonomies ) ) continue;
		
		if ( !isset( $taxonomies_filter[ $taxonomy ] ) ) $taxonomies_filter[ $taxonomy ] = array();
		$taxonomies_filter[ $taxonomy ][] = $pt->name;
	}
}

// Save options
if ( isset ($_POST['p2p_relationships_action']) && $_POST['p2p_relationships_action'] == 'save_opts' ) {
	
	if ( !wp_verify_nonce($_POST['p2p-relationships-opts'], 'save_opts') ) {
		print 'Sorry, your nonce did not verify.';
		exit;
	}
	
	$relationships = array();
	$unnamed = 0;
	$any_error = false;
	
	if ( isset($_POST['p2p-rel']) && is_array($_POST['p2p-rel']) ) {
	
		foreach ( $_POST['p2p-rel'] as $relationship ) {
			
			// Sanitize relationship, comming from the admin form, then key are inside
			list( $key, $sanitized_relationship, $errors ) = $this->sanitize_raw_relationship( $relationship );

			// No key or invalid? Let's give unique one
			if ( $key == '') {
				$unnamed++;
				$key = sanitize_key( 'unnamed_' . $unnamed );
			}

			if ( $errors == false ) {
				
			} else {
				if ( !$any_error ) {
					$sanitized_relationship['error'] = true;
					$any_error = true;
					echo '<div class="error p2p-relatioship">';
					echo '<h2>' . esc_html__( 'There is errors on current settings.', 'posts-2-posts-relationships' ) . '</h2>';
					echo '<p><strong>' . esc_html__( 'Changes not saved. Please, solve it and click Save again.', 'posts-2-posts-relationships' ) . '</strong></p>';
				}
				echo '<h3>' . esc_html ( sprintf( __('Error(s) on "%s":', 'posts-2-posts-relationships'), esc_html($key) ) ) . '</h3><ul>';
				foreach ($errors as $e) {
					echo '<li>' . esc_html($e) . '</li>';
				}
				echo '</ul>';
			}
			
			$relationships[$key] = $sanitized_relationship;
		}
		if ( $any_error ) echo '</div>';
	}

	// Maybe must delete some relationships?
	if ( !$any_error && isset($_POST['p2p-rel-erase-relationships']) && is_array($_POST['p2p-rel-erase-relationships']) ) {
		foreach ( $_POST['p2p-rel-erase-relationships'] as $key ) {
			$key = sanitize_key( $key );
			$sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}p2p_relationships WHERE rel_key=%s", $key );
			$wpdb->query( $sql );
		}
	}
	
	if ( !$any_error ) {
		// Save data
		$options = $this->options;
		$options['relationships'] = $relationships;
		$this->set_options($options);
		?>
			<div class="notice notice-success"> 
				<p><strong><?php esc_html_e('Settings saved.', 'posts-2-posts-relationships'); ?></strong></p>
			</div>
		<?php
	} else {
		// Close error container
		echo '</div>';
	}
	
} else {
	
	// Nothing to save? Let's check current relationships
	$relationships = $this->options['relationships'];
	$any_error = false;

	foreach ( $relationships as $key => $relationship ) {
		
		list( $relationship, $errors ) = $this->sanitize_relationship($relationship, true);
		
		if ( $errors != false ) {
			$relationship['error'] = true;
			if ( !$any_error ) {
				$any_error = true;
				echo '<div class="error p2p-relatioship">';
				echo '<h2>' . esc_html__( 'There is errors on current settings.', 'posts-2-posts-relationships' ) . '</h2>';
				echo '<p><strong>' . esc_html__( 'Maybe you\'re downgraded Posts 2 Posts Relationships?, please, check it. Any change on this settings can result in data loss.', 'posts-2-posts-relationships' ) . '</strong></p>';
			}
			echo '<h3>' . esc_html( sprintf( __('Error(s) on "%s":', 'posts-2-posts-relationships'), esc_html($key) ) ) . '</h3><ul>';
			foreach ($errors as $e) {
				echo '<li>' . esc_html($e) . '</li>';
			}
			echo '</ul>';
		}
		
		$relationships[$key] = $relationship;
	}
	if ( $any_error ) echo '</div>';
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline">Posts 2 Posts Relationships</h1>
	<form method="post" action="<?php echo admin_url('options-general.php?page=p2p-relationships-opts' ); ?>" novalidate="novalidate">
	
		<div id="p2p-rel-relationship-list">
			<div class="p2p-rel-header main">
				<div class="order"><?php esc_html_e('Order', 'posts-2-posts-relationships'); ?></div>
				<div class="label"><?php esc_html_e('Box title', 'posts-2-posts-relationships'); ?></div>
				<div class="name"><?php echo esc_html( _x('Key', 'Maybe better leave in english', 'posts-2-posts-relationships') ); ?></div>
				<div class="rel"><span class="dashicons dashicons-leftright" title="<?php echo esc_attr( __('Number of relationships', 'posts-2-posts-relationships') ); ?>"></span></div>
			</div>
			<ul class="sortable">
				<?php
				$n = 0;
				
				if ( is_array( $relationships ) && count ($relationships) > 0 ) {
				
					foreach ( $relationships as $key => $params ) {
						
						// Calculate current relations for this relationship
						$rel = 0;
						$rel = $this->get_raw( array( 'key' => $key, 'element_id' => 'all' ) );
						$rel = is_array($rel) ? count($rel) : 0;
						
						p2p_relationships_rel_form ( $key, $params, $n, $rel, $all_post_types, $exclude_posts, $taxonomies_filter ) ;
						$n++;
					}
				
				}
				?>
				<li class="no_relations"><?php esc_html_e('There is currently no relations for now. Start by adding a new one.', 'posts-2-posts-relationships'); ?></li>
			</ul>
			<div class="buttons">
				<a href="#" class="button white button-large p2p-rel-new-rel"><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('New Relation', 'posts-2-posts-relationships'); ?></a>
			</div>
		</div>
		<?php
		// There is orphan relationships?
		$sql = "SELECT rel_key, count(rel_id) AS count FROM {$wpdb->prefix}p2p_relationships WHERE rel_key NOT IN ('" . implode( "','" , esc_sql( array_keys($relationships) ) ) . "') GROUP BY rel_key";
		$orphans = $wpdb->get_results( $sql );
		
		if ( is_array($orphans) && count($orphans) > 0 ) {
			?>
			<div class="notice notice-error inline" id="p2p-rel-orphans">
				<h3><?php esc_html_e('There is orphans relationships into database:','posts-2-posts-relationships'); ?></h3>
				<p><?php esc_html_e('You can remove it or create a new relation with the same key to recover it:','posts-2-posts-relationships'); ?></p>
				<ul>
				<?php
				foreach ($orphans as $orphan) {
					echo '<li>- ' . sprintf( esc_html( __('There is %s orphan relationship(s) under the key: %s', 'posts-2-posts-relationships') ), '<strong>'.esc_html($orphan->count).'</strong>', '<strong>'.esc_html($orphan->rel_key).'</strong>' );
					echo '<a href="#" class="p2p-rel-erase-orphan" data-ddbb-key="'.esc_attr($orphan->rel_key).'">' . esc_html__( 'Erase relationships', 'posts-2-posts-relationships' ) . '</a></li>';
				}
				?>
				</ul>
			</div>
			<?php
		}
		?>
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php echo esc_attr( __('Save Changes', 'posts-2-posts-relationships') ); ?>">
		</p>
		<?php wp_nonce_field( 'save_opts','p2p-relationships-opts' ); ?>
		<input type="hidden" name="p2p_relationships_action" value="save_opts" />
	</form>
	<ul class="p2p-relationship-empty">
		<?php
		// Default empty relationship for new JavaScript creation
		$empty = $this->get_default_relationship();
		$empty['from']['object_name'] = array();
		$empty['to']['object_name']   = array();
		p2p_relationships_rel_form ( '', $empty, 0, 0, $all_post_types, $exclude_posts, $taxonomies_filter ) ;
		?>
	</ul>
</div>	

<?php

function p2p_relationships_rel_form ( $key, $params, $n, $rel, $all_post_types, $exclude_posts, $taxonomies_filter ) {

	$classname = $rel==0 ? 'no_relationships' : 'has_relationships';
	if ( isset($params['error']) && $params['error'] ) $classname .= ' error';
	?>
	<li class="p2p-rel-relation <?php echo esc_attr($classname); ?>" >
		<div class="p2p-rel-header">
			<div class="order"><span class="icon"><?php echo esc_html( $n+1 ); ?></span></div>
			<div class="label">
				<a href="#" class="p2p-rel-title"><?php echo esc_html( $params['box_title'] ); ?></a>
				<div class="row-options">
					<a href="#" class="p2p-rel-edit"><?php esc_html_e( 'Edit', 'posts-2-posts-relationships' ); ?></a>
					<a href="#" class="p2p-rel-duplicate"><?php esc_html_e( 'Duplicate', 'posts-2-posts-relationships' ); ?></a>
					<?php if ($rel > 0) { ?>
						<a href="#" class="red p2p-rel-erase"><?php esc_html_e( 'Erase relationships', 'posts-2-posts-relationships' ); ?></a>
					<?php } ?>
					<a href="#" class="red p2p-rel-delete"><?php esc_html_e( 'Delete', 'posts-2-posts-relationships' ); ?></a>
				</div>
			</div>
			<div class="name"><?php echo esc_html( $key ); ?></div>
			<div class="rel"><?php echo esc_html($rel); ?></div>
		</div>
		<div class="p2p-rel-body">
			<input type="hidden" name="p2p-rel[<?php echo esc_attr($n); ?>][relation]" value="<?php echo esc_attr( $params['relation'] ); ?>" />
			<div class="p2p-rel-section">
				<div class="p2p-rel-label">
					<label><?php esc_html_e( 'Box title', 'posts-2-posts-relationships' ); ?></label>
					<p class="description"><?php esc_html_e( 'This is the title of the relation box on EDIT pages', 'posts-2-posts-relationships' ); ?></p>
				</div>
				<div class="p2p-rel-field">
					<input type="text" name="p2p-rel[<?php echo esc_attr($n); ?>][box_title]" value="<?php echo esc_attr( $params['box_title'] ); ?>" class="reactive_title" />
				</div>
			</div>
			<div class="p2p-rel-section">
				<div class="p2p-rel-label">
					<label><?php echo esc_html( _x('Key', 'Maybe better leave in english', 'posts-2-posts-relationships') ); ?></label>
					<p class="description"><?php esc_html_e( 'Single word, no spaces. Underscores and dashes allowed', 'posts-2-posts-relationships' ); ?></p>
				</div>
				<div class="p2p-rel-field">
					<input type="text" name="p2p-rel[<?php echo esc_attr($n); ?>][key]" value="<?php echo esc_attr( $key ); ?>" data-ddbb-key="<?php echo esc_attr( $key ); ?>" class="reactive_key" />
				</div>
			</div>
			<div class="p2p-rel-two_cols">
				<?php
				foreach ( array('from', 'to') as $iam ) {
					?>
					<div class="p2p-rel-col">
						<!--start column from -->
						<div class="p2p-rel-head_section">
							<?php 
							if ( $iam == 'from' ) {
								$iam_i18n      = __( 'From', 'posts-2-posts-relationships' );
								$reverse_i18n  = __( 'To', 'posts-2-posts-relationships' );
							} else {
								$iam_i18n      = __( 'To', 'posts-2-posts-relationships' );
								$reverse_i18n  = __( 'From', 'posts-2-posts-relationships' );
							}
							echo '<h2>' . esc_html( $iam_i18n ) . '</h2>';
							?>
						</div>
						<div class="p2p-rel-section">
							<div class="p2p-rel-label">
								<label><?php esc_html_e( 'Object', 'posts-2-posts-relationships' ); ?></label>
								<p class="description"><?php esc_html_e( 'Select one or more post type', 'posts-2-posts-relationships' ); ?></p>
							</div>
							<div class="p2p-rel-field">
								<select name="p2p-rel[<?php echo esc_attr($n); ?>][<?php echo esc_attr($iam); ?>][object_name][]" multiple="multiple" class="select2">
									<?php 
									// Object name can be a string, make it array() in any case
									$on = $params[$iam]['object_name']; if ( !is_array($on) ) $on = array ($on);
									foreach ( $all_post_types as $pt) {
										if ( in_array( $pt->name, $exclude_posts ) ) continue;
										$sel = in_array( $pt->name, $on );
										echo '<option value="' . esc_attr($pt->name) . '"' . ( $sel ? ' selected' : '' ) . '>';
										echo esc_html( $pt->label.' ['.$pt->name.']' ) . '</option>';
									}
									?>
								</select>
							</div>
						</div>
						<div class="p2p-rel-head_section">
							<p class="subtitle"><?php echo esc_html( sprintf( __('%s box configuration', 'posts-2-posts-relationships'), $iam_i18n ) ); ?></p>
							<p class="description"><?php echo esc_html( sprintf( __('The %s box will be displayed on %s edit page:', 'posts-2-posts-relationships'), strtoupper($iam_i18n), strtoupper($reverse_i18n) ) ); ?></p>
						</div>
						<div class="p2p-rel-section">
							<div class="p2p-rel-label">
								<label><?php esc_html_e( 'UI Mode', 'posts-2-posts-relationships' ); ?></label>
								<p class="description"><?php esc_html_e( 'At least one of the two must be "Editable"', 'posts-2-posts-relationships' ); ?></p>
							</div>
							<div class="p2p-rel-field">
								<select name="p2p-rel[<?php echo esc_attr($n); ?>][<?php echo esc_attr($iam); ?>][ui_mode]" class="ui_mode_combo">
									<option value="edit" <?php if ($params[$iam]['ui_mode'] == 'edit') echo 'selected'; ?>><?php esc_html_e( 'Editable', 'posts-2-posts-relationships' ); ?></option>
									<option value="view" <?php if ($params[$iam]['ui_mode'] == 'view') echo 'selected'; ?>><?php esc_html_e( 'View only', 'posts-2-posts-relationships' ); ?></option>
									<option value="hidden" <?php if ($params[$iam]['ui_mode'] == 'hidden') echo 'selected'; ?>><?php esc_html_e( 'Hidden', 'posts-2-posts-relationships' ); ?></option>
								</select>
							</div>
						</div>
						<div class="p2p-rel-section position_fields">
							<div class="p2p-rel-label">
								<label><?php esc_html_e( 'Box position', 'posts-2-posts-relationships' ); ?></label>
								<p class="description"><?php esc_html_e( 'Where should appear? Main column or sidebar?', 'posts-2-posts-relationships' ); ?></p>
							</div>
							<div class="p2p-rel-field">
								<select name="p2p-rel[<?php echo esc_attr($n); ?>][<?php echo esc_attr($iam); ?>][box_context]">
									<option value="normal" <?php if ($params[$iam]['box_context'] == 'normal') echo 'selected'; ?>><?php esc_html_e( 'Normal', 'posts-2-posts-relationships' ); ?></option>
									<option value="side" <?php if ($params[$iam]['box_context'] == 'side') echo 'selected'; ?>><?php esc_html_e( 'Side', 'posts-2-posts-relationships' ); ?></option>
									<option value="advanced" <?php if ($params[$iam]['box_context'] == 'advanced') echo 'selected'; ?>><?php esc_html_e( 'Advanced', 'posts-2-posts-relationships' ); ?></option>
								</select>
							</div>
						</div>
						<div class="p2p-rel-section position_fields">
							<div class="p2p-rel-label">
								<label><?php esc_html_e( 'Box priority', 'posts-2-posts-relationships' ); ?></label>
								<p class="description"><?php esc_html_e( 'Control box order in EDIT page', 'posts-2-posts-relationships' ); ?></p>
							</div>
							<div class="p2p-rel-field">
								<select name="p2p-rel[<?php echo esc_attr($n); ?>][<?php echo esc_attr($iam); ?>][box_priority]">
									<option value="high" <?php if ($params[$iam]['box_priority'] == 'high') echo 'selected'; ?>>High</option>
									<option value="core" <?php if ($params[$iam]['box_priority'] == 'core') echo 'selected'; ?>>Core</option>
									<option value="default" <?php if ($params[$iam]['box_priority'] == 'default') echo 'selected'; ?>>Default</option>
									<option value="low" <?php if ($params[$iam]['box_priority'] == 'low') echo 'selected'; ?>>Low</option>
								</select>
							</div>
						</div>
						<div class="p2p-rel-section filter_fields">
							<div class="p2p-rel-label">
								<label><?php esc_html_e( 'Filters', 'posts-2-posts-relationships' ); ?></label>
								<p class="description"><?php esc_html_e( 'Add a search text', 'posts-2-posts-relationships' ); ?></p>
							</div>
							<div class="p2p-rel-field">
								<input type="checkbox" name="p2p-rel[<?php echo esc_attr($n); ?>][<?php echo esc_attr($iam); ?>][search_box]" <?php if ($params[$iam]['search_box']) echo ' checked'; ?> value="1"><label><?php esc_html_e( 'Search text box', 'posts-2-posts-relationships' ); ?></label>
							</div>
						</div>
						<div class="p2p-rel-section filter_fields">
							<div class="p2p-rel-label">
								<label><?php esc_html_e( 'Filters', 'posts-2-posts-relationships' ); ?></label>
								<p class="description"><?php esc_html_e( 'Add a taxonomy filter', 'posts-2-posts-relationships' ); ?></p>
							</div>
							<div class="p2p-rel-field">
								<select name="p2p-rel[<?php echo esc_attr($n); ?>][<?php echo esc_attr($iam); ?>][tax_filter][]" multiple="multiple"  class="select2">
									<?php
									// Tax filter can be a string, make it array() in any case
									$tf = $params[$iam]['tax_filter']; if ( !is_array($tf) ) $tf = array ($tf);
									foreach ( $taxonomies_filter as $taxonomy_name => $posts ) {
										$sel = in_array( $taxonomy_name, $tf );
										$taxonomy = get_taxonomy($taxonomy_name);
										echo '<option data-posts="' . esc_attr( implode ( ',', $posts) ) . '" value="' . esc_attr($taxonomy_name) . '"' . ( $sel ? ' selected' : '' ) . '>';
										echo esc_html($taxonomy->label) . ' [' . esc_html($taxonomy_name) . ']</option>';
									}
									?>
								</select>
							</div>
						</div>

						<!--end column from -->
					</div>
					<?php
				}
				?>
			</div>
		</div>
	</li>
	<?php
}