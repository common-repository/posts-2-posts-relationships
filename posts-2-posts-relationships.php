<?php
/*
 * Plugin Name: Posts 2 Posts Relationships
 * Plugin URI: 
 * Description: Efficient many-to-many connections between posts, pages and custom post types.
 * Version: 1.0.0
 * Author: wpcentrics
 * Author URI: https://www.wp-centrics.com
 * Text Domain: posts-2-posts-relationships
 * Domain Path: /lang/
 * Requires at least: 4.7
 * Tested up to: 5.8
 * Requires PHP: 5.5
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @package Posts 2 Posts Relationships
*/

defined( 'ABSPATH' ) || exit;

define ('P2P_REL_VERSION', '1.0.0' );
define ('P2P_REL_PATH', dirname(__FILE__) . '/' );
define ('P2P_REL_URL', plugin_dir_url( __FILE__ ) );

class P2P_Relationships {

	private $options = array ();

	/************************************************************************
	     Construct & initialise everything
	 ************************************************************************/
	
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 */
	public function __construct() {

		$this->load_options();

		// Init stuff
		//add_action( 'init', array ( $this, 'init' ) );

		// Admin-side interface: styles & scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_load_styles_and_scripts' ) );

		// Admin side: save, delete, or change post status
		add_action( 'save_post',      array ( $this, 'save_post' ), 10, 3 );
		add_action( 'deleted_post',   array ( $this, 'deleted_post' ), 10, 1 );
		add_action( 'transition_post_status', array ( $this, 'transition_post_status' ), 10, 3 );

		// Admin-side interface: configuration
		add_action( 'admin_menu', array ( $this, 'admin_menu' ), 80 );
		
		// Metaboxes for relationships
		add_action( 'add_meta_boxes', array ( $this, 'add_meta_boxes' ) );
		
		// AJAX
		add_action( 'wp_ajax_p2p_relationships', array( $this, 'admin_ajax' ) );
		
		// Link to configuration
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_plugin_action_link' ) );
		
		// Alter raw $wpdb queries to find related posts
		add_filter( 'posts_where',   array ( $this, 'posts_where'), 10, 2 );
		add_filter( 'posts_orderby', array ( $this, 'posts_orderby'), 100, 2 );

	}
	
	/**
	 * After all plugins are loaded, we will initialise everything
	 *
	 * @since 1.0.0
	 *
	 */
	function init() {

		// Register plugin text domain for translations files
		//load_plugin_textdomain( 'posts-2-posts-relationships', false, basename( dirname( __FILE__ ) ) . '/lang' );
		
	}
		
	/**
	 * Load options at plugin inisialitation, and maybe first install.
	 *
	 * @since 1.0.0
	 *
	 */
	public function load_options() {
		
		$should_update = false;
		
		// Set default options, for first installation
		$options = array(
			'first_version'    => P2P_REL_VERSION,
			'first_install'    => time(),
			'five_stars'       => time() + (60 * 60 * 24 * 10), // ten days
			'current_version'  => '',
			'relationships'    => array()
			
		);
		
		// Load options from DB and overwrite defaults
		$opt_db = get_option( 'p2p-relationships', array() );
		if (is_array($opt_db)) {
			foreach ($opt_db as $key=>$value) {
				$options[$key] = $value;
			}
		}

		// First install?
		if ($options['current_version'] == '') {
			$options['current_version'] = P2P_REL_VERSION;
			$should_update = true;
		}
		
		// Plugin Update?
		if (version_compare($options['current_version'], P2P_REL_VERSION, '<') ) {
			$options['current_version'] = P2P_REL_VERSION;
			$should_update = true;
		}
		
		$this->options = $options;
		
		if ($should_update) {
			
			$this->set_options($options);
		}
	}
	
	/************************************************************************
	     Getters
	 ************************************************************************/

	/**
	 * Get relationships
	 *
	 * @since 1.0.0
	 *
	 */
	public function get_relationships() {
		return $this->options['relationships'];
	}

	/**
	 * Options getter
	 *
	 * @since 1.0.0
	 *
	 */
	public function get_options() {
		
		return $this->options;
	}

