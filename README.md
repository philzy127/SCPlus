# SupportCandy Plus

**Contributors:** Jules
**Version:** 1.0.0
**Requires at least:** 5.0
**Tested up to:** 6.0
**Requires PHP:** 7.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A collection of powerful enhancements for the SupportCandy WordPress plugin.

## Description

SupportCandy Plus is a comprehensive plugin that consolidates several individual scripts and features into a single, manageable package. It provides an admin settings page to control various enhancements for the SupportCandy ticket list and submission form, allowing for a more tailored and efficient support experience.

### Core Features

*   **Ticket Hover Card:** Hover over a ticket in the list to see a quick preview of its details without leaving the page. (Feature can be toggled)
*   **Dynamic Column Hiding:** Automatically hides the "Priority" column if all tickets are of low priority, and hides any other column that is completely empty, cleaning up the ticket view. (Feature can be toggled)
*   **Conditional Column Display:** Configure specific columns to show or hide based on the selected ticket filter. This is perfect for creating custom views for different ticket categories (e.g., "Network Access Requests").
*   **Hide Ticket Types for Non-Agents:** Restrict non-agent users from selecting certain ticket types on the submission form. (Feature can be toggled)

## Installation

1.  Upload the `supportcandy-plus` directory to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to **Settings > SupportCandy Plus** to configure the plugin.

## Configuration

All features can be configured from the **Settings > SupportCandy Plus** page in your WordPress admin area.

### General Settings

*   **Enable Ticket Hover Card:** Check this to enable the hover-card feature.
*   **Enable Dynamic Column Hiding:** Check this to enable the automatic hiding of empty columns and the low-priority column.
*   **Enable Ticket Type Hiding for Non-Agents:** Check this to prevent non-agents from seeing certain ticket types.

### Conditional Column Hiding

*   **Filter Name to Trigger Hiding:** Enter the exact name of the filter that should trigger the special view (e.g., `Network Access Requests`).
*   **Columns to Hide in Special View:** Provide a comma-separated list of column names that should be hidden when the special filter is active.
*   **Columns to Show Only in Special View:** Provide a comma-separated list of column names that should only be visible when the special filter is active.