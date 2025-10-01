# SupportCandy Plus

SupportCandy Plus is a powerful enhancement plugin for the SupportCandy helpdesk system in WordPress. It adds a suite of advanced features designed to streamline ticket management, improve agent workflow, and provide greater control over the user interface.

## Features

This plugin includes several modules that can be enabled or disabled based on your needs:

*   **Ticket Hover Card:** Quickly view ticket details by hovering over them in the ticket list.
*   **General Cleanup:** Automatically hide empty columns or the priority column to reduce clutter.
*   **Ticket Type Hiding:** Restrict which ticket types are visible to non-agent users.
*   **After Hours Notice:** Display a customizable notice on the ticket form outside of business hours.
*   **Conditional Column Hiding:** Create powerful, context-aware rules to control column visibility in the ticket list.

---

## Conditional Column Hiding

This is one of the most powerful features of SupportCandy Plus. It allows you to create a set of rules to control the visibility of columns in the ticket list based on the currently selected ticket view (filter). This creates a dynamic and context-aware ticket list, showing agents only the information they need for a specific task.

### How the Rule Builder Works

You can create multiple rules to define the visibility of your columns. The rules are processed in a logical order to determine the final state of each column in any given view.

Each rule consists of four parts:

1.  **Action (SHOW / SHOW ONLY / HIDE):** This is the core of the rule.
    *   `HIDE`: Explicitly hides a column in a specific view. This is the most powerful action and acts as a final veto, overriding any other rules for that column in that view.
    *   `SHOW ONLY`: This is the best way to handle columns that are only relevant in one context. It makes the column visible in the specified view but implicitly hides it in **all other views** by default.
    *   `SHOW`: Explicitly shows a column. This is primarily used to create exceptions and override the implicit hiding caused by a `SHOW ONLY` rule in another view.

2.  **Column:** The specific column that the rule will affect.

3.  **Condition:**
    *   `WHEN IN VIEW`: The rule applies only when the selected view is active.
    *   `WHEN NOT IN VIEW`: The rule applies when **any other** view is active.

4.  **View:** The SupportCandy ticket view (filter) that triggers the rule.

### Example Scenario

Imagine you have a column named "Billing Code" that should only be seen by the Accounting department. You also have a special "Manager" view where managers need to see it as well.

*   **Rule 1:** `SHOW ONLY` | `Billing Code` | `WHEN IN VIEW` | `Accounting View`
    *   *This single rule accomplishes two things: it makes "Billing Code" visible in the "Accounting View" and hides it everywhere else by default. You no longer need extra "hide" rules for every other view.*

*   **Rule 2:** `SHOW` | `Billing Code` | `WHEN IN VIEW` | `Manager View`
    *   *This rule creates an exception. It overrides the default hiding from the "Show Only" rule and makes the "Billing Code" column visible in the "Manager View" as well.*