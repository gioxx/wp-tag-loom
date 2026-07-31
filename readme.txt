=== Smart Tags Optimizer ===
Contributors: gioxx
GitHub Plugin URI: https://github.com/gioxx/wp-smart-tags
Tags: tags, ai, claude, taxonomy, cleanup
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.22.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage tags with or without AI: get Claude suggestions for merging duplicates and flagging unused tags, or search, merge, and delete tags manually.

== Description ==
Smart Tags Optimizer sends your post tags to the Claude API (Anthropic) in batches and asks it to spot near-duplicates, semantic overlaps, and low-usage tags that could be merged into a broader existing tag. Nothing is changed automatically: every suggestion is queued for review and only applied when you approve it. Alongside the AI analysis, a "Manage Tags" tab covers manual, non-AI tag housekeeping: usage statistics, search, merge, and delete.

Features:
* Batch analysis of all tags via the Claude API, with a live processing log, progress tracking, and a stop button
* Merge suggestions grouped by type: near-duplicates, semantic overlaps, low-usage tags
* Per-suggestion Approve/Reject/Restore, plus multi-select bulk actions (bulk approve, bulk reject, bulk restore) on each suggestions table
* Rejected suggestions are kept (not discarded) and can be restored to pending at any time
* A "Manage Tags" tab for non-AI tag housekeeping: a usage-distribution histogram, a searchable/sortable/paginated table of every tag in use (each linking to its filtered post list), individual or bulk tag deletion, and a manual merge tool to combine any 2+ tags regardless of naming
* Unused tag detection (0 posts) with a bulk delete and a "recount tag counts" tool to fix drifted counts
* Configurable AI model, batch size, and response language for Claude's suggestion "reason" text, independent of the plugin's own UI language
* "Test API key" button to verify the Anthropic API key before saving
* English by default, with an included Italian translation
* Git Updater compatible for seamless updates from GitHub

