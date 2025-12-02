# BLOOM Pattern Recognition System

The BLOOM Pattern Recognition System is a WordPress plugin that provides the foundational capabilities for content analysis and pattern recognition within the Aevov Pattern System. It is responsible for processing content into tensors and chunks, managing the core data models, and providing an API for other plugins to use.

## Installation

1.  **Prerequisites**: This plugin is designed for a WordPress Multisite environment.

2.  **Installation Steps**:
    *   Place the `bloom-pattern-recognition` folder in your `wp-content/plugins` directory.
    *   Navigate to the Network Admin > Plugins page in your WordPress dashboard.
    *   Network Activate the `BLOOM Pattern Recognition System` plugin. It must be activated **first**, before any other plugins in the Aevov Pattern System suite.

## Configuration

After activation, the settings for this plugin can be found in the Network Admin dashboard under the **BLOOM Patterns** menu.

Here you can configure:

*   **Network Settings**: Define the chunk size and other network-related parameters.
*   **Processing Settings**: Configure the batch size and sync interval for pattern processing.
*   **API Settings**: Manage the API key for secure communication with other plugins.
*   **Monitoring & Data Retention**: Set up system monitoring thresholds and define how long data should be retained.
*   **Debug Mode**: Enable verbose logging for troubleshooting.

## Features

*   **Content Processing**: Converts content into a structured format (tensors and chunks) suitable for analysis.
*   **Custom Database Tables**: Manages custom tables for storing tensors, chunks, and patterns.
*   **Admin Interface**: Provides a dedicated admin page for managing settings and monitoring the system.
*   **Error Handling**: Includes a centralized error handler for robust logging and debugging.

## Basic Usage

The BLOOM Pattern Recognition System primarily works as a backend service for other plugins in the Aevov suite. Its administrative interface is the main point of interaction for configuration and monitoring.

Once installed, it will automatically begin processing content as configured. You can monitor its activity and manage its settings from the **BLOOM Patterns** page in the Network Admin.
