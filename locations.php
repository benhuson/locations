<?php

/*
Plugin Name: Locations
Plugin URI: http://www.benhuson.co.uk/
Description: Manage locations. Suitable for creating a stockists list, store locations, contact list etc.
Version: 0.1
Author: Ben Huson
Author URI: http://www.benhuson.co.uk/
License: GPLv2
*/

class Locations {

	/**
	 * Constructor
	 */
	function Locations() {
		add_action( 'init', array( $this, 'register_post_types' ), 9 );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 5 );
		add_action( 'save_post', array( $this, 'save_post' ) );
		add_action( 'pre_post_update', array( $this, 'pre_post_update' ) );
		add_filter( 'manage_edit-location_columns', array( $this, 'manage_edit_location_columns' ) );
		add_action( 'manage_location_posts_custom_column', array( $this, 'manage_location_posts_custom_column' ), 10, 2 );
		add_filter( 'manage_edit-locationregion_columns', array( $this, 'manage_edit_locationregion_columns' ) );
		add_action( 'manage_locationregion_posts_custom_column', array( $this, 'manage_locationregion_posts_custom_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts' ) );
		add_filter( 'parse_query', array( $this, 'filter_admin_locations_list' ) );
		add_shortcode( 'locations', array( $this, 'shortcode_locations' ) );
		add_filter( 'http_request_args', 'hide_from_plugin_update', 5, 2 );
	}

	/**
	 * Locations Shortcode
	 */
	function shortcode_locations( $atts, $content = '' ) {
		global $post;
		$output = '';
		$atts = shortcode_atts( array(
			'display' => 'table'
		), $atts );		

		$regions = get_posts( array(
			'numberposts' => -1,
			'post_type'   => 'locationregion',
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		) );
		$locations = get_posts( array(
			'numberposts' => -1,
			'post_type'   => 'location',
			'orderby'     => 'title',
			'order'       => 'ASC',
		) );
		foreach ( $regions as $key => $region ) {
			if ( $region->post_parent > 0 )
				continue;
			$output .= sprintf( '<h2>%s</h2>', $region->post_title );
			$table = '';
			foreach ( $locations as $loc_key => $loc_val ) {
				if ( $loc_val->post_parent == $region->ID ) {
					$table .= sprintf( '<tr><td>%s</td><td>%s</td><td>%s</td></tr>', $loc_val->post_title, $this->get_location_address_string( $loc_val->ID ), get_post_meta( $loc_val->ID, '_location_contact_telephone', true ) );
				}
			}
			if ( ! empty( $table ) )
				$output .= sprintf( '<table>%s</table>', $table );
			foreach ( $regions as $key2 => $region2 ) {
				if ( $region2->post_parent == $region->ID ) {
					$output .= sprintf( '<h3>%s</h3>', $region2->post_title );
					$table = '';
					foreach ( $locations as $loc_key => $loc_val ) {
						if ( $loc_val->post_parent == $region2->ID ) {
							$table .= sprintf( '<tr><td>%s</td><td>%s</td><td>%s</td></tr>', $loc_val->post_title, $this->get_location_address_string( $loc_val->ID ), get_post_meta( $loc_val->ID, '_location_contact_telephone', true ) );
						}
					}
					if ( ! empty( $table ) )
						$output .= sprintf( '<table>%s</table>', $table );
				}
			}
		}
		return $output;
	}

	/**
	 * Get Location Address as String
	 */
	function get_location_address_string( $post ) {
		$street   = get_post_meta( $post, '_location_address_street', true );
		$locality = get_post_meta( $post, '_location_address_locality', true );
		$region   = get_post_meta( $post, '_location_address_region', true );
		$postcode = get_post_meta( $post, '_location_address_postcode', true );
		$country  = get_post_meta( $post, '_location_address_country', true );
		$address = array();
		if ( ! empty( $street ) )
			$address[] = $street;
		if ( ! empty( $locality ) )
			$address[] = $locality;
		if ( ! empty( $region ) )
			$address[] = $region;
		if ( ! empty( $postcode ) )
			$address[] = $postcode;
		if ( ! empty( $country ) )
			$address[] = $country;
		if ( count( $address ) > 0 )
			return implode( ', ', $address );
		return '';
	}

	/**
	 * Register Post Types
	 */
	function register_post_types() {

		// Regions
		$args = array(
			'labels'             => array(
				'name'               => _x( 'Regions', 'post type general name', 'locations' ),
				'singular_name'      => _x( 'Region', 'post type singular name', 'locations' ),
				'add_new'            => _x( 'Add New', 'location', 'locations' ),
				'add_new_item'       => __( 'Add New Region', 'locations' ),
				'edit_item'          => __( 'Edit Region', 'locations' ),
				'new_item'           => __( 'New Region', 'locations' ),
				'all_items'          => __( 'Regions', 'locations' ),
				'view_item'          => __( 'View Regions', 'locations' ),
				'search_items'       => __( 'Search Regions', 'locations' ),
				'not_found'          => __( 'No regions found', 'locations' ),
				'not_found_in_trash' => __( 'No regions found in Trash', 'locations' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Regions', 'locations' )
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true, 
			'show_in_menu'       => 'edit.php?post_type=location', 
			'query_var'          => true,
			'rewrite'            => array( 'slug' => _x( 'locations', 'URL slug', 'locations' ) ),
			'capability_type'    => 'post',
			'has_archive'        => true, 
			'hierarchical'       => true,
			'menu_position'      => null,
			'supports'           => array( 'title', 'page-attributes', 'thumbnail', 'excerpt' )
		); 
		register_post_type( 'locationregion', $args );

		// Locations
		$args = array(
			'labels'             => array(
				'name'               => _x( 'Locations', 'post type general name', 'locations' ),
				'singular_name'      => _x( 'Location', 'post type singular name', 'locations' ),
				'add_new'            => _x( 'Add New Location', 'location', 'locations' ),
				'add_new_item'       => __( 'Add New Location', 'locations' ),
				'edit_item'          => __( 'Edit Location', 'locations' ),
				'new_item'           => __( 'New Location', 'locations' ),
				'all_items'          => __( 'All Locations', 'locations' ),
				'view_item'          => __( 'View Locations', 'locations' ),
				'search_items'       => __( 'Search Locations', 'locations' ),
				'not_found'          => __( 'No locations found', 'locations' ),
				'not_found_in_trash' => __( 'No locations found in Trash', 'locations' ), 
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Locations', 'locations' )
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true, 
			'show_in_menu'       => true, 
			'query_var'          => true,
			'rewrite'            => array( 'slug' => _x( 'locations', 'URL slug', 'locations' ) ),
			'capability_type'    => 'post',
			'has_archive'        => true, 
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' )
		); 
		register_post_type( 'location', $args );
		remove_post_type_support( 'location', 'editor' );
		remove_post_type_support( 'location', 'excerpt' );
		remove_post_type_support( 'location', 'thumbnail' );
	}

	/**
	 * Post Updated Messages
	 */
	function post_updated_messages( $messages ) {
		global $post, $post_ID;
		$messages['location'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __( 'Location updated. <a href="%s">View location</a>', 'locations' ), esc_url( get_permalink( $post_ID ) ) ),
			2 => __( 'Custom field updated.', 'locations' ),
			3 => __( 'Custom field deleted.', 'locations' ),
			4 => __( 'Location updated.', 'locations' ),
			/* Translators: %s: date and time of the revision */
			5 => isset( $_GET['revision'] ) ? sprintf( __( 'Location restored to revision from %s', 'locations' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __( 'Location published. <a href="%s">View location</a>', 'locations' ), esc_url( get_permalink( $post_ID ) ) ),
			7 => __( 'Location saved.', 'locations' ),
			8 => sprintf( __( 'Location submitted. <a target="_blank" href="%s">Preview location</a>', 'locations' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			9 => sprintf( __( 'Location scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview location</a>', 'locations' ),
			// Translators: Publish box date format, see http://php.net/date
			date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( __( 'Location draft updated. <a target="_blank" href="%s">Preview location</a>', 'locations' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);
		return $messages;
	}

	/**
	 * Add Post Edit List Filters
	 */
	function restrict_manage_posts() {
		global $post;
		if ( 'location' != get_query_var( 'post_type' ) )
			return;
		$post_parent = isset( $_GET['post_parent'] ) ? $_GET['post_parent'] : '';
		echo wp_dropdown_pages( array(
			'echo'              => 0,
			'name'              => 'post_parent',
			'post_type'         => 'locationregion',
			'selected'          => $post_parent,
			'show_option_none'  => __( 'View all locations', 'loc' ),
			'option_none_value' => ''
		) );
	}

	function filter_admin_locations_list( $query ) {
		global $pagenow;
		$qv = &$query->query_vars;
		if ( $pagenow=='edit.php' && isset($qv['post_type']) && 'location' == $qv['post_type'] ) {
			if ( isset( $_GET['post_parent'] ) )
				$qv['post_parent'] = $_GET['post_parent'];
		}
	}

	/**
	 * Add Meta Boxes
	 */
	function add_meta_boxes() {
		add_meta_box( 'locationregion', __( 'Region', 'locations' ), array( $this, 'locationregion_meta_box' ), 'location', 'side' );
		add_meta_box( 'locaddress', __( 'Address', 'locations' ), array( $this, 'locaddress_meta_box' ), 'location', 'normal' );
		add_meta_box( 'loccontact', __( 'Contact Details', 'locations' ), array( $this, 'loccontact_meta_box' ), 'location', 'normal' );
	}

	/**
	 * Location Region Meta Boxes
	 */
	function locationregion_meta_box() {
		global $post;
		echo wp_dropdown_pages( array(
			'echo'              => 0,
			'name'              => 'parent_id',
			'post_type'         => 'locationregion',
			'selected'          => $post->post_parent,
			'show_option_none'  => __( '–– None ––', 'loc' ),
			'option_none_value' => 0
		) );
		wp_nonce_field( 'update_location_locationregion', '_nonce_location_locationregion' );
	}

	/**
	 * Location Address Meta Box
	 */
	function locaddress_meta_box() {
		global $post;
		echo '<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><label for="location_address_aptno">' . __( 'Apartment / Room Number', 'locations' ) . '</label></th>
						<td><input name="location_address_aptno" type="text" id="location_address_aptno" value="' . get_post_meta( $post->ID, '_location_address_aptno', true ) . '" class="regular-text" placeholder="' . __( 'e.g. Apartment or suite number', 'locations' ) . '" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="location_address_street">' . __( 'Street Address', 'locations' ) . '</label></th>
						<td><input name="location_address_street" type="text" id="location_address_street" value="' . get_post_meta( $post->ID, '_location_address_street', true ) . '" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="location_address_locality">' . __( 'Locality', 'locations' ) . '</label></th>
						<td><input name="location_address_locality" type="text" id="location_address_locality" value="' . get_post_meta( $post->ID, '_location_address_locality', true ) . '" class="regular-text" placeholder="' . __( 'e.g. City', 'locations' ) . '" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="location_address_region">' . __( 'Region', 'locations' ) . '</label></th>
						<td><input name="location_address_region" type="text" id="location_address_region" value="' . get_post_meta( $post->ID, '_location_address_region', true ) . '" class="regular-text" placeholder="' . __( 'e.g. State, county or province', 'locations' ) . '" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="location_address_postcode">' . __( 'Postal Code', 'locations' ) . '</label></th>
						<td><input name="location_address_postcode" type="text" id="location_address_postcode" value="' . get_post_meta( $post->ID, '_location_address_postcode', true ) . '" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="location_address_country">' . __( 'Country', 'locations' ) . '</label></th>
						<td><input name="location_address_country" type="text" id="location_address_country" value="' . get_post_meta( $post->ID, '_location_address_country', true ) . '" class="regular-text" /></td>
					</tr>
				</tbody>
			</table>';
		wp_nonce_field( 'update_location_address', '_nonce_location_address' );
	}

	/**
	 * Location Contact Details Meta Boxe
	 */
	function loccontact_meta_box() {
		global $post;
		echo '<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><label for="location_contact_telephone">' . __( 'Telephone', 'locations' ) . '</label></th>
						<td><input name="location_contact_telephone" type="text" id="location_contact_telephone" value="' . get_post_meta( $post->ID, '_location_contact_telephone', true ) . '" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="location_contact_email">' . __( 'Email', 'locations' ) . '</label></th>
						<td><input name="location_contact_email" type="text" id="location_contact_email" value="' . get_post_meta( $post->ID, '_location_contact_email', true ) . '" class="regular-text" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="location_contact_web">' . __( 'Web', 'locations' ) . '</label></th>
						<td><input name="location_contact_web" type="text" id="location_contact_web" value="' . get_post_meta( $post->ID, '_location_contact_web', true ) . '" class="regular-text" placeholder="http://" /></td>
					</tr>
				</tbody>
			</table>';
		wp_nonce_field( 'update_location_contact', '_nonce_location_contact' );
	}

	/**
	 * Is Updating Location
	 */
	function is_updating_loc( $post_id ) {

		// Check Post Type
		if ( isset( $_POST['post_type'] ) && 'location' == $_POST['post_type'] ) {

			// Check Capabilities
			if ( current_user_can( 'edit_post', $post_id ) ) {

				// Check Revision
				if ( ! wp_is_post_revision( $post_id ) )
					return true;
			}
		}
		return false;
	}

	/**
	 * Is Updating Location Nonce Check
	 * Verify this came from the our screen and with proper authorization,
	 * because save_post can be triggered at other times
	 */
	function is_updating_location_nonce( $post_id, $nonce_name, $nonce_action ) {
		if ( isset( $_POST[$nonce_name] ) && wp_verify_nonce( $_POST[$nonce_name], $nonce_action ) )
			return $this->is_updating_loc( $post_id );
		return false;
	}

	/**
	 * Pre Post Update
	 */
	function pre_post_update( $post_id ) {

		// Check Valid Update
		if ( ! $this->is_updating_location_nonce( $post_id, '_nonce_location_locationregion', 'update_location_locationregion' ) )
			return $post_id;

		// Update Location Region Count
		$p = get_post( $post_id );
		$posts = get_posts( array(
			'post_type'    => 'location',
			'post_parent'  => $p->post_parent,
			'post__not_in' => array( $post_id )
		) );
		update_post_meta( $p->post_parent, 'location_count', count( $posts ) );
	}

	/**
	 * Save Post
	 */
	function save_post( $post_id ) {

		// Update Location Region Count
		if ( $this->is_updating_location_nonce( $post_id, '_nonce_location_locationregion', 'update_location_locationregion' ) ) {
			$p = get_post( $post_id );
			if ( $p->post_parent > 0 ) {
				$posts = get_posts( array(
					'post_type'   => 'location',
					'post_parent' => $p->post_parent
				) );
				update_post_meta( $p->post_parent, 'location_count', count( $posts ) );
			}
		}

		// Update Location Address
		if ( $this->is_updating_location_nonce( $post_id, '_nonce_location_address', 'update_location_address' ) ) {
			$address = shortcode_atts( array(
				'location_address_aptno'     => '',
				'location_address_street'    => '',
				'location_address_locality'  => '',
				'location_address_region'    => '',
				'location_address_postcode'  => '',
				'location_address_country'   => ''
			), $_POST );

			// Validate and save
			foreach ( $address as $key => $val ) {
				if ( empty( $val ) ) {
					delete_post_meta( $post_id, '_' . $key );
				} else {
					update_post_meta( $post_id, '_' . $key, sanitize_text_field( $val ) );
				}
			}
		}

		// Update Location Contact
		if ( $this->is_updating_location_nonce( $post_id, '_nonce_location_contact', 'update_location_contact' ) ) {
			$contact = shortcode_atts( array(
				'location_contact_telephone' => '',
				'location_contact_email'     => '',
				'location_contact_web'       => ''
			), $_POST );

			// Validate and save
			foreach ( $contact as $key => $val ) {
				if ( empty( $val ) ) {
					delete_post_meta( $post_id, '_' . $key );
				} else {
					switch ( $key ) {
						case 'location_contact_email':
							$val = sanitize_email( $val );
							break;
						case 'location_contact_web':
							$val = esc_url_raw( $val );
							break;
						default;
							$val = sanitize_text_field( $val );
							break;
					}
					update_post_meta( $post_id, '_' . $key, $val );
				}
			}
		}
	}

	/**
	 * Insert Manage Column
	 * Allows easy insertion of a manage column before or after an existing column.
	 */
	function insert_manage_column( $columns, $column, $value, $location = '', $before = false ) {
		if ( ! empty( $location ) && array_key_exists( $location, $columns ) ) {
			$new_columns = array();
			foreach ( $columns as $c => $v ) {
				if ( $before && $c == $location ) 
					$new_columns[$column] = $value;
				$new_columns[$c] = $v;
				if ( ! $before && $c == $location ) 
					$new_columns[$column] = $value;
			}
			$columns = $new_columns;
		} else {
			$columns[$column] = $value;
		}
		return $columns;
	}

	/**
	 * Add Location Manage Columns
	 */
	function manage_edit_location_columns( $columns ) {
		$columns = $this->insert_manage_column( $columns, 'locationregion', __( 'Region', 'loc' ), 'date', true );
		return $columns;
	}

	/**
	 * Add Location Manage Column Data
	 */
	function manage_location_posts_custom_column( $column, $post_id ) {
		global $post;
		switch( $column ) {
			case 'locationregion' :
				echo sprintf( '<a href="%s">%s</a>', get_edit_post_link( $post->post_parent ), get_the_title( $post->post_parent ) );
				break;
			default :
				break;
		}
	}

	/**
	 * Add Location Region Manage Columns
	 */
	function manage_edit_locationregion_columns( $columns ) {
		$columns = $this->insert_manage_column( $columns, 'location_count', __( 'Locations', 'loc' ), 'date', true );
		return $columns;
	}

	/**
	 * Add Location Region Manage Column Data
	 */
	function manage_locationregion_posts_custom_column( $column, $post_id ) {
		global $post;
		switch( $column ) {
			case 'location_count' :
				$count = get_post_meta( $post_id, 'location_count', true );
				if ( ! empty( $count ) && is_numeric( $count ) )
					echo sprintf( '<a href="%s">%s</a>', add_query_arg( 'post_parent', $post_id, admin_url( 'edit.php?post_type=location' ) ), sprintf( _n( '1 location', '%s locations', $count, 'locations' ), $count ) );
				break;
			default :
				break;
		}
	}

	/**
	 * Flush Rewrite Rules
	 */
	function flush_rewrite_rules() {
		$this->register_post_types();
		flush_rewrite_rules();
	}

	/**
	 * Hide From Plugin Updates
	 */
	function hide_from_plugin_update( $r, $url ) {
		if ( 0 !== strpos( $url, 'http://api.wordpress.org/plugins/update-check' ) )
			return $r; // Not a plugin update request. Bail immediately.
		$plugins = unserialize( $r['body']['plugins'] );
		unset( $plugins->plugins[ plugin_basename( __FILE__ ) ] );
		unset( $plugins->active[ array_search( plugin_basename( __FILE__ ), $plugins->active ) ] );
		$r['body']['plugins'] = serialize( $plugins );
		return $r;
	}

}

global $locations;
$locations = new Locations();
register_activation_hook( __FILE__, array( $locations, 'flush_rewrite_rules' ) );
