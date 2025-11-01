# Technical Architecture: Unified Ticket Macro (UTM)

## 1. Objective

The Unified Ticket Macro (UTM) is a feature for the SupportCandy Plus plugin that provides a single, dynamic macro tag, `{{scp_unified_ticket}}`, for use in email notifications.

This macro intelligently renders a `<table>` of selected ticket fields, but *only* includes rows for fields that contain a value. The list of fields to be processed is configurable by an administrator on a dedicated settings page.

The primary goal is to provide a customizable, source-agnostic way to display ticket data in emails while adhering to a strict, high-performance, cached architecture.

## 2. Core Technical Challenge

The central and most difficult challenge in developing this feature was architectural, not functional. The macro's content needed to be available for the very first email sent upon ticket creation. However, the SupportCandy environment presented several critical constraints that made simple solutions fail catastrophically:

1.  **The Recursive Loop Problem:** The most intuitive approach—hooking into the `wpsc_create_new_ticket` action, building the HTML, and saving it to the ticket's metadata via `$ticket->save()`—resulted in a fatal error. The hook fires from *within* the initial save process. Calling `$ticket->save()` again from inside this hook created an infinite recursive loop that crashed the system.

2.  **The Race Condition Problem:** An asynchronous approach using `wp_schedule_single_event()` to run a background job (WP-Cron) was implemented to solve the recursion. This successfully decoupled the caching from the initial ticket creation. However, it introduced a race condition: the default "New Ticket Created" email was often sent *before* the scheduled cron job had a chance to run. This resulted in the macro being replaced with an empty string in the crucial first email, defeating the feature's purpose.

3.  **The Performance Mandate:** The feature was explicitly forbidden from using "on-the-fly" generation for the initial email, as this had been proven to cause site hangs and timeouts in the user's production environment.

The final architecture was therefore designed specifically to solve all three of these problems simultaneously.

## 3. Final Architecture (The "How" and "Why")

The solution is divided into two main components: a backend caching engine (`SCPUTM_Core`) and a frontend admin interface (`SCPUTM_Admin`).

### Part 1: The Caching Engine (`SCPUTM_Core`)

This class contains all the logic for building, caching, and replacing the macro. It solves the core technical challenge by implementing a **"Transient-First with Shutdown Hook"** pattern.

#### Key Functions & Execution Flow:

**A. For a NEW Ticket:**

The process is designed to be both instant and safe.

1.  **`scputm_prime_cache_on_creation( $ticket )`**
    *   **Hook:** `wpsc_create_new_ticket` (priority 5)
    *   **Action:** This function fires immediately when a new ticket is created. It calls the internal `_scputm_build_live_utm_html()` method to generate the macro's HTML.
    *   **Crucial Step 1: Set Transient:** Instead of saving to the ticket, it saves the generated HTML to a temporary, short-lived WordPress **transient**.
        ```php
        set_transient( 'scputm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
        ```
        This makes the HTML instantly available to any other process in the same request, solving the race condition.
    *   **Crucial Step 2: Register Shutdown:** It then registers a separate function to run at the very end of the PHP request.
        ```php
        add_action( 'shutdown', array( $this, 'scputm_deferred_save' ) );
        ```
        This solves the recursive loop problem by deferring the database write.

2.  **`scputm_replace_utm_macro( $data, $thread )`**
    *   **Hook:** `wpsc_create_ticket_email_data` (priority 10)
    *   **Action:** When the email engine runs (moments after the cache is primed), this function checks for the macro tag.
    *   **Logic:** It first checks if the transient exists (`get_transient(...)`). Because it does, it uses the HTML from the transient to replace the macro tag in the email body. The email is sent with the correct data.

3.  **`scputm_deferred_save()`**
    *   **Hook:** `shutdown`
    *   **Action:** After the page has been sent to the user and the connection is closed, this function finally runs.
    *   **Logic:** It retrieves the HTML from the transient, saves it to the ticket's permanent metadata (`$ticket->misc`), and then safely calls `$ticket->save()`. It then deletes the transient. This readies the cache for all future emails related to this ticket.

**B. For an UPDATED Ticket:**

The process is much simpler because we no longer have to worry about recursion.

1.  **`scputm_update_utm_cache( $ticket_or_thread_or_id )`**
    *   **Hooks:** `wpsc_after_reply_ticket`, `wpsc_after_change_ticket_status`, etc.
    *   **Action:** When a ticket is updated, this function fires. It rebuilds the HTML from scratch and saves it directly to the ticket's metadata via `$ticket->save()`.

