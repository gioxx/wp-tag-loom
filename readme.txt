=== Tag Loom - Taxonomy Manager ===
Contributors: gioxx
GitHub Plugin URI: https://github.com/gioxx/wp-tag-loom
Tags: tags, taxonomy, terms, cleanup, ai
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.24.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage tags and taxonomy terms with or without AI: merge/cleanup suggestions via WordPress core's AI Client, or search, merge, delete manually.

== Description ==
Tag Loom sends your post tags in batches to whichever AI provider the site owner has configured in WordPress core's AI Client, and asks it to spot near-duplicates, semantic overlaps, and low-usage tags that could be merged into a broader existing tag. Nothing is changed automatically: every suggestion is queued for review and only applied when you approve it. Alongside the AI analysis, a "Manage Tags" tab covers manual, non-AI tag housekeeping: usage statistics, search, merge, and delete.

Tag Loom does not ship its own AI integration or store an API key. It relies entirely on the AI Client bundled in WordPress 7.0, so it works with any provider you have set up (Anthropic, OpenAI, Google, and others) and the provider can be swapped at the site level without touching the plugin.

Features:
* Batch analysis of all tags through the core AI Client, with a live processing log, progress tracking, and a stop button
* Merge suggestions grouped by type: near-duplicates, semantic overlaps, low-usage tags
* Per-suggestion Approve/Reject/Restore, plus multi-select bulk actions (bulk approve, bulk reject, bulk restore) on each suggestions table
* Rejected suggestions are kept (not discarded) and can be restored to pending at any time
* A "Manage Tags" tab for non-AI tag housekeeping: a usage-distribution histogram, a searchable/sortable/paginated table of every tag in use (each linking to its filtered post list), individual or bulk tag deletion, and a manual merge tool to combine any 2+ tags regardless of naming
* Unused tag detection (0 posts) with a bulk delete and a "recount tag counts" tool to fix drifted counts
* Optional model preference, configurable batch size, and a response language for the suggestion "reason" text, independent of the plugin's own UI language
* "Check AI provider" button to confirm a provider is configured before running an analysis
* English by default; translatable via WordPress.org's translation platform (translate.wordpress.org)
* Git Updater compatible for seamless updates from GitHub

= Third-party service =
The "AI Analysis" tab uses WordPress core's AI Client (introduced in WordPress 7.0). When you click "Start analysis", the plugin hands batches of tag names and their post counts (no post content, no personal data) to the AI Client, which forwards them to whichever AI provider the site owner has configured, and stores the returned suggestions for your review. The plugin itself contacts no external service and holds no credentials: the provider, its endpoint, and its API key are all managed by WordPress core. Nothing is sent automatically, on activation, or in the background, and the entire "Manage Tags" tab (search, merge, delete, unused-tag cleanup) works without any AI provider at all. Review the privacy policy and terms of the specific AI provider you configure.

== Installation ==
1. Requires WordPress 7.0 or newer (for the bundled AI Client).
2. Copy the whole plugin folder to `wp-content/plugins/tag-loom/`.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Configure an AI provider in the WordPress settings (only needed for the "AI Analysis" tab).
5. Go to **Posts → Tag Loom** and use "Manage Tags", or click "Start analysis" on the "AI Analysis" tab.

== Frequently Asked Questions ==
= Does this change my tags automatically? =
No. Every merge suggestion requires manual approval before anything is changed.

= What happens to a rejected suggestion? =
It's kept in a "Rejected suggestions" list and can be restored to pending at any time, individually or in bulk.

= Can I delete tags with 0 posts? =
Yes, the "Unused tags" table lists them with a bulk delete option.

== Changelog ==
= 0.24.2 =
* Security: the per-user list filters that persist across page loads (usage bucket, "Hide locked tags", quick-sort order) now require a valid `wpto_filter_prefs` nonce before writing to user meta. Forged cross-site links can no longer alter another logged-in user's saved filter preferences; the filter is still applied to the current view. Addresses a WordPress.org directory review finding on `get_active_bucket()`.

= 0.24.1 =
* Renamed the plugin again, from "Termcraft - Taxonomy Manager" to "Tag Loom - Taxonomy Manager" (display name, slug/text domain, main file, GitHub repository), after the WordPress.org directory review rejected "Termcraft" as already used as another project's distinctive name.

= 0.24.0 =
* Renamed the plugin from "Smart Tags Optimizer" to "Termcraft - Taxonomy Manager" (display name, slug/text domain, main file) for the WordPress.org directory review: the former name was flagged as too generic and close to an existing "Smart Tags" term.
* Replaced the direct Anthropic Claude integration with WordPress core's AI Client (`wp_ai_client_prompt()`). The plugin no longer stores an API key or contacts any provider directly; the site owner configures a provider once in the WordPress settings and can use any supported provider.
* Bumped the minimum WordPress version to 7.0 (the AI Client is bundled there).
* Settings: removed the "Anthropic API Key" field; the model field is now an optional comma-separated model preference; "Test API key" is now "Check AI provider".
* Moved the admin-notice URL cleanup from an inline `<script>` block to the enqueued admin script.

= 0.23.3 =
* Fixed the "All tags" table staying empty after an Ajax action removed every row on the current page; it now auto-reloads.
* WordPress.org Plugin Check cleanup ahead of the directory submission (readme header, nonce-verification and DB-query sniff comments, dropped unused Domain Path header).
* Added the WordPress.org-required "Third-party service" disclosure to the readme's Description, and a matching note next to the Anthropic API Key field in Settings: what's sent, when, and links to Anthropic's Privacy Policy and Terms of Service.
* Corrected stale "included Italian translation" claims in `readme.txt`, `README.md`, and `README.it.md`: no `.mo`/`.po` files are bundled anymore (WordPress.org directory guidelines require translations through translate.wordpress.org instead).

= 0.23.2 =
* Fixed locked tags being unable to enter the merge selection at all, even as the merge target (which always survives — only source tags get deleted). Locked tags remain fully protected as a merge source.

= 0.23.1 =
* Fixed the "All tags" bulk actions (Lock/Unlock/Delete) showing a "Please select a bulk action to perform." alert even though the action had actually run.

= 0.23.0 =
* Added a "Hide locked tags" checkbox to the "All tags" table, checked by default.
* Fixed the "All tags" table losing an active search/sort/bucket filter when paging past page 1.
* Converted single-row lock toggle, single-row delete, and the Lock/Unlock/Delete bulk actions on the "All tags" table to Ajax, so they no longer reload the page or drop the current search/sort/pagination state.

= 0.22.2 =
* Removed the duplicate lock icon shown under the checkbox on locked rows; the "Lock" column icon alone now shows state (red closed lock = locked, gray open lock = unlocked).

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
= 0.24.0 =
Requires WordPress 7.0+. The plugin was renamed to Tag Loom and now uses core's AI Client instead of a stored Anthropic key; configure an AI provider in WordPress settings to use the "AI Analysis" tab.

= 0.5.0 =
Plugin, folder and text domain were renamed from wp-tags-optimizer to ai-tags-optimizer; reinstall from the new plugin folder if updating manually.
