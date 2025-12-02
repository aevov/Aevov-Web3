# Aevov Pattern Sync Protocol

The Aevov Pattern Sync Protocol (APS) is a WordPress plugin that facilitates the synchronization and analysis of content patterns across a network of sites. It is the core component of the Aevov Pattern System, responsible for managing pattern data, orchestrating analysis, and communicating with other plugins in the suite.

## Installation

1.  **Prerequisites**: This plugin is designed for a WordPress Multisite environment and requires the **BLOOM Pattern Recognition System** plugin to be installed and activated first.

2.  **Installation Steps**:
    *   Place the `AevovPatternSyncProtocol` folder in your `wp-content/plugins` directory.
    *   Navigate to the Network Admin > Plugins page in your WordPress dashboard.
    *   Network Activate the `Aevov Pattern Sync Protocol` plugin. It must be activated **after** the `BLOOM Pattern Recognition System`.

## Configuration

After activation, the core settings for this plugin are managed under the **APS Tools** > **Settings** page in the WordPress admin sidebar. Here you can configure:

*   **API Settings**: Configure the connection to the BLOOM engine, including the API endpoint and key.
*   **Sync Settings**: Define the sync interval, confidence thresholds, and other parameters for pattern analysis.
*   **Advanced Settings**: Enable or disable debug mode and configure data retention policies.

## Features

*   **Pattern Synchronization**: Automatically syncs content patterns between sites in your network.
*   **Database Management**: Creates and manages custom database tables for storing patterns, metrics, and logs.
*   **Cron-based Processing**: Uses WordPress cron to schedule regular tasks for data cleanup and optimization.
*   **BLOOM Integration**: Deeply integrates with the BLOOM Pattern Recognition System for advanced pattern analysis.
*   **Extensible**: Provides hooks and filters for developers to extend its functionality.

## Basic Usage

Once installed and configured, the Aevov Pattern Sync Protocol runs automatically in the background. You can monitor its activity and view pattern data through the **APS Tools** dashboard.

*   **Dashboard**: Provides an overview of system status, recent patterns, and network activity.
*   **Patterns**: View a list of all synchronized patterns and their analysis results.
*   **System Status**: Monitor the health and performance of the synchronization protocol.