	function get_default_relationship() {
		 
		return array (  // unique name

			'relation'     => 'many_to_many', // relation type
			'box_title'    => '(no title)',
			
			'from' => array (
					'object_type' => 'post', // post for now, term also in future
					'object_name' => array('post'),  // custom post type for now, can be multiple
					'ui_mode'     => 'edit', // edit / view / hidden

					'box_context'  => 'normal', // wp values:  normal | side | advanced
					'box_priority' => 'default', // wp values:  high | core | default | low
					'search_box'   => false,
					'tax_filter'   => array (), // can be multiple
			),
			
			'to' => array (
					'object_type' => 'post', // post for now, term also in future
					'object_name' => array('post'),  // custom post type for now, can be multiple
					'ui_mode'     => 'hidden', // edit / view / hidden

					'box_context'  => 'normal', // wp values:  normal | side | advanced
					'box_priority' => 'default', // wp values:  high | core | default | low
					'search_box'   => false,
					'tax_filter'   => array (), // can be multiple
			)
		);
	}

	/************************************************************************
	     Setters
	 ************************************************************************/

	/**
	 * Options setter
	 *
	 * @since 1.0.0
	 *
	 */
	public function set_options($options) {

		update_option( 'p2p-relationships', $options, true );
		$this->options = $options;
	}

	/************************************************************************
	     Sanitization
	 ************************************************************************/
	 
	/**
	 * Sanitize array of keys
	 *
	 * @since 1.0.0
	 *
	 */
	public function sanitize_array_of_keys( $array_of_keys ) {
		
		if ( !is_array($array_of_keys) ) return array();
		
		foreach ( $array_of_keys as $index => $key ) {
			$array_of_keys[$index] = sanitize_key( $key );
		}
		return $array_of_keys;
	}

	/**
	 * Sanitize array of integers
	 *
	 * @since 1.0.0
	 *
	 */
	public function sanitize_array_of_integers( $array_of_ints ) {
		
		if ( !is_array($array_of_ints) ) return array();
		
		foreach ( $array_of_ints as $index => $int ) {
			$array_of_ints[$index] = intval( $int );
		}
		return $array_of_ints;
	}
	
	/**
	 * Sanitize raw relationship: from the admin pane, includes the key inside
	 *
	 * @since 1.0.0
	 *
	 */
	public function sanitize_raw_relationship( $relationship ) {

		// First we will get, sanitize and remove the inside key
		$key = '';
		if ( isset ( $relationship['key'] ) ) {
			$key = sanitize_key( $relationship['key'] );
			unset ( $relationship['key'] );
		}
		
		// Sanitize without auto-completion
		list( $sanitized_relationship, $debug_messages ) = $this->sanitize_relationship($relationship, true, true);

		return array ( $key, $sanitized_relationship, $debug_messages );
	}

