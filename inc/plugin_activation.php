<?php
/**
 * Plugin activation
 *
 * @package Posts 2 Posts Relationships
 * @version 1.0.0
 */
 
defined( 'ABSPATH' ) || exit;

// We're inside function p2p_rel_activation():
global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

$table_name = $wpdb->prefix . 'p2p_relationships';

if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {

	$sql = "CREATE TABLE $table_name (
		rel_id       bigint(20)   NOT NULL AUTO_INCREMENT,
		rel_key      varchar(200) NOT NULL,
		from_type    varchar(200) NOT NULL,
		from_name    varchar(200) NOT NULL,
		from_id      bigint(20)   NOT NULL,
		from_status  varchar(20)  NOT NULL,
		from_lang    varchar(20)  NOT NULL,
		to_type      varchar(200) NOT NULL,
		to_name      varchar(200) NOT NULL,
		to_id        bigint(20)   NOT NULL,
		to_status    varchar(20)  NOT NULL,
		to_lang      varchar(20)  NOT NULL,
		UNIQUE KEY id (rel_id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}
?>
