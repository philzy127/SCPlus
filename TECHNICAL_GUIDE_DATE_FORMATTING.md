# Technical Guide: Implementing Custom Date Formatting for SupportCandy

This document provides a comprehensive, step-by-step guide for developers on how to build a feature that allows administrators to customize the date and time formats for columns in the SupportCandy ticket list. This guide is the result of a deep and iterative development process and includes not only the final, correct implementation but also a summary of incorrect approaches to avoid.

## Table of Contents

1.  **Feature Overview**
2.  **Part 1: The Settings UI**
    *   Creating the Settings Page
    *   Building the Dynamic Rule-Builder
    *   The PHP Template for a Rule
    *   The JavaScript for Dynamic Behavior
3.  **Part 2: The Data Layer (Saving the Rules)**
    *   Registering the Setting
    *   Sanitizing and Saving the Array of Rules (The Critical Fix)
4.  **Part 3: The Core Formatting Logic (The Official PHP Solution)**
    *   Hooking into the Correct Filters
    *   The Four Crucial Steps in the Callback Function
    *   Full Code Example
5.  **Part 4: Lessons Learned (Incorrect Approaches to Avoid)**
    *   Mistake #1: The Flawed JavaScript DOM Manipulation Approach
    *   Mistake #2: The `last_reply_on` Property Mismatch
    *   Mistake #3: Incorrectly Saving Settings

---

## Feature Overview

The goal is to allow a WordPress administrator to create multiple rules to override the default "time ago" display for date-based columns in the SupportCandy ticket list. For example, an admin could set the "Date Created" column to display as "Monday, October 27, 2025" and the "Last Reply" column to display as "10:30 AM".

The final, correct architecture consists of three parts:
1.  **A settings page** where users can define these rules.
2.  **A data sanitization function** that correctly saves these rules to the WordPress options table.
3.  **A PHP filter callback** that uses the officially supported method to apply these rules.

---

## Part 1: The Settings UI

The user interface for this feature is a dynamic "rule builder" that allows the admin to add, configure, and remove multiple formatting rules on a single settings page.

### Creating the Settings Page

First, a new admin submenu page is created using the `add_submenu_page` function. The function that renders the page content calls `settings_fields` and `do_settings_sections` to build the form.

*File: `supportcandy-plus/includes/class-scp-admin-settings.php`*
```php
// In the register_settings() method:
add_settings_section(
    'scp_date_time_formatting_section',
    __( 'Date & Time Formatting Rules', 'supportcandy-plus' ),
    // ... callback for description ...
    'scp-date-time-formatting'
);

add_settings_field(
    'scp_date_time_formatting_rules',
    __( 'Rules', 'supportcandy-plus' ),
    array( $this, 'render_date_time_formatting_rules_builder' ), // The function that builds the UI
    'scp-date-time-formatting',
    'scp_date_time_formatting_section'
);
```

### Building the Dynamic Rule-Builder

The `render_date_time_formatting_rules_builder` function is responsible for rendering the entire UI. It retrieves the saved rules and iterates over them, rendering a template for each. It also includes a template within a `<script>` tag that the frontend JavaScript will use to add new rules.

### The PHP Template for a Rule

This is the core HTML structure for a single rule, written in a PHP function. It includes dropdowns for the column and format type, a text input for the custom format, and checkboxes for other options. Note the use of `__INDEX__` as a placeholder; this is what the JavaScript will replace with a unique ID when adding a new rule.

*File: `supportcandy-plus/includes/class-scp-admin-settings.php`*
```php
private function render_date_format_rule_template( $index, $rule, $columns ) {
    $column          = $rule['column'] ?? '';
    $format_type     = $rule['format_type'] ?? 'default';
    // ... other variables ...
    ?>
    <div class="scp-date-rule-wrapper">
        <div>
            <div class="scp-date-rule-row scp-date-rule-row-top">
                <!-- Dropdowns and inputs with names like "scp_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][column]" -->
            </div>
            <div class="scp-date-rule-row scp-date-rule-row-bottom" style="display: none;">
                <!-- Checkboxes for 'Use Long Date' and 'Show Day of the Week' -->
            </div>
        </div>
        <button type="button" class="button scp-remove-date-rule">&times;</button>
    </div>
    <?php
}
```