	/**
	 * Sanitize relationship setting
	 *
	 * @since 1.0.0
	 *
	 */
	public function sanitize_relationship( $relationship, $debug = false, $saving_settings = true ) {

		$debug_messages = array();
		
		$allowed_main_keys    = array ( 'relation', 'box_title', 'from', 'to' );
		$allowed_relations    = array ( 'many_to_many' );
		$allowed_sub_keys     = array ( 'object_type', 'object_name', 'ui_mode', 'box_context', 'box_priority', 'search_box', 'tax_filter' );
		$required_sub_keys    = array ( 'object_name', 'ui_mode' );
		
		$allowed_values       = array (
									'object_type'  => array ( 'post' ),
									'ui_mode'      => array ( 'edit', 'view', 'hidden' ),
									'box_context'  => array ( 'normal', 'side', 'advanced' ),
									'box_priority' => array ( 'high', 'core', 'default', 'low' )
								 );

		$default_values = $this->get_default_relationship();
		
		if ( $saving_settings ) {
			$default_values['from']['object_name'] = array();
			$default_values['to']['object_name'] = array();
		}
		
		// Not an array?
		if ( !is_array( $relationship ) ) {
			
			$debug_messages[] = 'Array expected';
			if ($debug) return array ($default_values, $debug_messages);
			
			return $default_values;
		}

		// Checking missing main keys
		foreach ( $allowed_main_keys as $key ) {
			if ( !isset( $relationship[$key] ) ) {
				$debug_messages[] = 'Missing key: ' . sanitize_key($key);
			}
		}
				
		// Checking unknown main keys
		foreach ( $relationship as $key=>$value ) {
			if ( !in_array( $key, $allowed_main_keys ) ) {
				$debug_messages[] = 'Unknown key: ' . sanitize_key($key);
				unset( $relationship[$key] );
			}
		}
		
		// Checking missing sub keys
		foreach ( $required_sub_keys as $subkey ) {
			if ( !$saving_settings && !isset( $relationship['from'][$subkey] ) ) {
				$debug_messages[] = 'Missing subkey on FROM: ' . sanitize_key($subkey);
			}
			if ( !$saving_settings && !isset( $relationship['to'][$subkey] ) ) {
				$debug_messages[] = 'Missing subkey on TO: ' . sanitize_key($subkey);
			}
		}

		// Add default values for missing keys / subkeys
		$relationship = array_merge( $default_values, $relationship );
		$relationship['from'] = array_merge( $default_values['from'], $relationship['from'] );
		$relationship['to']   = array_merge( $default_values['to'],   $relationship['to'] );
		
		// Checking relation type
		if ( !in_array( $relationship['relation'], $allowed_relations ) ) {
			$debug_messages[] = 'Unknown relation type: ' . sanitize_key($relationship['relation']);
			$relationship['relation'] = $allowed_relations[0];
		}
				
		// checking from and to
		foreach ( array('from', 'to') as $iam ) {

			// must be arrays
			if ( !is_array( $relationship[$iam] ) ) {
				$debug_messages[] = 'Array expected on: ' . strtoupper(sanitize_key($iam));
				$relationship[$iam] = $default_values($iam);
			}
			foreach ( $relationship[$iam] as $subkey => $value ) {
				
				// checking subkeys
				if ( !in_array( $subkey, $allowed_sub_keys ) ) {
					$debug_messages[] = 'Unknown subkey on ' . strtoupper(sanitize_key($iam)) . ': ' . sanitize_key($subkey);
					unset( $relationship[$iam][$subkey] );
				}
				
				if ( isset( $allowed_values[$subkey] ) ) {
					
					// checking values for field known in $allowed_values[]
					if ( !in_array( $relationship[$iam][$subkey], $allowed_values[$subkey] ) ) {
						$debug_messages[] = 'Unknown value for ' . strtoupper(sanitize_key($iam)) . '/' . sanitize_key($subkey) . ': ' . sanitize_key($relationship[$iam][$subkey]);
						$relationship[$iam][$subkey] = $allowed_values[$subkey][0];
					}
				
				} elseif ( $subkey == 'search_box' ) {
					
					// Only boolean
					if ( in_array( $relationship[$iam][$subkey], array ( 1, '1', 'on', true) ) ) {
						$relationship[$iam][$subkey] = true;
					
					} elseif ( in_array( $relationship[$iam][$subkey], array ( 0, '0', 'off', false) ) ) {
						$relationship[$iam][$subkey] = false;
					
					} else {
						$debug_messages[] = 'Unknown value for ' . strtoupper(sanitize_key($iam)) . '/' . sanitize_key($subkey) . ': ' . sanitize_key($relationship[$iam][$subkey]);
						$relationship[$iam][$subkey] = $default_values[$iam][$subkey];
					}
				
				} else {
					
					// Other values must be sanitized in standard way: array of sanitized keys
					// for now object_name & tax_filter
					
					if ( 
							!is_array( $relationship[$iam][$subkey] ) 
							&& ( $relationship[$iam][$subkey] == false || $relationship[$iam][$subkey] == '' ) ) 
					{
						$relationship[$iam][$subkey] = array();
					}
					if ( !is_array( $relationship[$iam][$subkey] ) ) $relationship[$iam][$subkey] = array ( $relationship[$iam][$subkey] );
					
					// Sanitize free values
					foreach ( $relationship[$iam][$subkey] as $item_key=>$item_value ) {
						if ( $item_value !== sanitize_key ($item_value) ) {
							$debug_messages[] = 'Invalid value in ' . strtoupper(sanitize_key($iam)) . '/' . sanitize_key($subkey) . ': ' . sanitize_key($item_value);
						}
						$relationship[$iam][$subkey][$item_key] = sanitize_key($item_value);
					}
				}
			}
			
			// Objects must be known
			if ( isset($relationship[$iam]['object_name']) && is_array($relationship[$iam]['object_name']) ) {
				$post_types = get_post_types( '', 'names' ); 
				foreach ( $relationship[$iam]['object_name'] as $item_key=>$item_value ) {
					if ( !in_array( $item_value, $post_types ) ) {
						$debug_messages[] = 'Unknown object name (post type) on ' . strtoupper(sanitize_key($iam)) . ': ' . sanitize_key($item_value);
						unset( $relationship[$iam]['object_name'][$item_key] );
					}
				}
				if ( count ( $relationship[$iam]['object_name'] ) == 0 ) {
					$debug_messages[] = 'Object name (post type) required in ' . strtoupper(sanitize_key($iam)) . ', set as: ' 
										. sanitize_key( implode(',', $default_values[$iam]['object_name'] ) );
					$relationship[$iam]['object_name'] = $default_values[$iam]['object_name'];
				}
			} else {
				$debug_messages[] = 'Object name (post type) required in ' . strtoupper(sanitize_key($iam));
			}
		}

		if ($debug) return array ($relationship, count ($debug_messages) > 0 ? $debug_messages : false );
		
		return $relationship;
	}

