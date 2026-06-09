---
slug: getting-started-with-webberzone-code-block-highlighting
title: "Getting Started with WebberZone Code Block Highlighting"
products: [code-block-highlighting]
sections: [01-wzcbh-getting-started]
tags: [code-block-highlighting, installation]
status: publish
order: 0
---

WebberZone Code Block Highlighting extends the native WordPress Gutenberg `core/code` block with syntax highlighting, and is available for free on WordPress.org.

## WordPress install (The easy way)

1. Go to **Plugins > Add New**.
2. Search for **WebberZone Code Block Highlighting**.
3. Click **Install Now**, then **Activate**.

## Manual install

1. Download the latest release from [WordPress.org](https://wordpress.org/plugins/webberzone-code-block-highlighting/) or from [GitHub Releases](https://github.com/WebberZone/webberzone-code-block-highlighting/releases).
2. Unzip the archive and upload the `webberzone-code-block-highlighting` folder to `/wp-content/plugins/`.
3. Go to **Plugins** in your WordPress admin and activate **WebberZone Code Block Highlighting**.

## Installing via WP CLI

```bash
wp plugin install webberzone-code-block-highlighting --activate
```

On a Multisite network, use `--activate-network` to network-activate the plugin:

```bash
wp plugin install webberzone-code-block-highlighting --activate-network
```

## Using WebberZone Code Block Highlighting

After activation, open the block editor and add a **Code** block (or select an existing one). A **Code Highlighting** panel appears in the Inspector Controls sidebar on the right. Use that panel to choose the language, color scheme, and other display options for that block.

The plugin works as a filter on top of `core/code` — it never replaces the block. If you deactivate the plugin, syntax highlighting is removed but all code blocks remain intact and valid. No content is lost.
