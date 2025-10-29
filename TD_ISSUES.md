### **Technical Document 2: Development Issues & Current Blockers**

**1. Critical Blocker: Process Hang on Ticket Submission (Resolved)**
*   **Symptom:** Submitting a new ticket caused the PHP process to hang, leading to a site-wide failure and timeout.
*   **Root Cause:** The initial diagnostic logging attempted to log the entire `WPSC_Ticket` object using `print_r($ticket, true)`. The `WPSC_Ticket` object contains circular references (e.g., a ticket object can contain a thread object, which in turn contains a reference back to the ticket object). This sent `print_r` into an infinite recursive loop, which rapidly consumed all available server memory, crashing the process.
*   **Resolution:** A "safe debug snapshot" protocol was implemented. Instead of logging the raw object, a simple, flat array is now created. This array contains only safe, primitive data types (strings, numbers, simple arrays) and the output of the object's own `$ticket->to_array()` method, which is designed to be a safe, non-recursive representation. This resolved the hang completely.

**2. Critical Blocker: Fatal SQL Errors (Resolved)**
*   **Symptom:** After resolving the first hang, a second wave of site hangs occurred, which were later identified as fatal SQL errors.
*   **Root Cause:** The `get_all_custom_field_data` function, which is responsible for mapping custom field option IDs to their labels, was built on incorrect assumptions about the SupportCandy database schema.
    *   **Attempt 1:** The query attempted to filter on a non-existent column (`cf.is_active`).
    *   **Attempt 2:** The `JOIN` condition between the custom fields table and the options table used an incorrect foreign key (`opt.field_id` instead of `opt.custom_field`).
*   **Resolution:** You provided precise technical documentation detailing the correct table and column names (`psmsc_custom_fields`, `psmsc_custom_field_options`, and the `custom_field` foreign key). The SQL query was corrected based on this documentation, which resolved the fatal errors.

**3. Architectural Issue: Incomplete "Unified" Implementation (Outstanding)**
*   **Symptom:** The macro currently only functions in the "New Ticket Created" email notification. It will not be replaced in any other notification context (e.g., agent reply, customer reply, ticket closed).
*   **Root Cause:** The macro replacement logic is currently hooked into only one of SupportCandy's many email filters: `wpsc_create_ticket_email_data`. To be truly "unified," the logic must be attached to all relevant email-related filters.
*   **Resolution Plan:** The `replace_macro` function needs to be hooked into the other email data filters provided by SupportCandy, such as `wpsc_agent_reply_email_data`, `wpsc_user_reply_email_data`, `wpsc_close_ticket_email_data`, etc. The logic within the function is generic enough to handle these different contexts, but the hooks must be added. This was deferred until the core data-retrieval logic could be verified via logging.

**4. Data Integrity Issue: Magic Method vs. `property_exists()` (Resolved)**
*   **Symptom:** The macro was initially failing to retrieve data for standard ticket properties like `subject` or `status`.
*   **Root Cause:** The code was using `property_exists($ticket, 'subject')` to check if the data was available. However, the `WPSC_Ticket` class stores its data in a private internal array and uses a `__get` magic method to provide access. `property_exists()` only checks for explicitly declared public properties and therefore fails, preventing the magic method from ever being called.
*   **Resolution:** The code was changed to access the property directly (e.g., `$value = $ticket->subject;`) and then check if the returned `$value` is null or empty. This allows the `__get` magic method to function correctly.