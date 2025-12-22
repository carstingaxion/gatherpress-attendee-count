
=== GatherPress Attendee Count ===

Contributors:      carstenbach & WordPress Telex
Tags:              block, gatherpress, events, dashboard
Tested up to:      6.8
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Monitor GatherPress events with missing attendee counts directly from your WordPress dashboard.

== Description ==

GatherPress Attendee Count provides an admin dashboard widget that displays a list of GatherPress events that are missing attendee count information. This helps event organizers quickly identify which events need attention and ensure all event data is complete.

**Features:**

* Admin dashboard widget showing events with empty `gatherpress_attendee_count` meta field
* Clean, organized list view of events needing attendee count updates
* Direct links to edit events from the dashboard
* Lightweight and efficient queries

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/gatherpress-attendee-count` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to your WordPress Dashboard to see the new "GatherPress Attendee Count" widget
4. The widget will automatically display any GatherPress events missing attendee counts

== Frequently Asked Questions ==

= Does this plugin require GatherPress? =

Yes, this plugin is designed to work with GatherPress events and requires GatherPress to be installed and active.

= What does "missing attendee count" mean? =

The plugin looks for GatherPress events where the `gatherpress_attendee_count` post meta field is empty or not set, indicating that attendee information hasn't been recorded for that event.

= Can I use this on the frontend? =

No, the plugin provides only an admin dashboard widget and a custom column on the events list admin page.

== Screenshots ==

1. Admin dashboard widget showing GatherPress events with missing attendee counts
2. Custom column on the events list admin page

== Changelog ==

= 0.1.0 =
* Initial release
* Admin dashboard widget for monitoring GatherPress events

