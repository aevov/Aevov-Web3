# Aevov Cubbit Downloader

Integrates the Cubbit Authenticated Downloader with the Aevov Pattern Sync Protocol.

## Description

This plugin allows users to download Aevov patterns securely from Cubbit S3 storage using the Cubbit Authenticated Downloader plugin. It adds a "Download" button to the Aevov pattern list, which initiates the download process.

## Features

*   Download single or multiple Aevov patterns at once.
*   Securely download private patterns from Cubbit S3 storage.
*   Display a progress bar during the download process.
*   Cancel a download in progress.

## Requirements

*   Aevov Pattern Sync Protocol
*   Cubbit Authenticated Downloader
*   Cubbit Directory Manager

## Installation

1.  Install and activate the Aevov Pattern Sync Protocol, Cubbit Authenticated Downloader, and Cubbit Directory Manager plugins.
2.  Install and activate the Aevov Cubbit Downloader plugin.

## Usage

1.  Go to the Aevov Pattern Sync Protocol dashboard.
2.  Select the patterns you want to download by checking the checkboxes next to them.
3.  Click the "Download Selected" button at the top of the pattern list.
4.  The download process will start, and you will see a progress bar.
5.  Once the download is complete, you will be provided with a secure download link.
6.  You can cancel the download at any time by clicking the "Cancel" button.

## Testing

**Important:** This plugin has not been tested in a live environment. It is recommended that you test the plugin thoroughly before using it in a production environment.

To test the plugin, you will need to have the Aevov Pattern Sync Protocol, Cubbit Authenticated Downloader, and Cubbit Directory Manager plugins installed and configured correctly. You will also need to have some Aevov patterns stored in your Cubbit S3 storage.

Once you have everything set up, you can test the plugin by following these steps:

1.  Go to the Aevov Pattern Sync Protocol dashboard.
2.  Select one or more patterns to download.
3.  Click the "Download Selected" button.
4.  Verify that the download process starts and that the progress bar is displayed.
5.  Try canceling a download and verify that it is canceled correctly.
6.  Verify that the downloaded file is correct.