	/************************************************************************
	     Admin styles and scripts
	 ************************************************************************/

	/**
	 * Admin-side styles and scripts
	 *
	 * @since 1.0.0
	 *
	 */
	public function admin_load_styles_and_scripts () {
		

		wp_register_script( 'p2p-relationships_admin_script', P2P_REL_URL . 'assets/js/admin-p2p-relationships.js',   array( 'jquery-core', 'jquery-ui-sortable' ), P2P_REL_VERSION );
		wp_register_style ( 'p2p-relationships_admin_style',  P2P_REL_URL . 'assets/css/admin-p2p-relationships.css', array(), P2P_REL_VERSION );

		wp_enqueue_script ( 'p2p-relationships_admin_script' );
		wp_enqueue_style  ( 'p2p-relationships_admin_style' );

		// Only on Settings > P2P Relationships, for compatibility prevention
		if ( !isset( $_GET['page'] ) || $_GET['page'] != 'p2p-relationships-opts') return;
			
			// Load select2 in the same way as ACF, to avoid incompatibility issues 
			// ====================================================================
			
			// globals
			global $wp_scripts, $wp_styles;
			
			// vars
			$min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
			$major = '4';
			$version = '';
			$script = '';
			$style = '';
			
			// attempt to find 3rd party Select2 version
			// - avoid including v3 CSS when v4 JS is already enququed
			if( isset($wp_scripts->registered['select2']) ) {
				$major = (int) $wp_scripts->registered['select2']->ver;
			}
			
			// v4
			if( $major == 4 ) {
				
				$version = '4.0';
				$script  = P2P_REL_URL . "assets/js/select2/4/select2.full{$min}.js";
				$style   = P2P_REL_URL . "assets/js/select2/4/select2{$min}.css";
			
			// v3
			} else {
				
				$version = '3.5.2';
				$script  = P2P_REL_URL . "assets/js/select2/3/select2{$min}.js";
				$style   = P2P_REL_URL . "assets/js/select2/3/select2.css";
				
			}
			
			// enqueue
			wp_enqueue_script('select2', $script, array('jquery'), $version );
			wp_enqueue_style('select2', $style, '', $version );
	}

	/************************************************************************
	     Admin settings
	 ************************************************************************/

	/**
	 * Add submenu link under the Settings admin menu option
	 *
	 * @since 1.0.0
	 *
	 */
	function admin_menu() {
		
		add_submenu_page( 'options-general.php', 'Posts 2 Posts Relationships', 'P2P Relationships', 'manage_options', 'p2p-relationships-opts', array ($this, 'admin_pane'), 20 );
	}

	/**
	 * Require the admin settings pane 
	 *
	 * @since 1.0.0
	 *
	 */
	function admin_pane() {
		require( P2P_REL_PATH . 'inc/admin_pane.php');
	}
	
	/**
	* Add link on the plugin list
	*
	*/
	public static function add_plugin_action_link( $links ) {
	
		$start_link = array(
			'<a href="'. admin_url( 'options-general.php?page=p2p-relationships-opts' )
			 .'" style="color: #a16696; font-weight: bold;">'. esc_html__( 'Configure', 'coming-soon-wc') .'</a>',
		);
	
		return array_merge( $start_link, $links );
	}	


	/************************************************************************
	     Admin metaboxes (edit pages)
	 ************************************************************************/

