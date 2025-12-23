( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Configuration for different contexts 
		const contexts = {
			dashboard: { 
				selector: '#gatherpress_attendee_count_widget', 
				features: { 
					removeOnSave: true,
					allowEmpty: false,
					emptyMessage: 'All events have attendee count information!', 
				},
			},
			listTable: { 
				selector: '.column-gatherpress_attendee_count', 
				features: { 
					removeOnSave: false,
					allowEmpty: true,
					emptyMessage: null,
				},
			},
		};

		// Initialize both contexts
		Object.values( contexts ).forEach( ( context ) => { 
			initializeContext( context );
		} );

		function initializeContext( context ) {
			const $container = $( context.selector );
			if ( ! $container.length ) {
				return;
			}

			// Make attendee count clickable for inline editing 
			$container.on( 
				'click',
				'.gatherpress-count-value', 
				function ( e ) {
					e.preventDefault(); 

					// Check if user can edit
					if ( ! gatherpressAttendeeCount.canEdit ) {
						return;
					}

					const valueSpan = $( this );
					const container = valueSpan.closest( 
						'.gatherpress-count-container' 
					);
					const eventId = valueSpan.data( 'event-id' );
					const currentValue = valueSpan.data( 'value' );

					// Don't allow editing if already editing
					if ( container.find( 'input' ).length ) {
						return;
					}

					// Create input field
					const input = $( '<input>' ) 
						.attr( {
							type: 'number', 
							min: context.features.allowEmpty ? '0' : '1',
							value:
								currentValue === '—' ? '' : currentValue, 
							placeholder: '0',
						} )
						.addClass( 'gatherpress-count-input' );

					// Replace span with input 
					valueSpan.replaceWith( input );
					input.focus().select(); 

					// Handle save on blur or enter 
					input.on( 'blur keypress', function ( event ) {
						if (
							event.type === 'blur' ||
							( event.type === 'keypress' && 
								event.which === 13 )
						) {
							event.preventDefault(); 
							saveValue( 
								input,
								eventId, 
								currentValue, 
								context 
							);
						}
					} );

					// Handle escape key
					input.on( 'keydown', function ( event ) {
						if ( event.which === 27 ) {
							// Escape key
							restoreValue( input, currentValue );
						}
					} );
				}
			);
		}

		function saveValue( input, eventId, currentValue, context ) {
			const newValue = input.val().trim(); 
			const container = input.closest( '.gatherpress-count-container' );

			// Convert currentValue to string for comparison, handling empty case 
			const currentValueStr =
				currentValue === '—' ? '' : String( currentValue ); 

			// If value hasn't changed, just restore 
			if (
				newValue === currentValueStr ||
				( newValue === '' && currentValueStr === '' )
			) {
				restoreValue( input, currentValue );
				return;
			}

			// Don't allow saving 0 or empty in dashboard context 
			if (
				! context.features.allowEmpty &&
				( newValue === '' || newValue === '0' )
			) {
				restoreValue( input, currentValue );
				return;
			}

			// Show saving state
			container.addClass( 'gatherpress-saving' );

			// Determine action based on new value
			const action =
				newValue === '' || newValue === '0'
					? 'gatherpress_attendee_count_delete_attendee_count' 
					: 'gatherpress_attendee_count_update_attendee_count'; 

			// Prepare AJAX data
			const ajaxData = {
				action: action, 
				nonce: gatherpressAttendeeCount.nonce, 
				event_id: eventId, 
			};

			if ( action.includes( 'update' ) ) {
				ajaxData.attendee_count = newValue;
			}

			// Send AJAX request
			$.ajax( {
				url: gatherpressAttendeeCount.ajaxUrl, 
				type: 'POST', 
				data: ajaxData, 
				success: function ( response ) {
					container.removeClass( 'gatherpress-saving' );

					if ( response.success ) {
						// Handle dashboard widget context
						if ( context.features.removeOnSave ) {
							const listItem = container.closest( 'li' );
							listItem.fadeOut( 300, function () {
								listItem.remove(); 

								// Check if there are any items left
								const activityBlock = $(
									'#gatherpress-events-list' 
								);
								if (
									! activityBlock.find( 'li' ).length && 
									context.features.emptyMessage 
								) {
									activityBlock 
										.closest( '#activity-widget' )
										.html( 
											'<div class="no-activity"><p class="smiley" aria-hidden="true"></p><p>' +
												context.features 
													.emptyMessage +
												'</p></div>' 
										);
								}
							} );
						} else {
							// Handle list table context 
							if (
								newValue === '' ||
								newValue === '0'
							) {
								// Value was deleted - show empty state
								const newSpan = $(
									'<span class="button gatherpress-count-value gatherpress-empty">' 
								)
									.attr( 'data-event-id', eventId )
									.attr( 'data-value', '—' )
									.text( '—' );
								input.replaceWith( newSpan );
							} else { 
								// Value was updated - show new value
								const newSpan = $(
									'<span class="button gatherpress-count-value">' 
								)
									.attr( 'data-event-id', eventId )
									.attr( 'data-value', newValue )
									.html( '<span>' + newValue + '</span>' );
								input.replaceWith( newSpan );
							}

							// Flash the container
							container.addClass( 'gatherpress-updated' );
							setTimeout( function () {
								container.removeClass( 
									'gatherpress-updated' 
								);
							}, 500 ); 
						}
					} else {
						restoreValue( input, currentValue );
						alert(
							response.data.message ||
								'Failed to update attendee count.'
						);
					}
				},
				error: function () {
					container.removeClass( 'gatherpress-saving' );
					restoreValue( input, currentValue );
					alert( 'An error occurred. Please try again.' );
				},
			} );
		}

		function restoreValue( input, value ) { 
			const eventId = input
				.closest( '.gatherpress-count-container' )
				.find( '.gatherpress-count-value' )
				.data( 'event-id' );

			let newSpan; 
			if ( value === '—' || value === '' ) {
				newSpan = $( 
					'<span class="button gatherpress-count-value gatherpress-empty">' 
				)
					.attr( 'data-event-id', eventId )
					.attr( 'data-value', '—' )
					.text( '—' );
			} else {
				newSpan = $( '<span class="button gatherpress-count-value">' )
					.attr( 'data-event-id', eventId )
					.attr( 'data-value', value )
					.html( '<span>' + value + '</span>' );
			}

			input.replaceWith( newSpan );
		}
	} );
} )( jQuery );