2.  **`scputm_replace_utm_macro( $data, $thread )`**
    *   **Hooks:** `wpsc_agent_reply_email_data`, `wpsc_close_ticket_email_data`, etc.
    *   **Action:** When a subsequent email is sent, this function runs. It checks for the transient, which does not exist for an updated ticket. It then falls back to retrieving the HTML from the permanent cache in `$ticket->misc` and performs the replacement.

### Part 2: The Admin Interface (`SCPUTM_Admin`)

This class (`class-scputm-admin.php`) and its corresponding JavaScript (`scp-utm-admin.js`) create the user-facing configuration page.

*   **Menu Registration:** The `add_admin_menu()` method hooks into `admin_menu` to add the "Unified Ticket Macro" submenu page under the main "SupportCandy Plus" menu.
*   **Settings Registration:** The `register_settings()` method hooks into `admin_init` to register the settings section and the single settings field (`scputm_selected_fields`) with the WordPress Settings API. This ensures the data is saved correctly to the `scp_settings` option in the `wp_options` table.
*   **UI Rendering:** The `render_fields_selector()` method generates the HTML for the dual-column list box.
    *   "Available Fields" are populated by getting all fields from `supportcandy_plus()->get_supportcandy_columns()` and removing any that are already in the "Selected Fields" list.
    *   "Selected Fields" are populated from the `scputm_selected_fields` option. The order is explicitly preserved.
*   **JavaScript Interactivity:** The JavaScript file provides the logic for the `>` (Add), `<` (Remove), `>>` (Add All), and `<<` (Remove All) buttons, moving `<option>` elements between the two `<select>` boxes. On form submission, a crucial function (`selectAllInSelectedBox()`) programmatically selects all options in the "Selected Fields" box to ensure they are included in the form's POST data and saved by WordPress.

## 4. Architectural Graveyard (The "Don'ts")

This section documents the failed architectural patterns that must be avoided in the future for similar tasks.

*   **Failed Approach #1: The Recursive Loop**
    *   **What:** Calling `$ticket->save()` on a ticket object from within a function hooked to `wpsc_create_new_ticket`.
    *   **Why it Failed:** This hook is part of the call stack of the initial `$ticket->save()` operation. Calling it again creates an infinite `save -> hook -> save -> hook -> ...` loop, leading to a PHP fatal error and a 500 server response.
    *   **Lesson:** Never write to a ticket object in a way that triggers a save from within a hook that fires during that same save operation. Use a deferred pattern like the `shutdown` hook instead.

*   **Failed Approach #2: The WP-Cron Race Condition**
    *   **What:** Using `wp_schedule_single_event()` to schedule a background task to build the cache 15 seconds after ticket creation.
    *   **Why it Failed:** While this solved the recursion, it was not reliable. The default SupportCandy email notifications are sent almost instantly. In many cases, the email filter hook would run *before* the 15-second cron job, find no cache, and send an email with an empty macro.
    *   **Lesson:** Asynchronous background jobs are not suitable for populating data that is required by another process within the *same request*. For this, a synchronous, temporary cache like a transient is the correct pattern.

## 5. Key Development Lessons & Gotchas

*   **Accidental File Deletion:** The admin UI was completely deleted by a `restore_file` command that was part of a flawed recovery plan. This was a catastrophic failure of state management. **Lesson:** Always verify the exact state of the filesystem before and after any destructive operation. Do not assume.
*   **Polymorphic Arguments:** The hook that updates the cache, `scputm_update_utm_cache`, can receive a `WPSC_Ticket` object, a `WPSC_Thread` object, or a numeric ID depending on which action fires it. The function must be written defensively to handle all of these input types.
*   **Complex Object Inspection:** The `WPSC_Ticket` object contains circular references. Debugging it with `print_r()` or `var_dump()` will crash the process. **Lesson:** Use `$ticket->to_array()` or access individual properties for safe inspection.
*   **Handling `null` vs. Object Values:** When iterating through fields, the value of a field can be `null`, a simple string, a `DateTime` object, or a custom `WPSC_*` object (e.g., `WPSC_Priority`). The code must check the type of the value (`is_a`, `instanceof`) before attempting to access properties on it (e.g., `$field_value->name`).
*   **Plugin Loading Order:** A class that registers WordPress hooks (especially `admin_menu`) must be instantiated at the correct time. Simply including the file is not enough. The main plugin file must explicitly create an instance of the class (e.g., `SCPUTM_Admin::get_instance();`) to ensure its hooks are registered.