	/**
	 * Add metaboxes where required
	 *
	 * @since 1.0.0
	 *
	 */
	function add_meta_boxes() {
		
		foreach ( $this->options['relationships'] as $key => $params ) {
			
			foreach ( array ( 'from', 'to' ) as $iam ) {
				
				// The visualization options are on the reverse
				$reverse      = ($iam == 'from') ? 'to' : 'from';
				$ui_mode      = $params[ $reverse ][ 'ui_mode' ];
				
				$object_type  = $params[ $iam ][ 'object_type' ];
			
				if ( $object_type == 'post' && $ui_mode != 'hidden' ) {
					
					add_meta_box(
						'p2p-relationships-from-' . $key, // unique id
						$params['box_title'],
						array( $this, $ui_mode == 'edit' ? 'print_meta_box_edit' : 'print_meta_box_view' ), 
						$params[ $iam ][ 'object_name' ],
						$params[ $reverse ][ 'box_context' ],
						$params[ $reverse ][ 'box_priority' ],
						array (
							'key'    => $key,
							'iam'    => $iam,
							'params' => $params
						)
					);
				}
			}
		}
	}
	
	/**
	 * Print metaboxes, edition mode
	 *
	 * @since 1.0.0
	 *
	 */
	function print_meta_box_edit( $post, $args ) {

		require( P2P_REL_PATH . 'inc/print_meta_box_edit.php');		
	}

	/**
	 * Print metaboxes, only view relationships
	 *
	 * @since 1.0.0
	 *
	 */
	function print_meta_box_view( $post, $args ) {

		require( P2P_REL_PATH . 'inc/print_meta_box_view.php');		
	}

	/************************************************************************
	     Save relationships & post changes sincronization
	 ************************************************************************/
	
	/**
	 * Save postbox info
	 *
	 * @since 1.0.0
	 *
	 */
	function save_post( $post_id, $post, $update ) {
		require( P2P_REL_PATH . 'inc/save_meta_boxes.php');		
	}
	
	/**
	 * When post has been deleted
	 *
	 * @since 1.0.0
	 *
	 */
	function deleted_post( $post_id ) {

		$args = array (
			'key'     			=> '',
			'element_id'        => $post_id, 
			'element_type'      => 'post',
		);
		
		$this->delete_raw ( $args );
	}
	
	/**
	 * When post changes the status
	 *
	 * @since 1.0.0
	 *
	 */
	function transition_post_status ( $new_status, $old_status, $post ) {
		
		$args = array (
			'element_id'        => $post->ID, 
			'element_type'      => 'post',
			'element_status'    => $new_status
		);
		
		$this->update_status ( $args );
	}
	
	
	/************************************************************************
	     AJAX Admin metaboxes (edit pages)
	 ************************************************************************/

	/**
	 * Admin AJAX call, populate with available elements for relationships
	 *
	 * @since 1.0.0
	 *
	 */
	function admin_ajax() {

		// Sanitize request data
		$what  = isset($_GET['what'])  ? sanitize_key($_GET['what']) : '';
		$key   = isset($_GET['key'])   ? sanitize_key($_GET['key'])  : '';
		$iam   = isset($_GET['iam'])   ? sanitize_key($_GET['iam'])  : '';
		$s     = isset($_GET['s'])     ? trim( sanitize_key($_GET['s']) ) : '';
		$type  = isset($_GET['type'])  ? trim( sanitize_key($_GET['type']) ) : '';
		$tax   = isset($_GET['tax'])   ? trim( sanitize_key($_GET['tax']) ) : '';
		
		switch( $what ) {
			
			case 'choose-relations':
			
				if ( $key == '' || !isset( $this->options['relationships'][$key] ) || ( $iam != 'from' && $iam != 'to') ) {
					
					echo '0';
					exit();
				}

				$reverse = ($iam == 'from') ? 'to' : 'from';
								
				$args = array (
							'numberposts' => -1, 
							'post_type'   => $this->options['relationships'][$key][$reverse]['object_name'],
							'post_status' => array ( 'publish', 'draft', 'future', 'pending' ),
							'suppress_filters' => false
				);
				
				// Optional search text filter 
				if ( $s != '') $args['s'] = $s;

				// Optional type filter 
				if ( $type != '') $args['post_type'] = $type;

				// Optional type filter 
				$tax_term = explode('-tax_term-', $tax);
				if ( is_array($tax_term) && count( $tax_term ) == 2 ) {
					
					$args['tax_query'] = array(
								array(
									'taxonomy'  => $tax_term[0],
									'field'     => 'id',
									'terms'     => $tax_term[1]
								)
							);
				}
 
				$targets = get_posts( $args );
				
				if ( is_array( $targets ) && count($targets) > 0 ) {
					
					echo '<ul>';
					
					foreach ( $targets as $target ) 
					{
						echo '<li data-id="' . esc_attr($target->ID) . '">#' . esc_html($target->ID);
						echo ' <span class="title">';
						echo $target->post_status == 'publish' ? '' : '<em class="'.esc_attr('status_'.$target->post_status).'">';
						echo esc_html($target->post_title);
						echo $target->post_status == 'publish' ? '' : ( '</em> [' . esc_html($target->post_status) . ']' );
						echo '</span></li>';
					}
					echo '</ul>';
				} else {
					echo '<p class="no_results">' . esc_html__( 'No results', 'posts-2-posts-relationships' ) . '</p>';
				}
				
				exit();
				
				break;
		}

		echo '0';
		
		exit();
	}

