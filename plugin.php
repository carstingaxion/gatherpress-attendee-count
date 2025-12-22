<?php
/**
 * Plugin Name:       GatherPress Attendee Count
 * Description:       Monitor GatherPress events with missing attendee counts directly from your WordPress dashboard.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            carstenbach & WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gatherpress-attendee-count
 *
 * @package GatherPressAttendeeCount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add dashboard widget for monitoring GatherPress events.
 */
function gatherpress_attendee_count_add_dashboard_widget() {
	wp_add_dashboard_widget(
		'gatherpress_attendee_count_widget',
		__( 'Events Missing Attendee Count', 'gatherpress-attendee-count' ),
		'gatherpress_attendee_count_render_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'gatherpress_attendee_count_add_dashboard_widget' );

/**
 * Enqueue dashboard widget scripts and styles.
 */
function gatherpress_attendee_count_enqueue_dashboard_scripts( $hook ) {
	if ( 'index.php' !== $hook ) {
		return;
	}

	// Enqueue dashboard widget CSS
	wp_enqueue_style( 
		'gatherpress-attendee-count-dashboard', 
		plugins_url( 'assets/css/dashboard-widget.css', __FILE__ ),
		array(),
		'0.1.0'
	);

	// Enqueue dashboard widget JS
	wp_enqueue_script( 
		'gatherpress-attendee-count-dashboard', 
		plugins_url( 'assets/js/dashboard-widget.js', __FILE__ ),
		array( 'jquery' ),
		'0.1.0',
		true
	);

	// Localize script with dynamic PHP values
	wp_localize_script( 
		'gatherpress-attendee-count-dashboard', 
		'gatherpressAttendeeCount', 
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gatherpress_attendee_count_nonce' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'gatherpress_attendee_count_enqueue_dashboard_scripts' );

/**
 * Enqueue scripts and styles for post list table inline editing.
 */
function gatherpress_attendee_count_enqueue_list_table_scripts( $hook ) {
	if ( 'edit.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen(); 
	if ( ! $screen || 'gatherpress_event' !== $screen->post_type ) {
		return;
	}

	$can_edit = current_user_can( 'edit_posts' ); 

	// Enqueue list table CSS
	wp_enqueue_style( 
		'gatherpress-attendee-count-list-table', 
		plugins_url( 'assets/css/list-table.css', __FILE__ ),
		array(),
		'0.1.0'
	);

	// Enqueue list table JS
	wp_enqueue_script( 
		'gatherpress-attendee-count-list-table', 
		plugins_url( 'assets/js/list-table.js', __FILE__ ),
		array( 'jquery' ),
		'0.1.0',
		true
	);

	// Localize script with dynamic PHP values
	wp_localize_script( 
		'gatherpress-attendee-count-list-table', 
		'gatherpressAttendeeCountListTable', 
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gatherpress_attendee_count_nonce' ),
			'canEdit' => $can_edit, 
		)
	);
}
add_action( 'admin_enqueue_scripts', 'gatherpress_attendee_count_enqueue_list_table_scripts' );

/**
 * Get events with missing attendee counts from transient or query.
 *
 * @return WP_Query The query object with events.
 */
function gatherpress_attendee_count_get_events() {
	$transient_key = 'gatherpress_attendee_count_events';
	$cached_events = get_transient( $transient_key );

	if ( false !== $cached_events ) {
		return $cached_events;
	}

	$args = array(
		'post_type'      => 'gatherpress_event',
		'gatherpress_event_query' => 'past',
		'posts_per_page' => 100,
		'post_status'    => array( 'publish' ),
		'orderby'        => 'event_date',
		// 'orderby'        => 'meta_value',
		// 'meta_key'       => 'gatherpress_datetime_start',
		'order'          => 'DESC',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => 'gatherpress_datetime_start',
				'compare' => 'EXISTS',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => 'gatherpress_attendee_count',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'gatherpress_attendee_count',
					'compare' => '=',
					'value'   => '',
				),
			),
		),
	);

	$query = new WP_Query( $args );

	set_transient( $transient_key, $query, 7 * DAY_IN_SECONDS );

	return $query;
}

/**
 * Clear the events transient cache.
 */
function gatherpress_attendee_count_clear_events_cache() {
	delete_transient( 'gatherpress_attendee_count_events' );
}

/**
 * Render the dashboard widget content.
 */
function gatherpress_attendee_count_render_dashboard_widget() {
	$query = gatherpress_attendee_count_get_events();

	if ( $query->have_posts() ) {
		echo '<div id="activity-widget">';
		echo '<div id="gatherpress-events-list" class="activity-block">';
		echo '<ul>';

		// $today    = current_time( 'Y-m-d' );
		// $yesterday = current_datetime()->modify( '-1 day' )->format( 'Y-m-d' );
		// $year     = current_time( 'Y' );

		// $time = get_the_time( 'U' );

		// if ( gmdate( 'Y-m-d', $time ) === $today ) {
		// 	$relative = __( 'Today' );
		// } elseif ( gmdate( 'Y-m-d', $time ) === $yesterday ) {
		// 	$relative = __( 'Yesterday' );
		// } elseif ( gmdate( 'Y', $time ) !== $year ) {
		// 	/* translators: Date and time format for recent posts on the dashboard, from a different calendar year, see https://www.php.net/manual/datetime.format.php */
		// 	$relative = date_i18n( __( 'M jS Y' ), $time );
		// } else {
		// 	/* translators: Date and time format for recent posts on the dashboard, see https://www.php.net/manual/datetime.format.php */
		// 	$relative = date_i18n( __( 'M jS' ), $time );
		// }

		while ( $query->have_posts() ) {
			$query->the_post();
			$event_id = get_the_ID();
			$edit_link = get_edit_post_link( $event_id );

			// Get event datetime from GatherPress meta
			$gatherpress_datetime = get_post_meta( $event_id, 'gatherpress_datetime_start', true );
			if ( ! empty( $gatherpress_datetime ) ) {
				$event_datetime = strtotime( $gatherpress_datetime );
				// $event_date = date_i18n( 'j. M, ' . get_option( 'time_format' ), $event_datetime );
			} else {
				// Fallback to post date if GatherPress meta doesn't exist
				$event_datetime = get_the_date( 'U', $event_id );
				// $event_date = date_i18n( 'd. M, H:i', $event_datetime );
			}
			$event_date = sprintf(
				// _x( '%1$s ago', '%2$s = human-readable time difference', 'wpdocs_textdomain' ),
				_x( 'vor %1$s', '%2$s = human-readable time difference', 'wpdocs_textdomain' ),
				human_time_diff( $event_datetime, current_time( 'timestamp' ) )
			);


			// Get venue from taxonomy
			$venue_terms = get_the_terms( $event_id, '_gatherpress_venue' );
			$venue_name = '';
			if ( ! empty( $venue_terms ) && ! is_wp_error( $venue_terms ) ) {
				$venue_name = $venue_terms[0]->name;
			}

			echo '<li class="gatherpress-event-item" data-event-id="' . esc_attr( $event_id ) . '">';
			echo '<span class="gatherpress-event-date">' . esc_html( $event_date ) . '</span>';
			echo '<div class="gatherpress-event-info">';
			echo '<span class="gatherpress-event-title">';
			echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( get_the_title() ) . '</a>';
			echo '</span>';
			
			if ( ! empty( $venue_name ) ) {
				echo '<span class="gatherpress-event-venue">' . esc_html( $venue_name ) . '</span>';
			}
			
			echo '<div class="gatherpress-count-container">';
			echo '<span class="button gatherpress-count-value" data-event-id="' . esc_attr( $event_id ) . '">—</span>';
			echo '</div>';
			echo '</div>';
			echo '</li>';
		}

		echo '</ul>';
		echo '</div>';
		echo '</div>';
	} else {
		echo '<div class="no-activity">';
		echo '<p class="smiley" aria-hidden="true"></p>';
		echo '<p>' . esc_html__( 'All events have attendee count information!', 'gatherpress-attendee-count' ) . '</p>';
		echo '</div>';
	}

	wp_reset_postdata();
}

/**
 * AJAX handler to update attendee count.
 */
function gatherpress_attendee_count_update_attendee_count() {
	check_ajax_referer( 'gatherpress_attendee_count_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gatherpress-attendee-count' ) ) );
	}

	$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
	$attendee_count = isset( $_POST['attendee_count'] ) ? intval( $_POST['attendee_count'] ) : 0;

	if ( ! $event_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid event ID.', 'gatherpress-attendee-count' ) ) );
	}

	if ( $attendee_count < 1 ) {
		wp_send_json_error( array( 'message' => __( 'Attendee count must be at least 1.', 'gatherpress-attendee-count' ) ) );
	}

	$updated = update_post_meta( $event_id, 'gatherpress_attendee_count', $attendee_count );

	if ( $updated ) {
		// Clear the transient cache
		gatherpress_attendee_count_clear_events_cache();

		wp_send_json_success( array(
			'message' => __( 'Attendee count updated successfully.', 'gatherpress-attendee-count' ),
			'event_id' => $event_id,
		) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to update attendee count.', 'gatherpress-attendee-count' ) ) );
	}
}
add_action( 'wp_ajax_gatherpress_attendee_count_update_attendee_count', 'gatherpress_attendee_count_update_attendee_count' );

