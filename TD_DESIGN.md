### **Technical Document 1: Unified Ticket Macro (UTM) - Design and Function**

**1. Intent & Objective**
The primary objective of the Unified Ticket Macro (UTM) module is to provide WordPress administrators with a powerful, dynamic tool to consolidate email notifications within the SupportCandy ecosystem. The core intent is to replace the need for multiple, slightly different email templates with a single, intelligent macro: `{{scp_unified_ticket}}`. This macro dynamically generates a summary of a ticket's key details, but intelligently includes only those fields that contain a value, preventing empty or irrelevant lines in the notification.

**2. Design Philosophy**
The module is designed with the following principles in mind:
*   **Modularity:** The UTM is built as a self-contained feature within the `supportcandy-plus` plugin. It can be enabled or disabled without affecting any other functionality.
*   **User-Friendliness:** The configuration is handled through a dedicated, intuitive admin page with a standard dual-column selector, which is a familiar UI pattern for WordPress users.
*   **API-First (WordPress & SupportCandy):** The design strictly adheres to using existing WordPress and SupportCandy APIs. It uses `add_submenu_page` for the UI, `register_setting` for data persistence, and SupportCandy's `wpsc_macros` and `wpsc_create_ticket_email_data` filters for its core logic. Direct database queries are limited and only used when a specific API is not available (e.g., for fetching custom field metadata).
*   **Performance:** The module includes a caching layer (`$custom_field_data_cache`) to ensure that complex data lookups (like fetching all custom field options) are performed only once per page load, preventing N+1 query problems.

**3. Functional Breakdown**

**a. Admin Configuration (`class-scp-admin-settings.php`)**
*   **UI Registration:** A new submenu page, "Unified Ticket Macro," is registered under the "SupportCandy Plus" top-level menu.
*   **Settings Fields:** Two settings are registered:
    *   `enable_utm`: A simple checkbox (boolean `1` or `0`) that controls whether the feature's hooks are active.
    *   `utm_columns`: An array that stores the slugs of the ticket fields selected by the administrator.
*   **UI Rendering (`render_utm_columns_field`):** This function generates the dual-column selector.
    1.  It calls `supportcandy_plus()->get_scp_utm_columns()` to get a master list of all available standard and custom fields.
    2.  It compares this list against the saved `utm_columns` option to separate the fields into "Available" and "Selected" lists.
    3.  It renders the two `<select multiple>` boxes and the control buttons.
*   **Data Persistence:** The settings are saved in the `wp_options` table under the `scp_settings` key. The `sanitize_settings` function ensures the `utm_columns` array is properly sanitized before saving.
*   **Interactivity (`supportcandy-plus-admin.js`):** jQuery is used to handle the click events on the add/remove buttons, moving `<option>` elements between the two select boxes. A `submit` handler ensures all options in the "Selected" box are marked as selected before form submission so they are correctly saved.

**b. Macro Registration & Execution (`unified-ticket-macro.php`)**
*   **Loading:** The main plugin file (`supportcandy-plus.php`) checks the `enable_utm` setting. If it's enabled, it `require_once`'s the `unified-ticket-macro.php` file.
*   **Macro Registration:** The `register_macro` function hooks into `wpsc_macros`. This makes `{{scp_unified_ticket}}` appear in the list of available macros within the SupportCandy notification builder UI.
*   **Macro Replacement:** The `replace_macro` function hooks into an email data filter (currently `wpsc_create_ticket_email_data`). This is the core of the module's logic:
    1.  **Context Check:** It first checks if the macro string `{{scp_unified_ticket}}` exists in the email body. If not, it returns immediately.
    2.  **Ticket Object Retrieval:** It retrieves the `WPSC_Ticket` object from the `$thread` object passed by the filter.
    3.  **Data Fetching:** It fetches the admin-configured `utm_columns` and calls the two helper functions: `get_scp_utm_columns()` (for labels) and `get_all_custom_field_data()` (for mapping custom field values).
    4.  **Iteration and Value Checking:** It iterates through the list of selected fields. For each field:
        *   It determines if it's a **standard field** or a **custom field**.
        *   It retrieves the raw value from the `$ticket` object (e.g., `$ticket->subject` or `$ticket->custom_fields[22]`).
        *   **Crucially, it checks if the retrieved value is null or an empty string.**
    5.  **Value Formatting:** If a value exists, it's passed to `format_field_value`. This helper function handles data type complexities, such as converting `DateTime` objects into a readable format or extracting the `->name` property from objects (like status or priority). For custom fields with predefined options (e.g., a dropdown), it maps the saved ID (e.g., `2`) to its text label (e.g., "Technical Support").
    6.  **HTML Table Generation:** For each valid field, it constructs an HTML `<tr>` containing the field's label and its formatted value.
    7.  **Final Replacement:** The complete HTML `<table>` string is used to replace the `{{scp_unified_ticket}}` placeholder in the email body.