	/************************************************************************
	     3rd party
	 ************************************************************************/

	/**
	 * Get post lang per ID, compatible with WPML and Polylang
	 *
	 * @since 1.0.0
	 *
	 */
	function get_post_lang ( $post_id ) {
		
		// WPML
		if ( defined('ICL_SITEPRESS_VERSION') && defined('WCML_VERSION') ) {
			$wpml_details = apply_filters( 'wpml_post_language_details', NULL, $post_id );
			if ( is_array( $wpml_details ) && isset( $wpml_details['locale'] ) ) return $wpml_details['locale'];
		}
		
		// Polylang
		if ( function_exists( 'pll_get_post_language' ) ) {
			return pll_get_post_language( $post_id, 'locale' );
		}
		
		return get_locale();
	}

	/************************************************************************
	     Low level relationships database queries 
		 (used by other functions)
	 ************************************************************************/

	/**
	 * Get raw info from relationships database table
	 *
	 * @since 1.0.0
	 *
	 */
	public function get_raw( $args ) {
		
		global $post, $wpdb;

		// Defaults parsed with params
		$defaults = array(
			'key'     			=> '',
			'element_id'        => false,    // Post ID optional inside loop
			'element_type'      => 'post',   // only posts (pages, custom post types, etc) for now (users and taxonomies in future)
			'status'            => 'publish', // status, array of status or 'any'
			'direction'         => 'any',    // any | from_to | to_from
		);
		$parsed_args = wp_parse_args( $args, $defaults );
		
		// Post ID optional inside loop, here we will get it:
		if ( $parsed_args['element_id'] != 'all' && $parsed_args['element_id'] == false ) {
			if ( !isset( $post->ID ) ) return false;
			$parsed_args['element_id'] = $post->ID;
		}
		
		// Checking key
		if ( $parsed_args['key'] != '' && !isset( $this->options['relationships'][ $parsed_args['key'] ] ) ) return false;

		// Only posts for now (users and taxonomies in future)
		if ( $parsed_args['element_type'] != 'post' ) return false;

		// Build SQL Query
		$sql = "SELECT from_id, to_id FROM {$wpdb->prefix}p2p_relationships WHERE 1=1";
		
		if ( $parsed_args['key'] != '' ) {
			
			$sql = $wpdb->prepare( $sql . ' AND rel_key=%s', $parsed_args['key'] );
		}
		
		if ( $parsed_args['element_id'] != 'all' ) {
			switch ( $parsed_args['direction'] ) {
				
				case 'from_to' :
					$sql = $wpdb->prepare( $sql . ' AND from_id=%d', $parsed_args['element_id'] );
					break;

				case 'to_from' :
					$sql = $wpdb->prepare( $sql . ' AND to_id=%d', $parsed_args['element_id'] );
					break;
			
				case 'any' :
				default :
					$sql = $wpdb->prepare( $sql . ' AND ( from_id=%d OR to_id=%d )', $parsed_args['element_id'], $parsed_args['element_id'] );
					break;
			}
		}

		if ( $parsed_args['status'] != 'any' ) {

			$allowed_status = $parsed_args['status'];
			if ( !is_array ($allowed_status) ) $allowed_status = array ( $allowed_status );

			$sql .=  " AND from_status IN ('" . implode( "','" , esc_sql($allowed_status) ) . "')" .
					" AND to_status IN ('" . implode( "','" , esc_sql($allowed_status) ) . "')";
		}

		// Retrieve data and put it into array
		$data = $wpdb->get_results( $sql );
		
		$results = array();
		foreach ( $data as $relationship ) {
			
			if ( $relationship->from_id != $parsed_args['element_id'] ) {
				
				$results[] = $relationship->from_id;
			} else {
				$results[] = $relationship->to_id;
			}
		}
		return $results;
	}

