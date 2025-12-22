( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		const canEdit = gatherpressAttendeeCountListTable.canEdit;

		if ( ! canEdit ) {
			return;
		}

		// Make attendee count clickable for inline editing
		$( '.column-gatherpress_attendee_count' ).on(
			'click',
			'.gatherpress-count-value',
			function ( e ) {
				e.preventDefault();

				const valueSpan = $( this );
				const container = valueSpan.parent();
				const currentValue = valueSpan.data( 'value' );
				const eventId = valueSpan.data( 'event-id' );

				// Don't create multiple inputs
				if ( container.find( '.gatherpress-inline-edit' ).length > 0 ) {
					return;
				}

				// Hide the value span
				valueSpan.hide();

				// Create inline edit form
				const editForm = $(
					'<span class="gatherpress-inline-edit"></span>'
				);
				const input = $(
					'<input type="number" class="gatherpress-inline-input" min="1" />'
				);
				const saveBtn = $(
					'<button class="button button-small gatherpress-inline-save">Save</button>'
				);
				const cancelBtn = $(
					'<button class="button button-small gatherpress-inline-cancel">Cancel</button>'
				);

				input.val( currentValue === '—' ? '' : currentValue );

				editForm.append( input ).append( saveBtn ).append( cancelBtn );
				container.append( editForm );

				input.focus().select();

				// Handle save
				const saveEdit = function () {
					const newValue = input.val();

					// Disallow 0 or empty - revert to empty state
					if ( newValue === '' || newValue === '0' || newValue < 1 ) {
						// If trying to set to 0, treat as delete/empty
						if ( currentValue !== '—' ) {
							saveBtn
								.prop( 'disabled', true )
								.text( 'Clearing...' );
							cancelBtn.prop( 'disabled', true );
							container.addClass( 'gatherpress-saving' );

							$.ajax( {
								url: gatherpressAttendeeCountListTable.ajaxUrl,
								type: 'POST',
								data: {
									action: 'gatherpress_attendee_count_delete_attendee_count',
									nonce: gatherpressAttendeeCountListTable.nonce,
									event_id: eventId,
								},
								success( response ) {
									if ( response.success ) {
										valueSpan.data( 'value', '—' );
										valueSpan.html( '—' );
										valueSpan.addClass(
											'gatherpress-empty'
										);
										valueSpan.css( 'color', '#d63638' );

										editForm.remove();
										container.removeClass(
											'gatherpress-saving'
										);
										valueSpan.fadeIn();
									} else {
										alert(
											response.data.message ||
												'Failed to clear attendee count.'
										);
										container.removeClass(
											'gatherpress-saving'
										);
										saveBtn
											.prop( 'disabled', false )
											.text( 'Save' );
										cancelBtn.prop( 'disabled', false );
									}
								},
								error() {
									alert(
										'An error occurred. Please try again.'
									);
									container.removeClass(
										'gatherpress-saving'
									);
									saveBtn
										.prop( 'disabled', false )
										.text( 'Save' );
									cancelBtn.prop( 'disabled', false );
								},
							} );
						} else {
							// Already empty, just cancel
							editForm.remove();
							valueSpan.show();
						}
						return;
					}

					saveBtn.prop( 'disabled', true ).text( 'Saving...' );
					cancelBtn.prop( 'disabled', true );

					// Add saving state to container
					container.addClass( 'gatherpress-saving' );

					$.ajax( {
						url: gatherpressAttendeeCountListTable.ajaxUrl,
						type: 'POST',
						data: {
							action: 'gatherpress_attendee_count_update_attendee_count',
							nonce: gatherpressAttendeeCountListTable.nonce,
							event_id: eventId,
							attendee_count: newValue,
						},
						success( response ) {
							if ( response.success ) {
								// Update the display value with new color
								valueSpan.data( 'value', newValue );
								valueSpan.html(
									'<span>' + newValue + '</span>'
								);
								valueSpan.removeClass( 'gatherpress-empty' );
								valueSpan.css( 'color', '#46b450' );

								// Remove edit form and show value
								editForm.remove();
								container.removeClass( 'gatherpress-saving' );
								valueSpan.fadeIn();

								// Show success flash
								container.addClass( 'gatherpress-updated' );
								setTimeout( function () {
									container.removeClass(
										'gatherpress-updated'
									);
									// Fade color back to default
									valueSpan.animate(
										{ color: '#2c3338' },
										1000
									);
								}, 2000 );
							} else {
								alert(
									response.data.message ||
										'Failed to update attendee count.'
								);
								container.removeClass( 'gatherpress-saving' );
								saveBtn
									.prop( 'disabled', false )
									.text( 'Save' );
								cancelBtn.prop( 'disabled', false );
							}
						},
						error() {
							alert( 'An error occurred. Please try again.' );
							container.removeClass( 'gatherpress-saving' );
							saveBtn.prop( 'disabled', false ).text( 'Save' );
							cancelBtn.prop( 'disabled', false );
						},
					} );
				};

				// Handle cancel
				const cancelEdit = function () {
					editForm.remove();
					valueSpan.show();
				};

				saveBtn.on( 'click', function ( e ) {
					e.preventDefault();
					saveEdit();
				} );

				cancelBtn.on( 'click', function ( e ) {
					e.preventDefault();
					cancelEdit();
				} );

				input.on( 'keypress', function ( e ) {
					if ( e.which === 13 ) {
						e.preventDefault();
						saveEdit();
					}
				} );

				input.on( 'keydown', function ( e ) {
					if ( e.which === 27 ) {
						e.preventDefault();
						cancelEdit();
					}
				} );
			}
		);
	} );
} )( jQuery );
