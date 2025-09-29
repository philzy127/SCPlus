# SupportCandy Plus

**Contributors:** Jules
**Version:** 2.1.0
**Requires at least:** 5.0
**Tested up to:** 6.0
**Requires PHP:** 7.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A collection of powerful, highly configurable enhancements for the SupportCandy WordPress plugin.

## Description

SupportCandy Plus is a comprehensive plugin that consolidates several individual scripts and features into a single, manageable package. It provides a detailed admin settings page to control various enhancements for the SupportCandy ticket list and submission form, allowing for a more tailored and efficient support experience.

Version 2 introduces full configurability, allowing administrators to fine-tune every feature to match their specific workflow and SupportCandy setup.

## Installation

1.  Upload the `supportcandy-plus` directory to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to the **SupportCandy Plus** top-level menu item in your WordPress admin area to configure the plugin.

## Configuration

All features are controlled from the **SupportCandy Plus** page in your WordPress admin area. Each feature can be enabled or disabled independently.

---

### Ticket Hover Card

-   **Enable Feature:** Check this to enable a floating card with ticket details when you hover over a ticket in the list.
-   **Hover Delay (ms):** Set the time in milliseconds to wait before the hover card appears (e.g., `1000` for 1 second).

---

### Hide Ticket Types from Non-Agents

This allows you to restrict which ticket types are available to non-agent users on the ticket submission form.

-   **Enable Feature:** Check this to activate the feature.
-   **Custom Field Name:** Enter the name of the custom field used for ticket types (e.g., `Ticket Category`). The plugin will look up the field's internal ID automatically, making it robust against changes.
-   **Ticket Types to Hide:** List the ticket types you want to hide from non-agents, with each type on a new line.

---

### Conditional Column Hiding Rules

This powerful feature allows you to create a set of rules to control the visibility of columns in the ticket list. You can define rules to show or hide specific columns based on the currently selected ticket view (filter).

-   **Enable Feature:** Check this to activate the rule-based system.

#### How the Rule Builder Works

You can create multiple rules, which are processed from top to bottom. The last rule that applies to a specific column will determine its final visibility.

Each rule consists of four parts:

1.  **Action (SHOW/HIDE):**
    -   `SHOW`: Make the selected columns visible.
    -   `HIDE`: Make the selected columns invisible.

2.  **Columns:**
    -   Select one or more columns that this rule will affect. The list is populated automatically from your SupportCandy setup.

3.  **Condition (WHEN IN VIEW / WHEN NOT IN VIEW):**
    -   `WHEN IN VIEW`: The rule will only apply when the selected view is active.
    -   `WHEN NOT IN VIEW`: The rule will apply when **any other** view is active.

4.  **View:**
    -   Select the SupportCandy ticket view (filter) that this rule is based on. This list is also populated automatically.

**Example Scenario:**

Imagine you want the "Onboarding Date" column to *only* be visible when you are looking at the "New Hire Tickets" view.

-   **Rule 1:** `HIDE` | `Onboarding Date` | `WHEN NOT IN VIEW` | `New Hire Tickets`
    -   This rule hides the "Onboarding Date" column for all views *except* "New Hire Tickets".
-   **Rule 2:** `SHOW` | `Onboarding Date` | `WHEN IN VIEW` | `New Hire Tickets`
    -   This rule explicitly shows the "Onboarding Date" column when the "New Hire Tickets" view is selected.