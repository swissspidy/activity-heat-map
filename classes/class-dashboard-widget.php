<?php
/**
 * This file holds the dashboard widgets class.
 *
 * @package Activity_Heat_Map
 */

/**
 * Configurable admin dashboard widget.
 */
class Activity_Heat_Map_Dashboard_Widget {
	/**
	 * Get the current activity heat map filter.
	 *
	 * @return string Name of the current filter.
	 */
	protected static function get_current_filter() {
		$current_filter = get_user_option( 'activity_heat_map_filter' );

		if ( ! $current_filter ) {
			$current_filter = 'posts';
		}

		return $current_filter;
	}

	/**
	 * Get the number of days to get entries for.
	 *
	 * @return int Days.
	 */
	protected static function get_number_of_days() {
		$days = get_user_option( 'activity_heat_map_days' );

		if ( ! $days ) {
			$days = 60;
		}

		return absint( $days );
	}

	/**
	 * Add an "Activity Heat Map" widget to the dashboard.
	 */
	public static function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'activity-heat-map',
			__( 'Activity Heat Map', 'activity-heat-map' ),
			array( __CLASS__, 'dashboard_widget_content' ),
			array( __CLASS__, 'dashboard_widget_controls' )
		);
	}

	/**
	 * Display the dashboard widget content.
	 */
	public static function dashboard_widget_content() {
		printf( '<div class="activity-heat-map" data-filter="%s" data-days="%d"></div>',
			esc_attr( self::get_current_filter() ),
			absint( self::get_number_of_days() )
		);

		activity_heat_map_enqueue_scripts();
	}

	/**
	 * Display the dashboard widget controls content.
	 */
	public static function dashboard_widget_controls() {
		$number_of_days    = self::get_number_of_days();
		$current_filter    = self::get_current_filter();
		$available_filters = activity_heat_map_get_filters();

		if ( isset( $_REQUEST['activity_heat_map_filter'] ) ) {
			$current_filter = sanitize_text_field( $_REQUEST['activity_heat_map_filter'] );

			if ( ! array_key_exists( $current_filter, $available_filters ) ) {
				$current_filter = 'posts';
			}

			update_user_option( get_current_user_id(), 'activity_heat_map_filter', $current_filter );
		}

		if ( isset( $_REQUEST['activity_heat_map_days'] ) ) {
			$number_of_days = absint( $_REQUEST['activity_heat_map_days'] );
			$number_of_days = ( $number_of_days <= 366 ) ? $number_of_days : 366;

			update_user_option( get_current_user_id(), 'activity_heat_map_days', $number_of_days );
		}

		echo '<p>';
		printf( '<label for="activity_heat_map_filter">%s</label>', esc_html__( 'Show activity: ', 'activity-heat-map' ) );

		echo '<select name="activity_heat_map_filter" id="activity_heat_map_filter">';

		foreach ( $available_filters as $filter => $data ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $filter ),
				selected( $current_filter, $filter ),
				esc_html( $data['title'] )
			);
		}

		echo '</select>';
		echo '</p>';

		echo '<p>';
		printf( '<label for="activity_heat_map_days">%s</label>', esc_html__( 'Number of days: ', 'activity-heat-map' ) );

		printf(
			'<input type="number" min="7" max="366" name="activity_heat_map_days" id="activity_heat_map_days" value="%s" />',
			absint( $number_of_days )
		);
		echo '</p>';
	}
}
