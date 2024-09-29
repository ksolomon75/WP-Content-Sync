# Content Sync

A plugin to sync content between WordPress installations using the WordPress REST API.

## Features

- **Source Mode**: Allows you to sync content from your WordPress site to another WordPress site.
  - Selective syncing
  - Media syncing
- **Destination Mode**: Allows you to receive content from another WordPress site and import it into your site.

## Installation

1. Download the [latest release](https://github.com/ksolomon75/WP-Content-Sync/releases/latest/download/WP-Content-Sync.zip) of the plugin.
2. Upload and activate the plugin on both sites.

## Usage

1. Go to the 'Settings' menu in the WordPress admin area.
2. Select the 'Content Sync' option.
3. Choose the 'Mode' for the plugin (source or destination).
4. Create an application password on the destination site.
5. Configure the destination URL, username, and application password on source site.

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