	/**
	 * Delete raw info from relationships database table
	 *
	 * @since 1.0.0
	 *
	 */
	public function delete_raw( $args ) {
		
		global $post, $wpdb;

		// Defaults parsed with params
		$defaults = array(
			'key'     			=> '',
			'element_id'        => false,    // Post ID optional inside loop
			'element_type'      => 'post',   // only posts (pages, custom post types, etc) for now (users and taxonomies in future)
			'status'            => 'any', // status, array of status or 'any'
			'direction'         => 'any',    // any | from_to | to_from
		);
		$parsed_args = wp_parse_args( $args, $defaults );
		
		// Key or element_id should be specified
		if ( $parsed_args['key'] == '' && $parsed_args['element_id'] == false ) return false;
		
		// Only posts for now (users and taxonomies in future)
		if ( $parsed_args['element_type'] != 'post' ) return false;

		// Build SQL Query
		$sql   = '';
		$sql_2 = '';
		
		if ( $parsed_args['key'] != '' ) {
			
			$sql = $wpdb->prepare( $sql . ' AND rel_key=%s', $parsed_args['key'] );
		}
		
		// Prepare status into array if needed
		if ( $parsed_args['status'] != 'any' ) {
			$delete_status = $parsed_args['status']; 
			if ( !is_array($delete_status) ) $delete_status = array ($delete_status);
		}
		
		switch ( $parsed_args['direction'] ) {
			
			case 'from_to' :
				if ( $parsed_args['element_id'] != 'all' ) {
					
					$sql = $wpdb->prepare( $sql . ' AND from_id=%d', $parsed_args['element_id'] );
				}
				if ( $parsed_args['status'] != 'any' ) {
										
					$sql = $sql . " AND from_status IN ('" . implode( "','" , esc_sql($delete_status) ) . "')";
				}
				break;

			case 'to_from' :
				if ( $parsed_args['element_id'] != 'all' ) {
					
					$sql = $wpdb->prepare( $sql . ' AND to_id=%d', $parsed_args['element_id'] );
				}
				if ( $parsed_args['status'] != 'any' ) {
										
					$sql = $sql . " AND to_status IN ('" . implode( "','" , esc_sql($delete_status) ) . "')";
				}
				break;
		
			case 'any' :
			default :
				if ( $parsed_args['element_id'] != 'all' ) {
					if ( $sql_2 == '' ) $sql_2 = $sql;
					$sql   = $wpdb->prepare( $sql . ' AND from_id=%d', $parsed_args['element_id'] );
					$sql_2 = $wpdb->prepare( $sql_2 . ' AND to_id=%d', $parsed_args['element_id'] );
				}
				
				if ( $parsed_args['status'] != 'any' ) {
					if ( $sql_2 == '' ) $sql_2 = $sql;
					$sql   = $sql . " AND from_status IN ('" . implode( "','" , esc_sql($delete_status) ) . "')";
					$sql_2 = $sql_2 . " AND to_status IN ('" . implode( "','" , esc_sql($delete_status) ) . "')";
				}
				break;
		}

		if ( $sql_2 == '' ) {
			$sql = "DELETE FROM {$wpdb->prefix}p2p_relationships WHERE 1=1" . $sql;
		} else {
			$sql = "DELETE FROM {$wpdb->prefix}p2p_relationships WHERE (1=1" . $sql . ") OR ( 1=1" . $sql_2 . ")";
		}
		
		// Run query
		$results = $wpdb->query( $sql );
		
		return $results;
	}
	