== Installation ==
1. Copy the whole plugin folder to `wp-content/plugins/smart-tags-optimizer/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Tools → Smart Tags Optimizer: Settings** and enter your Anthropic API key.
4. Go to **Posts → Smart Tags** and click "Start analysis".

== Frequently Asked Questions ==
= Does this change my tags automatically? =
No. Every merge suggestion requires manual approval before anything is changed.

= What happens to a rejected suggestion? =
It's kept in a "Rejected suggestions" list and can be restored to pending at any time, individually or in bulk.

= Can I delete tags with 0 posts? =
Yes, the "Unused tags" table lists them with a bulk delete option.

== Changelog ==
= 0.22.1 =
* Added "Lock"/"Unlock" bulk actions to the "All tags" table's Bulk actions dropdown, and made locked tags checkbox-selectable again (only for this purpose) so they can be picked for bulk unlock.
* Fixed the "add tags by name" and bulk "add to merge selection" flows silently dropping locked tags from the merge basket while still counting them as added; they're now excluded from the count and listed in a "Skipped locked tag(s)" notice.

= 0.22.0 =
* Added a "Lock" column to the "All tags" table: locking a tag prevents it from being checkbox-selected, merged (manually or via an approved AI suggestion), or deleted, until unlocked.
* Labeled the "AI Analysis" tab as "(experimental)".
* Dropped the bundled translation files from the repository in favor of translate.wordpress.org.

= 0.21.1 =
* Silenced the remaining Plugin Check warnings on `$wpdb->insert()`/`update()`/`delete()` calls in `includes/class-wpto-suggestions-repo.php`: they write to the plugin's own custom tables through the safe wpdb API, with no core alternative for non-WP tables.

= 0.21.0 =
* Fixed WordPress Plugin Check findings: existing `phpcs:ignore` comments on direct DB queries used a sniff code that doesn't exist, so they weren't actually suppressing anything; replaced with the correct codes and justifications.
* Added missing `translators:` comments for the merge-count strings and escaped their count argument.
* Silenced nonce-verification warnings on read-only admin dispatch/listing code paths that already verify the nonce in the handler they call.
* Shortened the readme short description to fit WordPress.org's 150-character limit.

= 0.20.0 =
* Renamed the plugin from "Smart Tags for WordPress" to "Smart Tags Optimizer" (display name, text domain, and translation files) ahead of WordPress.org directory submission: "WordPress" is a restricted term and cannot appear in a plugin name or slug at all.
* Removed the deprecated `load_plugin_textdomain()` call and updated the readme "Tested up to" header.

= 0.19.0 =
* Added a "Filtering by usage" notice with a "Clear filter" link right above the "All tags" table itself, since clicking a histogram bar now auto-scrolls straight to the table.
* Fixed keyboard use of the "Add tags by name" autocomplete: Enter on a highlighted suggestion now confirms it instead of possibly submitting the form; added a visible highlight color.
* Added a short intro above the tabs explaining what "Manage Tags" and "AI Analysis" each do.
* Clicking a usage-distribution histogram bar now auto-scrolls down to the filtered "All tags" table.

= 0.18.1 =
* "Manage Tags" is now the first tab and the default one, since day-to-day tag management has become the primary use case; "AI Analysis" moved second.
* Merged the "Overview" stat tiles and the usage-distribution histogram into one side-by-side panel.
* Adding tags to the merge selection now auto-scrolls to the "Merge selection" section instead of leaving you on top of the page.
* Fixed the merge-selection panel jumping to the very top of the page instead of staying under "Merge selection".
* Fixed the AI Analysis stat tiles stacking full-width instead of sitting side by side.

= 0.18.0 =
* Reordered "Manage Tags" into Overview → Unused tags → All tags → Merge selection.
* Added a sticky section-nav bar with jump links, plus a floating "back to top" button.

= 0.17.0 =
* Added an "Add tags by name" field to the merge selection bar: type comma-separated tag names, with WordPress's own tag autocomplete, to add existing tags straight to the merge selection.
* Fixed the "Name" and "Assigned posts" column headers rendering as blue hyperlinks instead of plain bold labels.

= 0.16.5 =
* Fixed success/error notices on "Manage Tags" staying stuck after being dismissed because a page refresh resent the same query args.

= 0.16.4 =
* Fixed the post-merge notice trusting a URL parameter verbatim, letting a crafted link display an arbitrary "Merged into ..." message; the tag name is now resolved server-side from its term ID.

= 0.16.3 =
* Fixed the tag table hiding tags with 0 posts even when searching, making it impossible to find and select a freshly created empty tag as a merge target.

= 0.16.2 =
* The usage-bucket histogram filter is now remembered per user and survives a plain page reload, instead of resetting as soon as the URL parameter drops out.

= 0.16.1 =
* The usage-distribution histogram bars are now clickable, filtering the tag table to that range; the filter is preserved through search, sort, and paging.

= 0.16.0 =
* Fixed the manual merge workflow losing your selection when searching for a second tag. The bulk action is now "Add to merge selection", which builds a persistent per-user selection across searches/pages before you prepare the merge.
* Added Quick Edit (inline rename/slug change via AJAX) to the tag table on "Manage Tags".

= 0.15.3 =
* Fixed the release workflow still building the zip artifact under the old "ai-tags-optimizer" slug; it's now "smart-tags-for-wordpress".

= 0.15.2 =
* Added a "Quick sort" row of one-click presets above the "Manage Tags" tag table, and the chosen sort order is now remembered per user.
* Fixed the Settings page/menu title dropping "for WordPress" after the plugin rename.

= 0.15.1 =
* Renamed the plugin to "Smart Tags Optimizer" to reflect its broader scope (AI analysis plus manual tag management). Display name only: the plugin folder, main file, text domain, and settings are unchanged.

= 0.9.0 =
* Added a "Test API key" button on the Settings page to verify the Anthropic API key before saving.
* Added Git Updater support (GitHub Plugin URI/Branch/Languages headers) for direct updates from the GitHub repository.
* Added a "Settings" quick link on the Plugins list screen, and a credits/attribution footer on the Settings page.

= 0.8.0 =
* Added multi-select checkboxes and bulk actions (Approve/Reject/Restore) to the Suggestions tables.

= 0.7.0 =
* Made the uninstall cleanup optional, on by default.

= 0.6.0 =
* Added a setting for Claude's response language, independent of the plugin UI language.

= 0.5.0 =
* Renamed the plugin to "AI Tags Optimizer for WordPress"; switched to English as the source language with a full Italian translation.

= 0.4.0 =
* Rejected suggestions are now kept instead of discarded, with a "Restore" action.

= 0.3.0 =
* Added a live processing log, a stop button, and fixed duplicate analysis starts.

= 0.2.0 =
* Added a "Recount tag counts" button.

= 0.1.0 =
* Initial implementation: tag analysis, merge suggestions, unused tag detection.

== Upgrade Notice ==
= 0.5.0 =
Plugin, folder and text domain were renamed from wp-tags-optimizer to ai-tags-optimizer; reinstall from the new plugin folder if updating manually.
