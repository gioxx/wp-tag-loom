# Changelog

All notable changes to this plugin are documented in this file.

## [0.23.0] - 2026-07-31

- Added a "Hide locked tags" checkbox to the "All tags" table (below the Quick sort links), checked by default, so locked tags stay out of the way while working the list. Persisted per-user like the usage-bucket filter (`WPTO_Admin_Page::get_hide_locked_tags()`); filters via a `meta_query` on `wpto_locked` in both `wp_count_terms()` and `get_terms()` in `WPTO_Tag_Stats_Table::prepare_items()`.
- Fixed the "All tags" table losing an active search (and sort/bucket filter) when paging past page 1. The `#wpto-tags-filter` form posted via `method="post"`, so `s`/`orderby`/etc. never made it into the URL that `WP_List_Table`'s pagination links are built from; switched the form to `method="get"` and updated the bulk-action handlers (`maybe_process_stats_tab_actions()`, `process_bulk_delete()`, `process_bulk_toggle_lock()`, the `add_to_merge` branch in `render_stats_tab()`) to read `tag_id[]`/`action`/`action2` from `$_REQUEST` instead of `$_POST` to match.
- Converted single-row lock toggle, single-row delete, and the "Lock"/"Unlock"/"Delete" bulk actions on the "All tags" table to Ajax (new `wp_ajax_wpto_toggle_lock`, `wp_ajax_wpto_bulk_lock`, `wp_ajax_wpto_delete_tags` handlers), so those no longer reload the page and drop the current search/sort/pagination state. Rows update or fade out in place; the "Hide locked tags" checkbox is respected when a row gets locked via Ajax. The full-page-reload routes (`process_toggle_lock()`, `process_bulk_delete()`, `process_bulk_toggle_lock()`) are kept as a no-JS fallback. The "Add to merge selection" bulk action is intentionally left as a normal (non-Ajax) submit, since it needs to redraw the merge basket section below the table anyway.

## [0.22.2] - 2026-07-31

- Removed the duplicate lock dashicon rendered under the checkbox on locked rows (`column_cb()` in `includes/class-wpto-tag-stats-table.php`); the checkbox alone is enough since the "Lock" column already shows state. The "Lock" column icon now also colors itself (red closed lock when locked, gray open lock when unlocked via new `.wpto-toggle-lock--locked`/`--unlocked` CSS classes) instead of relying solely on icon shape.

## [0.22.1] - 2026-07-31

- Added "Lock"/"Unlock" bulk actions to the "All tags" table's Bulk actions dropdown. Locked tags are checkbox-selectable again (previously hidden entirely) so they can be picked for a bulk unlock; destructive bulk actions (delete, add to merge) still drop locked ids server-side via `WPTO_Tag_Lock::filter_unlocked()`.
- Fixed `process_add_tags_by_name()` and the bulk "add to merge selection" action counting locked tags as added to the merge basket, when `add_to_merge_basket()` was actually silently filtering them out. Locked tags are now excluded from the "added" count up front and surfaced in a separate "Skipped locked tag(s)" notice.

## [0.22.0] - 2026-07-30

- Added a lock toggle to the "All tags" table (new "Lock" column): a locked tag can't be selected via checkbox, can't be added to the merge basket (manually or by an approved AI suggestion), and can't be deleted, until unlocked again. Backed by term meta (`wpto_locked`), cleaned up on uninstall when "Remove all plugin settings and export data" is enabled.
- Labeled the "AI Analysis" tab and its headings as "(experimental)" to set expectations that AI-suggested merges are a newer, less battle-tested feature than the manual tools.
- Dropped the bundled `languages/*.po`/`.mo`/`.pot` files from the repository (regenerate locally as needed) and excluded them via `.gitignore`, matching the WordPress.org review guidance to rely on translate.wordpress.org for hosted translations instead of bundling them.

## [0.21.2] - 2026-07-19