/**
 * AJAX handler to delete attendee count (revert to empty).
 */
function gatherpress_attendee_count_delete_attendee_count() {
	check_ajax_referer( 'gatherpress_attendee_count_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'gatherpress-attendee-count' ) ) );
	}

	$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;

	if ( ! $event_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid event ID.', 'gatherpress-attendee-count' ) ) );
	}

	$deleted = delete_post_meta( $event_id, 'gatherpress_attendee_count' );

	if ( $deleted ) {
		// Clear the transient cache
		gatherpress_attendee_count_clear_events_cache();

		wp_send_json_success( array(
			'message' => __( 'Attendee count cleared successfully.', 'gatherpress-attendee-count' ),
			'event_id' => $event_id,
		) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to clear attendee count.', 'gatherpress-attendee-count' ) ) );
	}
}
add_action( 'wp_ajax_gatherpress_attendee_count_delete_attendee_count', 'gatherpress_attendee_count_delete_attendee_count' );

/**
 * Add custom column to GatherPress events list table.
 */
function gatherpress_attendee_count_add_attendee_count_column( $columns ) {
	$new_columns = array();
	
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		
		if ( 'title' === $key ) {
			$new_columns['gatherpress_attendee_count'] = __( 'Attendees', 'gatherpress-attendee-count' );
		}
	}
	
	return $new_columns;
}
add_filter( 'manage_gatherpress_event_posts_columns', 'gatherpress_attendee_count_add_attendee_count_column' );

