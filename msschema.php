<?php
/*
Plugin Name: Msschema
Version: 0.1-alpha
Description: PLUGIN DESCRIPTION HERE
Author: YOUR NAME HERE
Author URI: YOUR SITE HERE
Plugin URI: PLUGIN SITE HERE
Text Domain: msschema
Domain Path: /languages
Network: true
*/

function msschema_init() {
	if ( ! is_multisite() ) {
		return;
	}

	if ( is_network_admin() && is_super_admin() ) {
		require __DIR__ . '/includes/admin.php';
	}
}
add_action( 'plugins_loaded', 'msschema_init' );


function msschema_filter_query( $query ) {
	global $wpdb;

	// Swap the table names
	$table_hash = msschema_get_table_hash( $wpdb->blogid );;
	$new_query = str_replace( array_keys( $table_hash ), array_values( $table_hash ), $query );

	if ( $new_query !== $query ) {
		// Add the WHERE clause
		// Sloppy. We're assuming there's always one
		// $query [sic]. We're replacing the return variable
		$query = str_replace( 'WHERE', 'WHERE blog_id = ' . (int) $wpdb->blogid . ' AND', $new_query );
	}


	return $query;
}
add_filter( 'query', 'msschema_filter_query' );

function msschema_get_table_hash( $blog_id ) {
	static $table_hashes;
	global $wpdb;

	if ( ! isset( $table_hashes ) ) {
		$table_hashes = array();
	}

	if ( ! isset( $table_hashes[ $blog_id ] ) ) {
		remove_filter( 'query', 'msschema_filter_query' );
		$blog_tables = msschema_get_blog_tables( $blog_id );

		foreach ( $blog_tables as $blog_table ) {
			$tbase = substr( $blog_table, strlen( $wpdb->get_blog_prefix( $blog_id ) ) );
			$table_hashes[ $blog_id ][ $blog_table ] = $wpdb->base_prefix . $tbase;
		}
		add_filter( 'query', 'msschema_filter_query' );
	}

	return $table_hashes[ $blog_id ];
}

function msschema_get_global_tables() {
	global $wpdb;

	$global_tables = array();

	foreach ( $wpdb->global_tables as $gt ) {
		$global_tables[] = $wpdb->base_prefix . $gt;
	}

	foreach ( $wpdb->ms_global_tables as $mgt ) {
		$global_tables[] = $wpdb->base_prefix . $mgt;
	}

	return $global_tables;
}

function msschema_get_blog_tables( $blog_id ) {
	global $wpdb;

	if ( $blog_id == 1 ) {
		return msschema_get_root_blog_tables();
	} else {
		$blog_prefix = $wpdb->get_blog_prefix( $blog_id );
		$all_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$blog_prefix}%'" );
		$tables = array_diff( $all_tables, msschema_get_global_tables(), msschema_get_root_blog_tables() );
		return $tables;
	}
}

function msschema_get_root_blog_tables() {
	global $wpdb;

	$global_tables = msschema_get_global_tables();

	$tables = array();
	$all_tables = $wpdb->get_col( "SHOW TABLES" );

	foreach ( $all_tables as $all_table ) {
		if ( in_array( $all_table, $global_tables ) ) {
			continue;
		}

		// Hack. If the first character after 'wp_' is a number,
		// assume this is a secondary table, and skip
		if ( is_numeric( substr( $all_table, strlen( $wpdb->base_prefix ), 1 ) ) ) {
			continue;
		}

		$tables[] = $all_table;
	}

	return $tables;
}

