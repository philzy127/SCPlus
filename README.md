# SupportCandy Plus

**Contributors:** Jules
**Version:** 2.0.0
**Requires at least:** 5.0
**Tested up to:** 6.0
**Requires PHP:** 7.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A collection of powerful, highly configurable enhancements for the SupportCandy WordPress plugin.

## Description

SupportCandy Plus is a comprehensive plugin that consolidates several individual scripts and features into a single, manageable package. It provides a detailed admin settings page to control various enhancements for the SupportCandy ticket list and submission form, allowing for a more tailored and efficient support experience.

Version 2.0 introduces full configurability, allowing administrators to fine-tune every feature to match their specific workflow and SupportCandy setup.

## Installation

1.  Upload the `supportcandy-plus` directory to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to **Settings > SupportCandy Plus** to configure the plugin.

## Configuration

All features are controlled from the **Settings > SupportCandy Plus** page in your WordPress admin area. Each feature can be enabled or disabled independently.

---

### Ticket Hover Card

-   **Enable Feature:** Check this to enable a floating card with ticket details when you hover over a ticket in the list.
-   **Hover Delay (ms):** Set the time in milliseconds to wait before the hover card appears (e.g., `1000` for 1 second).

---

### Dynamic Column Hider

This feature cleans up the ticket list by hiding columns that are empty or meet specific criteria.

-   **Enable Feature:** Check this to enable the automatic hiding of columns.
-   **Priority Column Name:** Enter the exact, case-sensitive name of your "Priority" column. The default is `Priority`.
-   **Low Priority Text:** Enter the exact text that identifies a low-priority ticket (e.g., `Low`). The priority column will be hidden if all tickets in the current view have this value.

---

### Hide Ticket Types from Non-Agents

This allows you to restrict which ticket types are available to non-agent users on the ticket submission form.

-   **Enable Feature:** Check this to activate the feature.
-   **Custom Field Name:** Enter the name of the custom field used for ticket types (e.g., `Ticket Category`). The plugin will look up the field's internal ID automatically, making it robust against changes.
-   **Ticket Types to Hide:** List the ticket types you want to hide from non-agents, with each type on a new line.

---

### Conditional Column Hiding by Filter

Create custom views by showing or hiding specific columns based on the active ticket filter. This is ideal for workflows where certain information is only relevant to a specific category of tickets.

-   **Enable Feature:** Check this to enable conditional hiding rules.
-   **Filter Name for Special View:** Enter the exact name of the filter that will trigger this rule (e.g., `Network Access Requests`).
-   **Columns to HIDE in Special View:** List the columns that should be **hidden** when the special filter is active. Place each column name on a new line.
-   **Columns to SHOW ONLY in Special View:** List columns that are normally hidden but should **appear** only when the special filter is active. Place each column name on a new line.