/**
 * Display attendee count value in the custom column.
 */
function gatherpress_attendee_count_display_attendee_count_column( $column, $post_id ) {
	if ( 'gatherpress_attendee_count' === $column ) {
		$attendee_count = get_post_meta( $post_id, 'gatherpress_attendee_count', true );
		$can_edit = current_user_can( 'edit_posts' );
		
		echo '<div class="gatherpress-count-container">';
		
		if ( '' !== $attendee_count && null !== $attendee_count ) {
			if ( $can_edit ) {
				echo '<span class="button gatherpress-count-value" data-event-id="' . esc_attr( $post_id ) . '" data-value="' . esc_attr( $attendee_count ) . '"><span>' . esc_html( $attendee_count ) . '</span></span>';
			} else {
				echo '<span><span>' . esc_html( $attendee_count ) . '</span></span>';
			}
		} else {
			if ( $can_edit ) {
				echo '<span class="button gatherpress-count-value gatherpress-empty" data-event-id="' . esc_attr( $post_id ) . '" data-value="—">—</span>';
			} else {
				echo '<span style="color: #d63638;">—</span>';
			}
		}
		
		echo '</div>';
	}
}
add_action( 'manage_gatherpress_event_posts_custom_column', 'gatherpress_attendee_count_display_attendee_count_column', 10, 2 );