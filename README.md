# Tag Loom - Taxonomy Manager

*[README disponibile anche in italiano](README.it.md)*

A WordPress plugin to manage your post tags and taxonomy terms with or without AI: get merge/cleanup suggestions through WordPress core's AI Client, or search, merge, and delete tags manually. Nothing is changed automatically — every AI suggestion requires manual approval.

## Requirements

- WordPress 7.0 or higher (bundles the core AI Client)
- PHP 7.4 or higher
- An AI provider configured in WordPress core's AI Client (only for the "AI Analysis" tab)

## Installation

1. Copy the whole plugin folder to `wp-content/plugins/tag-loom/` on your WordPress site.
2. Go to **Plugins** in the WordPress dashboard and activate "Tag Loom".

## Language

The plugin interface is in English by default. Translations (including Italian) are handled via [translate.wordpress.org](https://translate.wordpress.org/) rather than bundled with the plugin. This is independent from the language the AI uses when writing the "reason" for each suggestion, which is configured separately in Settings.

## Configuration

First configure an AI provider in the WordPress settings (WordPress 7.0's AI Client). Then go to **Tools → Tag Loom: Settings** and, if needed, adjust:

| Field | Description |
|---|---|
| **AI provider** | Use the **"Check AI provider"** button to confirm a provider is configured. Credentials live in WordPress core, not in this plugin. |
| **Preferred model(s)** | Optional. Comma-separated model IDs in priority order (e.g. `claude-haiku-4-5, gpt-5.4`); the AI Client uses the first one your provider supports. Blank = provider default. |
| **Batch size** | Number of tags sent per AI request (10-500). |
| **AI response language** | Language the AI should use for the "reason" text on each suggestion. Leave blank to match the language of your tag names automatically. |
| **Full cleanup on uninstall** | When checked (default), deleting the plugin removes its settings and suggestion/batch history from the database. |

## Running an analysis

1. Go to **Posts → Tag Loom**, on the **"AI Analysis"** tab.
2. Click **"Start analysis"**. Tags are processed in batches, with a live processing log and progress indicator; use **"Stop analysis"** to interrupt it.
3. Once batches complete, suggestions appear grouped by type:
   - **Near-duplicates** — textual near-duplicates (typos, plurals, casing, hyphens/spaces)
   - **Semantic overlaps** — different wording, overlapping meaning
   - **Low-usage tags** — very low usage tags that could merge into a broader existing tag

## Reviewing suggestions

Each suggestion can be **Approved** (merges the source tag(s) into the target tag), **Rejected**, or later **Restored** from the "Rejected suggestions" list back to pending. Every table also supports multi-select with a "select all" checkbox and bulk **Approve selected / Reject selected / Restore selected** actions — selection and bulk actions are scoped independently per table.

## Managing tags without AI

The **"Manage Tags"** tab (next to "AI Analysis" on the same page) is where all the non-AI, manual tag housekeeping lives:

- A usage-distribution histogram for an at-a-glance read of your taxonomy — click a bar to filter the table below to that exact range.
- The **"Unused tags (0 posts)"** table, with a bulk delete option; use **"Recount tag counts"** if counts look wrong (e.g. after an import).
- A searchable, sortable, paginated table of every tag in use, each linking straight to its filtered post list. Use the screen options panel (top right) to change how many tags are shown per page.
- **Delete** any tag individually (row action) or in bulk.
- **Quick Edit** any tag inline (row action) to rename it or change its slug, without leaving the page.
- **Merge** any 2+ tags regardless of how their names differ, even if you find them via separate searches: select tags via checkbox and use **"Add to merge selection"** to build up a persistent selection (shown in a bar above the table, across searches/pages/sorting), then **"Prepare merge"**, choose which tag should survive from a "Merge into" dropdown, and confirm. This skips the need to give unrelated tags a common search term just to find them together, which WordPress's native Tags screen requires — and no AI is involved.

## Updates

The plugin is compatible with [Git Updater](https://git-updater.com/), so it can be kept up to date directly from the [GitHub repository](https://github.com/gioxx/wp-tag-loom) without going through WordPress.org.
