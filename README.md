# Content Sync

A plugin to sync content between WordPress installations using the WordPress REST API.

## Features

- **Source Mode**: Allows you to sync content from your WordPress site to another WordPress site.
  - Selective syncing
  - Media syncing
  - Syncing custom post types
- **Destination Mode**: Allows you to receive content from another WordPress site and import it into your site.

> [!IMPORTANT]
> Any custom post types MUST already be set up on the destination site for custom post type syncing to work.

## Installation

1. Download the [latest release](https://github.com/ksolomon75/WP-Content-Sync/releases/latest/download/WP-Content-Sync.zip) of the plugin.
2. Upload and activate the plugin on both sites.

## Usage

1. Go to the 'Settings' menu in the WordPress admin area.
2. Select the 'Content Sync' option.
3. Choose the mode for the plugin (source or destination).
   - Source mode: Sync content from your WordPress site to another WordPress site.
   - Destination mode: Receive content from another WordPress site and import it into your site.
     - If you are syncing custom post types, make sure they are set up on the destination site.
4. Create an application password on the destination site.
5. Configure the destination URL, username, and application password on source site.
6. Go to the Sync Content admin page, select the items to sync, and click 'Sync Selected Content'.

## Changelog

### Version 0.1

- Initial release.

### Version 0.2

- Fixed bug with saving settings.

### Version 0.3

- Improved error handling.
- Added support for selective syncing.

### Version 0.4

- Initial support for importing media files.

### Version 0.5

- Consolidated settings and updated styling of content table

### Version 0.6

- Added support for syncing custom post types

### Version 0.7

- Added support for syncing post metadata (categroies, tags, featured images, etc)