### The JavaScript for Dynamic Behavior

A dedicated JavaScript file handles the client-side logic for the rule builder.

*File: `supportcandy-plus/assets/admin/js/scp-date-time-formatting.js`*
Key functionalities include:
1.  **Adding a New Rule:** When the "Add New Rule" button is clicked, the script clones the HTML from the template, replaces `__INDEX__` with a unique timestamp, and appends it to the container.
2.  **Removing a Rule:** A click handler on the "remove" button removes the closest `.scp-date-rule-wrapper` element.
3.  **Dynamic Visibility:** A `change` handler on the "format type" dropdown shows or hides the "Custom Format" text input and the row containing the checkboxes, based on the user's selection.
4.  **CRITICAL FIX:** When the checkboxes are hidden, the script also programmatically unchecks them (`.prop('checked', false)`). This is essential to prevent incorrect boolean values from being saved for formats where they don't apply.

---

## Part 2: The Data Layer (Saving the Rules)

This was a major source of bugs. The solution is to handle the settings sanitization with care, ensuring that settings from other tabs do not overwrite the date formatting rules.

### Registering the Setting

This is standard WordPress practice, done in the `register_settings` function. The key is the third parameter, which is the callback function for sanitization.
```php
register_setting( 'scp_settings', 'scp_settings', array( $this, 'sanitize_settings' ) );
```

### Sanitizing and Saving the Array of Rules (The Critical Fix)

The `sanitize_settings` function receives input from whatever settings tab was just saved. If you save settings on the "General" tab, the `date_format_rules` key will not be present in the `$input` array. The original flawed logic would then proceed to wipe out the saved rules.

The corrected logic is as follows:

*File: `supportcandy-plus/includes/class-scp-admin-settings.php`*
```php
public function sanitize_settings( $input ) {
    $existing_settings = get_option( 'scp_settings', [] );
    $merged_settings = array_merge( $existing_settings, $input );
    $sanitized_output = [];

    foreach ( $merged_settings as $key => $value ) {
        switch ( $key ) {
            // ... cases for other settings ...

            case 'date_format_rules':
                // CRITICAL: Only process this if it was actually in the form that was just submitted.
                if ( isset( $input['date_format_rules'] ) ) {
                    if ( is_array( $value ) ) {
                        $sanitized_rules = [];
                        foreach ( $value as $rule ) {
                            // Sanitize each field in the rule and add to the array
                        }
                        $sanitized_output[ $key ] = $sanitized_rules;
                    } else {
                        $sanitized_output[ $key ] = []; // If not an array, wipe it.
                    }
                } elseif ( isset( $existing_settings['date_format_rules'] ) ) {
                    // If the rules are NOT in the current submission, keep the old ones from the database.
                    $sanitized_output[ $key ] = $existing_settings['date_format_rules'];
                }
                break;
        }
    }
    return $sanitized_output;
}
```

---

## Part 3: The Core Formatting Logic (The Official PHP Solution)

This is the definitive, officially supported method for changing the visible date format. It is a pure PHP solution and does not require any frontend JavaScript to manipulate the DOM.

### Hooking into the Correct Filters

In the main plugin class, the `apply_date_time_formats` function retrieves the saved rules and then adds the *same* callback function to the filter for *every* potential standard date field.

*File: `supportcandy-plus/supportcandy-plus.php`*
```php
public function apply_date_time_formats() {
    // ... retrieve and format rules into $this->formatted_rules ...

    // Add filters for all potential standard fields.
    $standard_fields = [ 'date_created', 'last_reply_on', 'date_closed', 'date_updated' ];
    foreach ( $standard_fields as $field ) {
        add_filter( 'wpsc_ticket_field_val_' . $field, array( $this, 'format_date_time_callback' ), 10, 4 );
    }
    // Also add for custom datetime fields
    add_filter( 'wpsc_ticket_field_val_datetime', array( $this, 'format_date_time_callback' ), 10, 4 );
}
```