- Added the `Release Asset: true` plugin header so Git Updater installs the zip attached to the GitHub Release instead of the raw `main` branch zip.

## [0.21.1] - 2026-07-18

- Silenced the remaining WordPress Plugin Check warnings on `$wpdb->insert()`/`update()`/`delete()` calls in `includes/class-wpto-suggestions-repo.php` (`create_batch`, `mark_batch_done`, `mark_batch_failed`, `retry_batch`, `cancel_pending_batches`, `clear_for_new_analysis`, `insert_suggestions`, `set_suggestion_status`, `mark_applied`): they write to the plugin's own custom tables through the safe wpdb API, with no core alternative for non-WP tables.

## [0.21.0] - 2026-07-18

- Fixed WordPress Plugin Check findings on `includes/class-wpto-suggestions-repo.php`, `includes/class-wpto-unused-tags.php`, and `uninstall.php`: the existing `phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedTableName` comments referenced a sniff code that doesn't exist, so none of them were actually suppressing anything. Replaced with the correct codes (`DirectQuery`, `NoCaching`, `InterpolatedNotPrepared`, `PluginCheck.Security.DirectDB.UnescapedDBParameter`, etc.) and a justification, since every affected query targets the plugin's own custom tables via a static, non-user-controlled name.
- Added missing `translators:` comments for the two merge-count `_n()` strings in `includes/class-wpto-admin-page.php`, and explicitly cast their count argument before output.
- Silenced nonce-verification warnings on read-only admin dispatch/listing code paths (`maybe_process_stats_tab_actions()`, the tag stats table's search/sort) that already verify the nonce inside the handler they call, or don't mutate state.
- Shortened the readme short description to fit WordPress.org's 150-character limit.

## [0.20.0] - 2026-07-18

- Renamed the plugin from "Smart Tags for WordPress" to "Smart Tags Optimizer" (display name, text domain `ai-tags-optimizer` → `smart-tags-optimizer`, and translation files renamed/regenerated to match) ahead of WordPress.org directory submission: "WordPress" is a restricted term and cannot appear in a plugin name or slug at all.
- Removed the deprecated `load_plugin_textdomain()` call (WordPress auto-loads translations for hosted plugins since 4.6).
- Updated the readme "Tested up to" header to 7.0 and excluded `README.it.md` from the release zip built by the GitHub Actions workflow.

## [0.19.0] - 2026-07-16

- Added a "Filtering by usage: X posts" notice with a "Clear filter" link right above the "All tags" table itself (not just next to the histogram), since clicking a histogram bar now auto-scrolls straight to the table and the original notice was out of view.
- Fixed keyboard use of the "Add tags by name" autocomplete: arrow keys already navigated suggestions, but pressing Enter on a highlighted one could submit the form instead of confirming it; Enter (like Tab) now confirms the highlighted suggestion. Also added a visible highlight color, since the default was effectively invisible in wp-admin.
- Added a short intro box above the tabs, explaining what "Manage Tags" and "AI Analysis" each do, for anyone landing on the page without prior context.
- Clicking a usage-distribution histogram bar now auto-scrolls down to the "All tags" table so the applied filter is actually visible, instead of leaving you on top of the reloaded page.

## [0.18.1] - 2026-07-14

- Swapped the tab order and default: "Manage Tags" is now first and what loads by default, "AI Analysis" moved second, reflecting that day-to-day tag management (not AI-driven analysis) has become the primary use case.
- Merged the "Overview" stat tiles and the "Usage distribution" histogram into a single side-by-side panel instead of two stacked blocks, and narrowed the histogram accordingly.
- Adding tags to the merge selection (via the table's bulk action or the "Add tags by name" field) now auto-scrolls down to the "Merge selection" section instead of leaving you on top of the reloaded page.
- Fixed the "Add tags by name" panel jumping to the very top of the page (above the tabs) instead of staying under "Merge selection": it used the `notice` CSS class, which WordPress core auto-relocates to the top of the screen regardless of where it is in the markup.
- Fixed the "AI Analysis" stat tiles (Pending suggestions / Merges applied / Rejected suggestions) stacking full-width instead of sitting side by side; the column layout meant for the new Overview panel was leaking into the shared `.wpto-stats` class.
- Toned down how far the floating "back to top" button was nudged up (70px was overkill for clearing the footer credits).

## [0.18.0] - 2026-07-14

- Reordered "Manage Tags" into a clearer flow: Overview (stat tiles + usage distribution histogram) → Unused tags → All tags → Merge selection (moved from the top to the bottom, after the table where tags are actually picked).
- Added a sticky section-nav bar at the top of "Manage Tags" with jump links to each section (smooth-scrolls instead of a hard jump), plus a floating "back to top" button that appears once you've scrolled down.

## [0.17.0] - 2026-07-14

- Added a "Add tags by name" text field to the merge selection bar on "Manage Tags": type comma-separated tag names (with the same autocomplete WordPress uses on the post-edit Tags box) to add existing tags straight to the merge selection, without hunting for them in the table below. Names that don't match an existing tag are reported, not created.
- Fixed the "Name" and "Assigned posts" column headers in the tag table rendering as blue hyperlinks (they're sortable links under the hood). They now read as plain bold labels like the rest of wp-admin, both above and below the table.

## [0.16.5] - 2026-07-14

- Fixed the "Manage Tags" success/error notices (merge, delete) staying stuck after being dismissed, because reloading the page resent the same `wpto_merged`/`wpto_deleted`/... query args. The URL is now cleaned up via `history.replaceState` right after the notice is shown, so a refresh no longer brings it back.

## [0.16.4] - 2026-07-14

- Fixed the post-merge admin notice trusting the `wpto_merged_target` URL parameter verbatim, letting a crafted link display an arbitrary "Merged into ..." message; the target tag name is now resolved server-side from its term ID.

## [0.16.3] - 2026-07-14

- Fixed the "Manage Tags" tag table hiding tags with 0 posts even when searching, making it impossible to find and add a freshly created empty tag (meant to act as a merge master) to the merge selection. Empty tags are now hidden only when there is no active search term.

## [0.16.2] - 2026-07-12

- The usage-bucket histogram filter is now remembered per user (same mechanism already used for sort order), so it survives a plain page reload/reopen of "Manage Tags" instead of resetting as soon as `?bucket=` drops out of the URL. Clicking "Clear filter" explicitly forgets it.

## [0.16.1] - 2026-07-12

- The usage-distribution histogram bars are now clickable: clicking one filters the tag table below to that exact range (e.g. "3-5" posts), with the active bar highlighted and a "Clear filter" link. The filter is preserved when searching, sorting, or paging within the table.

## [0.16.0] - 2026-07-12

- Fixed a real limitation in the manual merge workflow: selecting tags across different searches/pages/sort orders used to lose the previous selection, since checkboxes only survive within a single page. The bulk action is now "Add to merge selection", which accumulates tags into a persistent per-user selection (shown in a bar above the table, with its own "Prepare merge" and "Clear selection" controls) so tags found via unrelated searches can be merged together.
- Added Quick Edit to the tag table: an inline row (no page navigation) to rename a tag or change its slug via AJAX, opened from a new row action next to Edit/Delete.

## [0.15.3] - 2026-07-12

- Fixed the release workflow still building the zip artifact under the old `ai-tags-optimizer` slug; it's now `smart-tags-for-wordpress` to match the plugin's current name. The main plugin file, text domain, and install path inside `wp-content/plugins/` are unaffected.

## [0.15.2] - 2026-07-12

- Added a "Quick sort" row of one-click presets (Name A→Z / Z→A, Most/Least used) above the "Manage Tags" tag table, as a shortcut to the existing sortable column headers.
- The chosen sort order is now remembered per user (stored in user meta) and used as the default the next time the tab is opened, instead of always resetting to "Most used".
- Fixed the Settings page/menu title dropping "for WordPress" after the plugin rename ("Smart Tags: Settings" → "Smart Tags for WordPress: Settings").

## [0.15.1] - 2026-07-12

- Renamed the plugin to "Smart Tags for WordPress" to reflect its broader scope (AI analysis plus manual tag management, not just AI-driven optimization). Display name only: the plugin folder, main file, text domain, and internal class/constant prefixes are unchanged.
- Renamed the GitHub repository from `wp-ai-tags-optimizer` to `wp-smart-tags` and updated the `GitHub Plugin URI`/`Plugin URI` headers accordingly. The plugin was not yet public, so no migration/redirect handling was needed.

## [0.15.0] - 2026-07-12

- Fixed the usage-distribution histogram always showing zero: `get_terms()` was called with the unsupported `fields => 'id=>count'`, silently dropping the `count` property; now uses `fields => 'all'`.
- Renamed the two tabs for clarity: "Optimizer" is now "AI Analysis" (purely AI-driven), "Tag Statistics" is now "Manage Tags" (all non-AI tag housekeeping). The "Unused tags (0 posts)" section moved from "AI Analysis" into "Manage Tags", alongside a new quick-stats row (tags in use / unused tags).
- Added tag deletion to "Manage Tags": a per-row "Delete" action and a "Delete" bulk action, both with confirmation.
- The tags-per-page count on "Manage Tags" is now configurable via the native WordPress "Screen Options" panel (default stays 20), instead of being hardcoded.

## [0.14.0] - 2026-07-12

- Added a "Tag Statistics" tab (no AI involved): a searchable, sortable, paginated table of tags in use (name, slug, assigned-post count linking to the filtered post list) plus a usage-distribution histogram for an at-a-glance read of the taxonomy.
- Added a manual merge workflow on the new tab: select any 2+ tags via checkbox regardless of name, confirm which one survives, and merge immediately — no need to give unrelated tags a common search term as required by WordPress's native Tags screen, and no AI involved.

## [0.13.4] - 2026-07-12

- Moved the operational "AI Tags Optimizer" page from Tools to Posts (where tags are managed), keeping the Settings page under Tools. The two pages now cross-link to each other.

## [0.13.3] - 2026-07-12

- Added `autocomplete="off"` to all suggestion/unused-tag checkboxes so the browser no longer restores their checked state after a page reload, which was misleading since the underlying rows change on every refresh.

## [0.13.2] - 2026-07-12

- Fixed a bug where Claude could propose the same tag pair in both directions ("A into B" and "B into A") as two separate suggestions; only one direction is now kept. Added an explicit prompt rule against this, plus a code-level guard against both directions coexisting as pending or against a pair the user already rejected.
- Pending suggestions whose target tag or every source tag no longer exists (e.g. left behind by approving the opposite-direction duplicate first) are now auto-pruned on page load, same as the existing rejected-suggestions cleanup.

## [0.13.1] - 2026-07-12

- Rejected suggestions are now automatically pruned when they can no longer be meaningfully restored: their target tag was deleted/merged away, or every one of their source tags was. Keeps the "Rejected suggestions" list free of orphaned entries.

## [0.13.0] - 2026-07-12

- Rejecting a suggestion now permanently suppresses that specific tag pairing (in either direction) from future analyses, as long as it stays in the "Rejected suggestions" list. Restoring a rejected suggestion lifts the suppression for that pair.

## [0.12.1] - 2026-07-12

- The "Merge into" selector on each suggestion now sits on its own line above the Approve/Reject buttons instead of being crammed inline with them, and never overflows its table column: long tag names are truncated with an ellipsis when closed, while the opened dropdown still shows the full name.

## [0.12.0] - 2026-07-12

- Analysis batches now always include the most-used tags ("anchors", up to 100) alongside a rotating slice of the rest, so low-usage/single-post tags are compared against real, popular candidates instead of whatever happens to sort near them alphabetically. Duplicate suggestions caused by anchor repetition across batches are automatically skipped.
- Each pending suggestion now has a "Merge into" selector listing every tag involved (source(s) + proposed target): pick a different one before clicking Approve to merge everything else into that tag instead of the one Claude proposed.

## [0.11.0] - 2026-07-12

- Starting a new analysis now clears any unreviewed pending suggestions and the batch log first, so results always reflect the current tag set instead of piling up on top of a stale previous run. Rejected suggestions and the applied-suggestions history are left untouched. A confirmation dialog was added before starting.
- Tightened the Claude system prompt for `semantic_overlap` and `low_usage_merge` suggestions (more prone to false positives than `near_duplicate`): now requires a confidence of 0.7+ and explicitly favors omitting a suggestion over a low-confidence one.

## [0.10.0] - 2026-07-12

- Added an "Applied suggestions (history)" table showing the most recent merges (last 50), read-only, with source/target tag names snapshotted at approval time since the source tags are deleted by the merge.
- Added stat tiles at the top of the main page: pending suggestions, merges applied, rejected suggestions, unused tags.
- Added a `wpto_db_version` upgrade check so existing installs pick up the new `applied_at`/`source_names`/`target_name` columns without reactivating the plugin.

## [0.9.0] - 2026-07-12

- Added a "Test API key" button on the Settings page, verifying the Anthropic API key against a minimal live request before saving.
- Added Git Updater support: `GitHub Plugin URI`, `GitHub Branch`, and `GitHub Languages` headers, plus `Requires at least` / `Requires PHP`, so the plugin can be updated directly from the GitHub repository.
- Added a "Settings" quick link on the Plugins list screen, and a credits/attribution footer (author + repo link, trademark notice) on the Settings page.
- Added `readme.txt` (WordPress.org style) and `README.md` / `README.it.md` (GitHub-facing docs).
- Enqueued admin assets on the Settings screen too (previously only loaded on the main Tools page).

## [0.8.0] - 2026-07-12

- Added multi-select checkboxes and bulk actions to the Suggestions tables: "Approve selected" / "Reject selected" per pending group (Near-duplicates, Semantic overlaps, Low-usage tags), and "Restore selected" for rejected suggestions. Each table has its own independent selection.
- Updated the Italian translation to cover the new bulk-action strings.

## [0.7.0] - 2026-07-12

- Made the uninstall cleanup (removing plugin data on uninstall) optional, enabled by default.

## [0.6.0] - 2026-07-12

- Added a setting for Claude's response language, independent from the plugin UI language.

## [0.5.0] - 2026-07-12

- Renamed the plugin to "AI Tags Optimizer for WordPress" (GitHub repo/folder and main plugin file renamed from `wp-tags-optimizer` to `ai-tags-optimizer`; text domain changed to `ai-tags-optimizer`).
- Switched all user-facing strings (admin UI, error messages, the Claude system prompt) to English as the source language.
- Added `load_plugin_textdomain()` and a full Italian translation, so the plugin shows in Italian on `it_IT` sites and English elsewhere.

## [0.4.0] - 2026-07-12

- Rejected suggestions are now kept instead of discarded, with a "Restore" action to bring them back to pending.

## [0.3.0] - 2026-07-12

- Added a live processing log during analysis.
- Added a stop button to interrupt an in-progress analysis.
- Fixed an issue where analysis could start twice concurrently.

## [0.2.0] - 2026-07-12

- Added a "Recount tag counts" button to fix per-tag post counts that drifted out of sync (e.g. after an import).

## [0.1.0] - 2026-07-11

- Initial implementation of the plugin (WP Tags Optimizer): tag analysis, merge suggestions, unused tag detection.
- Fixed a false-positive issue in unused tag detection.
