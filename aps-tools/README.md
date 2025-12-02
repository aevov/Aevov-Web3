APS Tools is a WordPress plugin that provides a user interface and a set of tools for managing and interacting with the Aevov Pattern System. It serves as the main dashboard for monitoring network activity, viewing patterns, and configuring the system.

## Installation

1.  **Prerequisites**: This plugin is designed for a WordPress Multisite environment and requires the **BLOOM Pattern Recognition System** and the **Aevov Pattern Sync Protocol** plugins to be installed and activated first.

2.  **Installation Steps**:
    *   Place the `aps-tools` folder in your `wp-content/plugins` directory.
    *   Navigate to the Network Admin > Plugins page in your WordPress dashboard.
    *   Network Activate the `APS Tools` plugin. It must be activated **last**, after the other plugins in the Aevov Pattern System suite.

## Configuration

After activation, a new menu item called **APS Tools** will appear in your WordPress admin sidebar. The settings for this plugin, as well as the core settings for the Aevov Pattern Sync Protocol, can be found under **APS Tools > Settings**.

Here you can configure:

*   **General Settings**: Enable JSON validation and set the sync interval.
*   **Debug Mode**: Enable or disable debug logging for troubleshooting.

## Features

*   **Unified Dashboard**: Provides a central dashboard for monitoring the entire Aevov Pattern System.
*   **Pattern Viewer**: Allows you to view a list of all synchronized patterns and their details.
*   **System Status**: Displays information about the health and performance of the system.
*   **Media Monitor**: Automatically processes uploaded JSON files and integrates them into the pattern system.
*   **Integration Testing**: Includes a comprehensive integration test suite that can be run via WP-CLI.

## Basic Usage

APS Tools is the primary interface for interacting with the Aevov Pattern System.

*   Navigate to the **APS Tools** menu in your WordPress admin to access the dashboard and other features.
*   Use the **Dashboard** to get a high-level overview of the system.
*   Use the **Patterns** page to view and manage individual patterns.
*   Use the **Settings** page to configure the behavior of the APS Tools and the Aevov Pattern Sync Protocol.

