<?php
/**
 * Plugin Name: Activity Heat Map
 * Plugin URI:  https://pascalbirchler.com
 * Description: Show your site activity in an intuitive heat map.
 * Version:     1.0.0
 * Author:      Pascal Birchler
 * Author URI:  https://pascalbirchler.com
 * License:     GPLv2+
 * Text Domain: activity-heat-map
 * Domain Path: /languages
 *
 * @package Activity_Heat_Map
 */

/**
 * Initialize the plugin.
 */
function activity_heat_map_init() {
	if ( is_admin() ) {
		require_once( dirname( __FILE__ ) . '/classes/class-dashboard-widget.php' );
		add_action( 'wp_dashboard_setup', array( 'Activity_Heat_Map_Dashboard_Widget', 'add_dashboard_widget' ) );
	}

	load_plugin_textdomain( 'activity-heat-map', false, 'languages' );

	add_action( 'admin_enqueue_scripts', 'activity_heat_map_enqueue_scripts' );

	add_action( 'wp_ajax_activity_heat_map', 'activity_heat_map_handle_ajax_requests' );

	add_filter( 'activity_heat_map_filter_result', 'activity_heat_map_calculate_streak', 10, 3 );
}

add_action( 'init', 'activity_heat_map_init' );

/**
 * Enqueue the necessary scripts and styles for the heat map.
 *
 * @since 1.1.0
 */
function activity_heat_map_enqueue_scripts() {
	wp_enqueue_script( 'activity-heat-map', plugin_dir_url( __FILE__ ) . 'js/activity-heat-map.js', array( 'jquery' ), '1.0.0' );
	wp_enqueue_style( 'activity-heat-map', plugin_dir_url( __FILE__ ) . 'css/activity-heat-map.css', array(), '1.0.0' );

	wp_localize_script( 'activity-heat-map', 'activityHeatMap', array(
		'ajax_url'      => admin_url( 'admin-ajax.php' ),
	) );
}

/**
 * Handle the initial ajax request for current filter data.
 *
 * @since 1.0.0
 */
function activity_heat_map_handle_ajax_requests() {
	$available_filters = activity_heat_map_get_filters();
	$filter            = sanitize_text_field( wp_unslash( $_REQUEST['filter'] ) );
	$days              = absint( wp_unslash( $_REQUEST['days'] ) );

	if ( ! array_key_exists( $filter, $available_filters ) ) {
		die( wp_json_encode( array() ) );
	}

	if ( 'streaks' === wp_unslash( $_REQUEST['type'] ) ) {
		die( wp_json_encode( activity_heat_map_get_streaks( $filter, $days ) ) );
	}

	die( wp_json_encode( array_values( activity_heat_map_get_data( $filter, $days ) ) ) );
}

/**
 * Get the stats data.
 *
 * All results are cached for 12 hours.
 *
 * @since 1.0.0
 *
 * @param string $filter Filter to get the data for.
 * @param int    $days   Number of days.
 * @return array The data for a specific filter.
 */
