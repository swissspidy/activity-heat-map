/**
 * Activity Heat Map
 *
 * Copyright (c) 2015 Pascal Birchler
 * Licensed under the GPLv2+ license.
 */

(function ( window, document, $ ) {
	'use strict';

	var activityHeatMap = window.activityHeatMap || {};
	activityHeatMap.activityData = [];

	$( document ).ready( function () {
		var heatmap = $( '.activity-heat-map' );

		if ( 0 === heatmap.length ) {
			return;
		}

		heatmap.each( function () {
			updateHeatMap(
				$( this ).attr( 'data-filter' ),
				$( this ).attr( 'data-days' ),
				$( this )
			);
		} );

		function updateHeatMap( filter, days, target ) {
			var queryString = $.param( { action: 'activity_heat_map', filter: filter, days: days } );

			$.getJSON( activityHeatMap.ajax_url + '?type=heatmap&' + queryString, function ( data ) {
				fillHeatMap( data, target );
			} );

			$.getJSON( activityHeatMap.ajax_url + '?type=streaks&' + queryString, function ( data ) {
				fillHeatMapStreaks( data, target );
			} );
		}

		function fillHeatMap( data, target ) {
			var inner = document.createElement( 'div' ), node, nodeSize, innerNode;
			inner.className = 'activity-heat-map__inner';
			target.prepend( inner );

			for ( var i = 0, row = 0, column = 0; i <= data.length - 1; i++, row++ ) {
				if ( i > 0 && i % 4 === 0 ) {
					column++;
					row = 0;
				}

				// Create node.
				node = document.createElement( 'div' );
				node.className = 'activity-heat-map-node';
				node.setAttribute( 'data-row', (row + 1).toString() );
				node.setAttribute( 'data-column', (column + 1).toString() );

				node.style.left = column / ( data.length - 1 ) * 4 * 100 + '%';
				node.style.top = ( row * 25 ) + '%';

				// Create inner node.
				innerNode = document.createElement( 'div' );
				innerNode.setAttribute( 'data-content', data[ i ].text );
				innerNode.className = 'activity-heat-map-node__inner';

				if ( 0 === data[ i ].count ) {
					nodeSize = 'none';
				} else if ( 2 > data[ i ].count ) {
					nodeSize = 'small';
				} else if ( 5 > data[ i ].count ) {
					nodeSize = 'medium';
				} else {
					nodeSize = 'large';
				}

				innerNode.className += ' activity-heat-map-node__inner--' + nodeSize;
				node.appendChild( innerNode );

				inner.appendChild( node );
			}
		}

		function fillHeatMapStreaks( data, target ) {
			var inner = document.createElement( 'div' ), node, streakTitle, streakNumber, streakText;
			inner.className = 'activity-heat-map__streaks';
			target.append( inner );

			$.each( [ 'total', 'longest', 'current' ], function ( index, type ) {
				node = document.createElement( 'div' );
				node.className = 'activity-heat-map-streak activity-heat-map-streak--' + type;
				inner.appendChild( node );

				streakTitle = document.createElement( 'div' );
				streakTitle.className = 'activity-heat-map-streak__text';
				streakTitle.appendChild( document.createTextNode( data[ type ].title ) );
				node.appendChild( streakTitle );

				streakNumber = document.createElement( 'div' );
				streakNumber.className = 'activity-heat-map-streak__number';
				streakNumber.appendChild( document.createTextNode( data[ type ].total ) );
				node.appendChild( streakNumber );

				streakText = document.createElement( 'div' );
				streakText.className = 'activity-heat-map-streak__text';
				streakText.innerHTML = data[ type ].text;
				node.appendChild( streakText );
			} );
		}
	} );
})( window, document, jQuery );