	/**
	 * Update element status from relationships database table
	 *
	 * @since 1.0.0
	 *
	 */
	public function update_status( $args ) {

		global $wpdb;
		
		// Defaults parsed with params
		$defaults = array(
			'element_id'        => false,    // Post ID required
			'element_type'      => 'post',   // only posts (pages, custom post types, etc) for now (users and taxonomies in future)
			'element_status'    => false,    // new status required
		);
		$parsed_args = wp_parse_args( $args, $defaults );
				
		// Only posts
		if ( $parsed_args['element_type'] != 'post' ) return false;
		
		// Required ID
		if ( !$parsed_args['element_id'] ) return false;

		// Required status
		if ( !$parsed_args['element_status'] ) return false;

		// Build SQL Query
		$sql_1 = $wpdb->prepare( "UPDATE {$wpdb->prefix}p2p_relationships SET from_status=%s WHERE from_id=%d AND from_type=%s",
							$parsed_args['element_status'], $parsed_args['element_id'], $parsed_args['element_type'] );

		$sql_2 = $wpdb->prepare( "UPDATE {$wpdb->prefix}p2p_relationships SET to_status=%s WHERE to_id=%d AND to_type=%s",
							$parsed_args['element_status'], $parsed_args['element_id'], $parsed_args['element_type'] );
		
		// Run query
		$wpdb->query( $sql_1 );
		$wpdb->query( $sql_2 );
	}

	/************************************************************************
	     Filters for WP_Query
	 ************************************************************************/
	
	/**
	 * Filter where clause when p2p relationships is requested
	 *
	 * @since 1.0.0
	 *
	 */
	function posts_where ($where, $query) {
		
		global $wpdb;
		
		// not p2p relationship query? nothing to do!
		if ( !isset($query->query) || !isset($query->query['p2p_rel_key']) ) return $where;
			
		$key        = $query->query['p2p_rel_key'];
		$post_id    = isset ( $query->query['p2p_rel_post_id'] )   ? $query->query['p2p_rel_post_id']   : false;
		$status     = isset ( $query->query['post_status'] )       ? $query->query['post_status']       : 'publish';
		$direction  = isset ( $query->query['p2p_rel_direction'] ) ? $query->query['p2p_rel_direction'] : 'any';
		
		$args = array (
						'key'           => $key,
						'element_id'    => $post_id,
						'element_type'  => 'post',
						'status'        => $status,
						'direction'     => $direction,
					  );
					  
		$related = $this->get_raw( $args );
		
		// Bad args, maybe unespecified post ID outside loop
		if ( $related === false ) return $where;
		
		if ( is_array($related) && count($related) > 0 ) {
			
			$where .= " AND {$wpdb->prefix}posts.ID IN (" . implode( ',' , esc_sql($related) ) . ")";
			
			//For performance, we save here the ORDER BY
			$query->p2p_rel_order_by = "FIND_IN_SET(ID, '" . implode( ',' , esc_sql($related) ) . "')";
			
		} else {
			
			// No related posts, force 0 results
			$where .= " AND 'p2p_rel' = 'false'"; 
		}

		// Not specified post type? Let's put all relationship post types
		if ( !isset($query->query['post_type']) || $query->query['post_type'] == '' ) {
			
			$post_types_from = $this->options['relationships'][$key]['from']['object_name'];
			$post_types_to   = $this->options['relationships'][$key]['to']['object_name'];
			
			if ( !is_array( $post_types_from ) ) $post_types_from  = array ($post_types_from);
			if ( !is_array( $post_types_to ) )   $post_types_to    = array ($post_types_to);
			
			$post_types = array_merge ( $post_types_from, $post_types_to );
			$where = str_replace("{$wpdb->prefix}posts.post_type = 'post' AND", "{$wpdb->prefix}posts.post_type IN ('" . implode( "', '", esc_sql( $post_types ) ) . "') AND", $where);
		}
		
		return $where;
		
	}

	/**
	 * Filter order by clause when p2p relationships is requested
	 *
	 * @since 1.0.0
	 *
	 */

	function posts_orderby( $orderby, $query ) {
		
		//For performance, previously saved ORDER BY
		if ( isset( $query->p2p_rel_order_by ) ) return $query->p2p_rel_order_by;
		
		return $orderby;	
	}
}

//Plugin activation, install table database
function p2p_rel_activation () {
	require( P2P_REL_PATH . 'inc/plugin_activation.php');		
}
register_activation_hook( __FILE__, 'p2p_rel_activation' );

// Plugin main class instantation
$P2P_Relationships = new P2P_Relationships();