function activity_heat_map_get_data( $filter, $days ) {
	$available_filters = activity_heat_map_get_filters();
	$key               = sprintf( 'filter-%s-%d', $filter, $days );
	$group             = 'activity-heat-map';
	$result            = wp_cache_get( $key, $group );

	if ( ! $result ) {
		$result = call_user_func( $available_filters[ $filter ]['callback'], $days );

		wp_cache_set( $key, $result, $group, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Filter the heat map filter result.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $result Result array containing the data for each day.
	 * @param string $filter The current filter.
	 * @param int    $days   The number of days.
	 */
	$result = apply_filters( 'activity_heat_map_filter_result', $result, $filter, $days );

	return $result;
}

/**
 * Get streaks data.
 *
 * @since 1.0.0
 *
 * @param string $filter Filter to get the data for.
 * @param int    $days   Number of days.
 * @return array The data for a specific filter.
 */
function activity_heat_map_get_streaks( $filter, $days ) {
	$result            = array();

	$last_entry = get_option( "ahm_{$filter}_{$days}_last_entry", false );

	$current_streak       = get_option( "ahm_{$filter}_{$days}_current_streak", array(
		date( 'd-m-Y' ),
		date( 'd-m-Y' ),
	) );
	$current_streak_begin = new DateTime( $current_streak[0] );
	$current_streak_end   = new DateTime( $current_streak[1] );
	$current_streak_diff  = $current_streak_end->diff( $current_streak_begin )->format( '%a' );
	if ( 0 < $current_streak_diff ) {
		$current_streak_diff ++;
	}

	$result['current'] = array(
		'title'      => __( 'Current streak', 'activity-heat-map' ),
		'begin'      => $current_streak_begin->format( get_option( 'date_format' ) ),
		'end'        => $current_streak_end->format( get_option( 'date_format' ) ),
		'total'      => sprintf( _n( '%s day', '%s days', $current_streak_diff, 'activity-heat-map' ), number_format_i18n( $current_streak_diff ) ),
		'text'       => '',
	);

	if ( $last_entry ) {
		$result['current']['text'] = sprintf( __( 'Last entry %s ago', 'activity-heat-map' ), human_time_diff( strtotime( $last_entry ) ) );
	}

	$longest_streak       = get_option( "ahm_{$filter}_{$days}_longest_streak", array(
		date( 'd-m-Y' ),
		date( 'd-m-Y' ),
	) );
	$longest_streak_begin = new DateTime( $longest_streak[0] );
	$longest_streak_end   = new DateTime( $longest_streak[1] );
	$longest_streak_diff  = $longest_streak_end->diff( $longest_streak_begin )->format( '%a' );
	if ( 0 < $longest_streak_diff ) {
		$longest_streak_diff ++;
	}

	/**
	 * Filter the date format for the longest streak text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format Date format. Default 'F j'.
	 */
	$longest_streak_format = apply_filters( 'activity_heat_map_longest_streak_format', 'F j' );

	$result['longest'] = array(
		'title' => __( 'Longest streak', 'activity-heat-map' ),
		'begin' => $longest_streak_begin->format( get_option( 'date_format' ) ),
		'end'   => $longest_streak_end->format( get_option( 'date_format' ) ),
		'total' => sprintf( _n( '%s day', '%s days', $longest_streak_diff, 'activity-heat-map' ), number_format_i18n( $longest_streak_diff ) ),
		'text'  => sprintf(
			__( '%s &ndash; %s', 'activity-heat-map' ),
			date_i18n( $longest_streak_format, $longest_streak_begin->getTimestamp() ),
			date_i18n( $longest_streak_format, $longest_streak_end->getTimestamp() )
		),
	);

	$total = get_option( "ahm_{$filter}_{$days}_total", 0 );

	$begin  = new DateTime( sprintf( '-%d days', absint( $days ) ) );

	/**
	 * Filter the date format for the total streak text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format Date format. Default 'F j'.
	 */
	$total_format = apply_filters( 'activity_heat_map_total_format', 'M j, Y' );

	$months = $days / 30;
	if ( 1 >= $months ) {
		$title = __( 'Entries in the past month', 'activity-heat-map' );
	} else {
		$title = sprintf(
			_n( 'Entries in the past %s month', 'Entries in the past %s months', $months, 'activity-heat-map' ),
			number_format_i18n( $months )
		);
	}

	$result['total'] = array(
		'title' => $title,
		'total' => sprintf( _n( '%s total', '%s total', $total, 'activity-heat-map' ), number_format_i18n( $total ) ),
		'text'  => sprintf(
			__( '%s &ndash; %s', 'activity-heat-map' ),
			date_i18n( $total_format, $begin->getTimestamp() ),
			date_i18n( $total_format )
		),
	);

	return $result;
}

/**
 * Calculate streaks based on the filter data.
 *
 * @since 1.0.0
 *
 * @param array  $result The result data.
 * @param string $filter The filter data.
 * @param int    $days   Number of days.
 * @return array The unmodified result data.
 */
function activity_heat_map_calculate_streak( $result, $filter, $days ) {
	$total                = 0;
	$current_streak_begin = $longest_streak_begin = $longest_streak_end = $previous_day = $last_entry = null;

	foreach ( $result as $day => $data ) {
		if ( null === $current_streak_begin ) {
			$current_streak_begin = $day;
		}

		if ( null === $longest_streak_begin ) {
			$longest_streak_end = $longest_streak_begin = $day;
		}

		if ( 0 === $data['count'] ) {
			$current_streak_begin = null;
		} else {
			$last_entry = $day;

			if ( $previous_day === $longest_streak_end ) {
				$longest_streak_end = $day;
			}

			if ( strtotime( $day ) - strtotime( $current_streak_begin ) >= strtotime( $longest_streak_end ) - strtotime( $longest_streak_begin ) ) {
				$longest_streak_begin = $current_streak_begin;
				$longest_streak_end   = $day;
			}
		}

		$total += $data['count'];
		$previous_day = $day;
	}

	// Save data to the database.
	update_option( "ahm_{$filter}_{$days}_current_streak", array(
		$current_streak_begin,
		date( 'd-m-Y' ),
	), false );
	update_option( "ahm_{$filter}_{$days}_longest_streak", array(
		$longest_streak_begin,
		$longest_streak_end,
	), false );
	update_option( "ahm_{$filter}_{$days}_total", $total, false );
	update_option( "ahm_{$filter}_{$days}_last_entry", $last_entry, false );

	return $result;
}

/**
 * Get the list of available heat map filters.
 *
 * @since 1.0.0
 *
 * @return array Filters array.
 */
function activity_heat_map_get_filters() {
	$available_filters = array(
		'posts'    => array(
			'title'    => __( 'Posts', 'activity-heat-map' ),
			'callback' => 'activity_heat_map_get_posts',
		),
		'comments' => array(
			'title'    => __( 'Comments', 'activity-heat-map' ),
			'callback' => 'activity_heat_map_get_comments',
		),
	);

	/**
	 * Filter the list of available heat map filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters Filters array.
	 */
	return apply_filters( 'activity_heat_map_filters', $available_filters );
}

/**
 * Fill up the days array with empty cells.
 *
 * @access private
 *
 * @since 1.0.0
 *
 * @param DateTime $date_begin Start date.
 * @param string   $text       Text to show for empty cells.
 * @return array A filler array.
 */
function _activity_heat_map_fill_results_array( DateTime $date_begin, $text ) {
	$result = array();

	$interval = DateInterval::createFromDateString( '1 day' );
	$period   = new DatePeriod( $date_begin, $interval, new DateTime() );

	/* @var DateTime $datetime */
	foreach ( $period as $datetime ) {
		$result[ $datetime->format( 'Y-m-d' ) ] = array(
			'count' => 0,
			'text'  => sprintf(
				$text,
				$datetime->format( get_option( 'date_format' ) )
			),
		);
	}

	return $result;
}

/**
 * Get posts for the activity heat map.
 *
 * @since 1.0.0
 *
 * @param int $days Number of days to retrieve posts for.
 * @return array Post data.
 */
function activity_heat_map_get_posts( $days ) {
	global $wpdb;

	$days = absint( $days );

	$begin  = new DateTime( sprintf( '-%d days', absint( $days ) ) );
	$result = _activity_heat_map_fill_results_array( $begin, __( 'No posts on %s', 'activity-heat-map' ) );

	$posts = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE_FORMAT(post_date, '%%Y-%%m-%%d') as post_date, COUNT(ID) count FROM $wpdb->posts WHERE 1=1 AND ( post_date >= '%s' ) AND post_type = 'post' AND (post_status = 'publish' OR post_status = 'private') GROUP BY DATE(post_date) ORDER BY post_date ASC",
		array( $begin->format( 'Y-m-d' ) )
	), ARRAY_A );

	if ( ! is_array( $posts ) ) {
		return $result;
	}

	foreach ( $posts as $post ) {
		$result[ $post['post_date'] ] = array(
			'count' => absint( $post['count'] ),
			'text'  => sprintf(
				_n( '%d post on %s', '%d posts on %s', absint( $post['count'] ), 'activity-heat-map' ),
				absint( $post['count'] ),
				date( get_option( 'date_format' ), strtotime( $post['post_date'] ) )
			),
		);
	}

	return $result;
}

