<?php
/**
 * Plugin Name:       GatherPress Attendee Count
 * Description:       Monitor GatherPress events with missing attendee counts directly from your WordPress dashboard.
 * Version:           0.1.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Requires Plugins:  gatherpress
 * Author:            carstenbach & WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gatherpress-attendee-count
 *
 * @package GatherPressAttendeeCount
 */

namespace GatherPress\AttendeeCount;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class implementing Singleton pattern.
 *
 * This class handles all plugin functionality including:
 * - Dashboard widget for monitoring events
 * - Admin list table column customization
 * - AJAX handlers for inline editing
 * - Transient caching for performance
 *
 * @since 0.1.0
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 0.1.0
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Transient key for caching event queries.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const TRANSIENT_KEY = 'gatherpress_attendee_count_events';

	/**
	 * Transient expiration time in seconds (7 days).
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private const TRANSIENT_EXPIRATION = 7 * DAY_IN_SECONDS;

	/**
	 * Post meta key for attendee count.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const META_KEY = 'gatherpress_attendee_count';

	/**
	 * GatherPress event post type.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const POST_TYPE = 'gatherpress_event';

	/**
	 * AJAX nonce action.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const NONCE_ACTION = 'gatherpress_attendee_count_nonce';

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @since 0.1.0
	 * @return Plugin The singleton instance.
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function init_hooks(): void {
		// Dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_scripts' ) );

		// List table customization.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_attendee_count_column' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'display_attendee_count_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_table_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_gatherpress_attendee_count_update_attendee_count', array( $this, 'ajax_update_attendee_count' ) );
		add_action( 'wp_ajax_gatherpress_attendee_count_delete_attendee_count', array( $this, 'ajax_delete_attendee_count' ) );
	}

	/**
	 * Register dashboard widget.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function add_dashboard_widget(): void {
		wp_add_dashboard_widget(
			'gatherpress_attendee_count_widget',
			__( 'Events Missing Attendee Count', 'gatherpress-attendee-count' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Enqueue scripts and styles for dashboard widget.
	 *
	 * @since 0.1.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_dashboard_scripts( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'gatherpress-attendee-count',
			plugins_url( 'assets/css/style.css', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/assets/css/style.css' ),
		);

		wp_enqueue_script(
			'gatherpress-attendee-count',
			plugins_url( 'assets/js/index.js', __FILE__ ),
			array( 'jquery' ),
			filemtime( __DIR__ . '/assets/js/index.js' ),
			array( 
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'gatherpress-attendee-count',
			'gatherpressAttendeeCount',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'canEdit' => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * Enqueue scripts and styles for list table.
	 *
	 * @since 0.1.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_list_table_scripts( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'gatherpress-attendee-count',
			plugins_url( 'assets/css/style.css', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/assets/css/style.css' ),
		);

		wp_enqueue_script(
			'gatherpress-attendee-count',
			plugins_url( 'assets/js/index.js', __FILE__ ),
			array( 'jquery' ),
			filemtime( __DIR__ . '/assets/js/index.js' ),
			array( 
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'gatherpress-attendee-count',
			'gatherpressAttendeeCount',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'canEdit' => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * Get events with missing attendee counts.
	 *
	 * Retrieves events from transient cache if available,
	 * otherwise performs database query and caches result.
	 *
	 * @since 0.1.0
	 * @return WP_Query Query object containing events.
	 */
	private function get_events(): WP_Query {
		$cached_events = get_transient( self::TRANSIENT_KEY );

		if ( $cached_events instanceof WP_Query ) {
			return $cached_events;
		}

		$args = array(
			'post_type'               => self::POST_TYPE,
			'gatherpress_event_query' => 'past',
			'posts_per_page'          => 100,
			'post_status'             => array( 'publish' ),
			'orderby'                 => 'event_date',
			'order'                   => 'DESC',
			'meta_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array( // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found, Universal.Arrays.MixedArrayKeyTypes.ImplicitNumericKey
					'key'     => 'gatherpress_datetime_start',
					'compare' => 'EXISTS',
				),
				array( // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found, Universal.Arrays.MixedArrayKeyTypes.ImplicitNumericKey
					'relation' => 'OR',
					array( // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found, Universal.Arrays.MixedArrayKeyTypes.ImplicitNumericKey
						'key'     => self::META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array( // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray.Found
						'key'     => self::META_KEY,
						'compare' => '=',
						'value'   => '',
					),
				),
			),
		);

		$query = new WP_Query( $args );

		set_transient( self::TRANSIENT_KEY, $query, self::TRANSIENT_EXPIRATION );

		return $query;
	}

	/**
	 * Clear events transient cache.
	 *
	 * Called when attendee count is updated or deleted
	 * to ensure fresh data on next request.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function clear_events_cache(): void {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Format event date for display.
	 *
	 * @since 0.1.0
	 * @param int $event_id Event post ID.
	 * @return string Formatted date string.
	 */
	private function format_event_date( int $event_id ): string {

		$today                = current_time( 'Y-m-d' );
		$yesterday            = current_datetime()->modify( '-1 day' )->format( 'Y-m-d' );
		$year                 = current_time( 'Y' );
		$gatherpress_datetime = get_post_meta( $event_id, 'gatherpress_datetime_start', true );
		
		if ( ! empty( $gatherpress_datetime ) && is_string( $gatherpress_datetime ) ) {
			$time = strtotime( $gatherpress_datetime );
		} else {
			$time = get_the_date( 'U', $event_id );
		}

		if ( gmdate( 'Y-m-d', $time ) === $today ) {
			$relative = __( 'Today', 'default' );
		} elseif ( gmdate( 'Y-m-d', $time ) === $yesterday ) {
			$relative = __( 'Yesterday', 'default' );
		} elseif ( gmdate( 'Y', $time ) !== $year ) {
			/* translators: Date and time format for recent posts on the dashboard, from a different calendar year, see https://www.php.net/manual/datetime.format.php */
			$relative = date_i18n( __( 'M jS Y', 'default' ), $time );
		} else {
			/* translators: Date and time format for recent posts on the dashboard, see https://www.php.net/manual/datetime.format.php */
			$relative = date_i18n( __( 'M jS', 'default' ), $time );
		}

		return $relative;
	}

	/**
	 * Get venue name for event.
	 *
	 * @since 0.1.0
	 * @param int $event_id Event post ID.
	 * @return string Venue name or empty string.
	 */
	private function get_event_venue( int $event_id ): string {
		$venue_terms = get_the_terms( $event_id, '_gatherpress_venue' );
		
		if ( ! empty( $venue_terms ) && ! is_wp_error( $venue_terms ) ) {
			return $venue_terms[0]->name;
		}
		
		return '';
	}

	/**
	 * Render dashboard widget content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$query = $this->get_events();

		if ( $query->have_posts() ) {
			echo '<div id="activity-widget">';
			echo '<div id="gatherpress-events-list" class="activity-block">';
			echo '<ul>';

			while ( $query->have_posts() ) {
				$query->the_post();
				$event_id   = get_the_ID();
				$edit_link  = get_edit_post_link( $event_id );
				$event_date = $this->format_event_date( $event_id );
				$venue_name = $this->get_event_venue( $event_id );

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
	 * Add attendee count column to list table.
	 *
	 * @since 0.1.0
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_attendee_count_column( array $columns ): array {
		$new_columns = array();
		
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			
			if ( 'title' === $key ) {
				$new_columns['gatherpress_attendee_count'] = __( 'Attendees', 'gatherpress-attendee-count' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Display attendee count in list table column.
	 *
	 * @since 0.1.0
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function display_attendee_count_column( string $column, int $post_id ): void {
		if ( 'gatherpress_attendee_count' !== $column ) {
			return;
		}

		$attendee_count = get_post_meta( $post_id, self::META_KEY, true );
		$can_edit       = current_user_can( 'edit_posts' );
		
		echo '<div class="gatherpress-count-container">';
		
		if ( '' !== $attendee_count && null !== $attendee_count ) {
			if ( $can_edit ) {
				echo '<span class="button gatherpress-count-value" data-event-id="' . esc_attr( $post_id ) . '" data-value="' . esc_attr( $attendee_count ) . '"><span>' . esc_html( $attendee_count ) . '</span></span>';
			} else {
				echo '<span><span>' . esc_html( $attendee_count ) . '</span></span>';
			}
		} elseif ( $can_edit ) {
			echo '<span class="button gatherpress-count-value gatherpress-empty" data-event-id="' . esc_attr( $post_id ) . '" data-value="—">—</span>';
		} else {
			echo '<span style="color: #d63638;">—</span>';
		}
		
		echo '</div>';
	}

	/**
	 * AJAX handler to update attendee count.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function ajax_update_attendee_count(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'gatherpress-attendee-count' ),
				) 
			);
		}

		$event_id       = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;
		$attendee_count = isset( $_POST['attendee_count'] ) ? intval( $_POST['attendee_count'] ) : 0;

		if ( ! $event_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid event ID.', 'gatherpress-attendee-count' ),
				) 
			);
		}

		if ( $attendee_count < 1 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Attendee count must be at least 1.', 'gatherpress-attendee-count' ),
				) 
			);
		}

		$updated = update_post_meta( $event_id, self::META_KEY, $attendee_count );

		if ( $updated ) {
			$this->clear_events_cache();

			wp_send_json_success(
				array(
					'message'  => __( 'Attendee count updated successfully.', 'gatherpress-attendee-count' ),
					'event_id' => $event_id,
				) 
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to update attendee count.', 'gatherpress-attendee-count' ),
				) 
			);
		}
	}

	/**
	 * AJAX handler to delete attendee count.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function ajax_delete_attendee_count(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'gatherpress-attendee-count' ),
				) 
			);
		}

		$event_id = isset( $_POST['event_id'] ) ? intval( $_POST['event_id'] ) : 0;

		if ( ! $event_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid event ID.', 'gatherpress-attendee-count' ),
				) 
			);
		}

		$deleted = delete_post_meta( $event_id, self::META_KEY );

		if ( $deleted ) {
			$this->clear_events_cache();

			wp_send_json_success(
				array(
					'message'  => __( 'Attendee count cleared successfully.', 'gatherpress-attendee-count' ),
					'event_id' => $event_id,
				) 
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to clear attendee count.', 'gatherpress-attendee-count' ),
				) 
			);
		}
	}
}
Plugin::get_instance();
