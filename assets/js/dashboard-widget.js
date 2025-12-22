(function ($) {
    'use strict';

    $(document).ready(function () {
        // Make attendee count clickable for inline editing in dashboard widget
        $('#gatherpress_attendee_count_widget').on('click', '.gatherpress-count-value', function (e) {
            e.preventDefault();

            var valueSpan = $(this);
            var container = valueSpan.parent();
            var eventId = valueSpan.data('event-id');

            // Don't create multiple inputs
            if (container.find('.gatherpress-inline-edit').length > 0) {
                return;
            }

            // Hide the value span
            valueSpan.hide();

            // Create inline edit form
            var editForm = $('<span class=\"gatherpress-inline-edit\"></span>');
            var input = $('<input type=\"number\" class=\"gatherpress-inline-input\" min=\"1\" />');
            var saveBtn = $('<button class=\"button button-small gatherpress-inline-save\">Save</button>');
            var cancelBtn = $('<button class=\"button button-small gatherpress-inline-cancel\">Cancel</button>');

            input.val('');

            editForm.append(input).append(saveBtn).append(cancelBtn);
            container.append(editForm);

            input.focus().select();

            // Handle save
            var saveEdit = function () {
                var newValue = input.val();

                // Disallow 0 or empty
                if (newValue === '' || newValue === '0' || newValue < 1) {
                    alert('Please enter a valid attendee count (minimum 1).');
                    return;
                }

                saveBtn.prop('disabled', true).text('Saving...');
                cancelBtn.prop('disabled', true);

                // Add saving state to container
                container.addClass('gatherpress-saving');

                $.ajax({
                    url: gatherpressAttendeeCount.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'gatherpress_attendee_count_update_attendee_count',
                        nonce: gatherpressAttendeeCount.nonce,
                        event_id: eventId,
                        attendee_count: newValue
                    },
                    success: function (response) {
                        if (response.success) {
                            // Remove the entire event item from the list
                            var eventItem = container.closest('.gatherpress-event-item');
                            eventItem.fadeOut(300, function () {
                                $(this).remove();

                                var remainingEvents = $('#gatherpress-events-list li').length;
                                if (remainingEvents === 0) {
                                    $('#gatherpress_attendee_count_widget .inside').html(
                                        '<div class=\"no-activity\">' +
                                        '<p class=\"smiley\" aria-hidden=\"true\"></p>' +
                                        '<p>All events have attendee count information!</p>' +
                                        '</div>'
                                    );
                                }
                            });
                        } else {
                            alert(response.data.message || 'Failed to update attendee count.');
                            container.removeClass('gatherpress-saving');
                            saveBtn.prop('disabled', false).text('Save');
                            cancelBtn.prop('disabled', false);
                        }
                    },
                    error: function () {
                        alert('An error occurred. Please try again.');
                        container.removeClass('gatherpress-saving');
                        saveBtn.prop('disabled', false).text('Save');
                        cancelBtn.prop('disabled', false);
                    }
                });
            };

            // Handle cancel
            var cancelEdit = function () {
                editForm.remove();
                valueSpan.show();
            };

            saveBtn.on('click', function (e) {
                e.preventDefault();
                saveEdit();
            });

            cancelBtn.on('click', function (e) {
                e.preventDefault();
                cancelEdit();
            });

            input.on('keypress', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    saveEdit();
                }
            });

            input.on('keydown', function (e) {
                if (e.which === 27) {
                    e.preventDefault();
                    cancelEdit();
                }
            });
        });
    });
})(jQuery);