/**
 * Get comments for the activity heat map.
 *
 * @param int $days Number of days to retrieve comments for.
 *
 * @return array Comment data.
 */
function activity_heat_map_get_comments( $days ) {
	global $wpdb;

	$days = absint( $days );

	$begin  = new DateTime( sprintf( '-%d days', absint( $days ) ) );
	$result = _activity_heat_map_fill_results_array( $begin, __( 'No posts on %s', 'activity-heat-map' ) );

	$comments = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE_FORMAT(comment_date, '%%Y-%%m-%%d') as comment_date, COUNT(comment_ID) count FROM $wpdb->comments WHERE 1=1 AND ( comment_date >= '%s' ) AND comment_approved = '1' GROUP BY DATE(comment_date) ORDER BY comment_date ASC",
		array( $begin->format( 'Y-m-d' ) )
	), ARRAY_A );

	if ( ! is_array( $comments ) ) {
		return array_values( $result );
	}

	foreach ( $comments as $comment ) {
		$result[ $comment['comment_date'] ] = array(
			'count' => absint( $comment['count'] ),
			'text'  => sprintf(
				_n( '%d comment on %s', '%d comments on %s', absint( $comment['count'] ), 'activity-heat-map' ),
				absint( $comment['count'] ),
				date( get_option( 'date_format' ), strtotime( $comment['comment_date'] ) )
			),
		);
	}

	return $result;
}