### The Four Crucial Steps in the Callback Function

The `format_date_time_callback` function is where the magic happens. It follows a precise sequence:

1.  **Check Context:** (Optional) Exit early if the code is not running in the desired context (e.g., the admin ticket list or a frontend AJAX request).
2.  **Identify Column:** Use `current_filter()` to get the name of the hook that is running (e.g., `wpsc_ticket_field_val_date_created`). Parse the slug (`date_created`) from this string. This is the only 100% reliable way to know which column is being processed.
3.  **Override Display Mode:** This is the most critical step. Set `$cf->date_display_as = 'date';`. The `$cf` object is passed by reference, and this change tells SupportCandy's rendering engine to use your function's return value as the visible text, not the hover-over `title` text.
4.  **Safely Format and Return:** Look up the rule for the slug identified in Step 2. Get the date property from the `$ticket` object (e.g., `$ticket->date_created`). **CRITICAL:** Always validate that the property is a valid `DateTime` object with `instanceof DateTime` before attempting to format it, as it can be `NULL`. Return the final formatted string.

### Full Code Example

*File: `supportcandy-plus/supportcandy-plus.php`*
```php
function format_date_time_callback( $value, $cf, $ticket, $module ) {
    // 1. CONTEXT CHECK
    $is_admin_list    = is_admin() && get_current_screen() && get_current_screen()->id === 'toplevel_page_wpsc-tickets';
    $is_frontend_list = isset( $_POST['is_frontend'] ) && '1' === $_POST['is_frontend'];
    if ( ! $is_admin_list && ! $is_frontend_list ) {
        return $value;
    }

    // 2. IDENTIFY COLUMN
    $current_filter = current_filter();
    $field_slug = /* ... logic to parse slug from $current_filter ... */;
    if ( ! $field_slug || ! isset( $this->formatted_rules[ $field_slug ] ) ) {
        return $value;
    }
    $rule = $this->formatted_rules[ $field_slug ];

    // 3. OVERRIDE DISPLAY MODE
    if ( is_object( $cf ) ) {
        $cf->date_display_as = 'date';
    }

    // 4. SAFELY FORMAT AND RETURN
    $date_object = $ticket->{$field_slug}; // e.g., $ticket->date_created
    if ( ! ( $date_object instanceof DateTime ) ) {
        return $value;
    }

    // ... logic to build the format string based on the $rule ...
    $new_value = wp_date( $format_string, $date_object->getTimestamp() );

    return $new_value;
}
```

---

## Part 4: Lessons Learned (Incorrect Approaches to Avoid)

### Mistake #1: The Flawed JavaScript DOM Manipulation Approach

The initial belief was that the PHP filter was failing entirely. This led to an incorrect solution where the PHP filter would place the formatted date into the `title` attribute, and a separate JavaScript function would then read this `title` and use it to replace the visible text.

**Why it was wrong:** This was fighting against the framework and was unnecessarily complex. It failed to account for the officially supported `$cf->date_display_as` property, which solves the problem cleanly in PHP without any need for client-side DOM manipulation for this feature.

### Mistake #2: The `last_reply_on` Property Mismatch

During development, an incorrect assumption was made that the property for the "Last Reply" column was `$ticket->last_reply` when the slug was `last_reply_on`. This was based on a misunderstanding of the data objects.

**The Correction:** The final, definitive documentation from SupportCandy confirmed that the property names on the `$ticket` object **do** match the field slugs. The correct property is `$ticket->last_reply_on`. No special case is needed.

### Mistake #3: Incorrectly Saving Settings

As detailed in Part 2, the `sanitize_settings` function initially failed to check if the `date_format_rules` key was present in the submitted data. This caused the rules to be wiped out whenever settings on any other tab were saved, which led to the misleading symptom of "only one rule is working." This highlights the importance of defensive coding in WordPress settings sanitization callbacks.
