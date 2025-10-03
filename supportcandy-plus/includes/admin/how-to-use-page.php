<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <p>This guide provides a detailed overview of all the features available in SupportCandy Plus. All settings can be configured from the plugin's admin pages.</p>

    <hr>

    <h2>General Settings</h2>
    <p>This page contains general-purpose features for improving the user experience and cleaning up the interface. You can find it under <strong>SupportCandy Plus > General Settings</strong>.</p>

    <h3>Ticket Details Card</h3>
    <p>This feature displays a floating card with key ticket details when you right-click on a ticket in the list, providing quick access to information without leaving the page.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Check this to enable the right-click card.</li>
    </ul>

    <h3>General Cleanup</h3>
    <p>These settings help declutter the ticket list for a cleaner interface.</p>
    <ul>
        <li><strong>Hide Empty Columns:</strong> Automatically hides any column in the ticket list that has no content in any of its rows.</li>
        <li><strong>Hide Priority Column:</strong> Hides the "Priority" column if all visible tickets have a priority of "Low," reducing visual noise.</li>
    </ul>

    <h3>Hide Ticket Types from Non-Agents</h3>
    <p>This allows you to restrict which ticket types are available to non-agent users on the ticket submission form, simplifying the user experience and ensuring tickets are categorized correctly.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Check this to activate the feature.</li>
        <li><strong>Custom Field Name:</strong> Select the custom field that represents your ticket types (e.g., "Ticket Category").</li>
        <li><strong>Ticket Types to Hide:</strong> List the ticket types you want to hide from non-agents, with each type on a new line.</li>
    </ul>

    <hr>

    <h2>Conditional Column Hiding</h2>
    <p>This powerful feature allows you to create a set of rules to control the visibility of columns in the ticket list. You can define rules to show or hide specific columns based on the currently selected ticket view (filter), creating a dynamic and context-aware ticket list.</p>
    <p>All settings are under <strong>SupportCandy Plus > Conditional Hiding</strong>.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Check this to activate the rule-based system.</li>
    </ul>
    <h3>How the Rule Builder Works</h3>
    <p>Each rule consists of four parts:</p>
    <ol>
        <li><strong>Action (SHOW / SHOW ONLY / HIDE):</strong>
            <ul>
                <li><code>HIDE</code>: Explicitly hides a column in a specific view. This is the most powerful action and acts as a final veto.</li>
                <li><code>SHOW ONLY</code>: Makes the column visible in the specified view but implicitly hides it in <strong>all other views</strong> by default. This is the most efficient way to handle context-specific columns.</li>
                <li><code>SHOW</code>: Explicitly shows a column. This is primarily used to create an exception to a <code>SHOW ONLY</code> rule.</li>
            </ul>
        </li>
        <li><strong>Column:</strong> The column the rule will affect.</li>
        <li><strong>Condition (WHEN IN VIEW / WHEN NOT IN VIEW):</strong> Determines if the rule applies when the selected view is active or not.</li>
        <li><strong>View:</strong> The SupportCandy ticket view (filter) that the rule is based on.</li>
    </ol>

    <hr>

    <h2>Queue Macro</h2>
    <p>This feature adds a <code>{{queue_count}}</code> macro that you can use in your SupportCandy email templates. When an email is sent, the macro is replaced with the number of open tickets in the same queue, giving customers a real-time view of their position.</p>
    <p>All settings are under <strong>SupportCandy Plus > Queue Macro</strong>.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Check this to activate the macro.</li>
        <li><strong>Ticket Type Field:</strong> Select the field that distinguishes your queues (e.g., Category, Priority, or a custom field).</li>
        <li><strong>Non-Closed Statuses:</strong> Use the dual-list box to select which ticket statuses should be counted as "open" for the queue count.</li>
        <li><strong>Test Queue Counts:</strong> Click the "Run Test" button to see the current counts for all queue types based on your saved settings. This is a great way to verify your configuration without sending test emails.</li>
    </ul>

    <hr>

    <h2>After Hours Notice</h2>
    <p>This feature shows a customizable message at the top of the "Create Ticket" form if a user is accessing it outside of your defined business hours, on weekends, or on specified holidays.</p>
    <p>All settings are under <strong>SupportCandy Plus > After Hours Notice</strong>.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Check this to display the notice.</li>
        <li><strong>After Hours Start / Before Hours End:</strong> Define your business hours using a 24-hour format (e.g., start at 17 and end at 8).</li>
        <li><strong>Include All Weekends:</strong> Check this to show the notice all day on Saturdays and Sundays.</li>
        <li><strong>Holidays:</strong> List any holidays (one per line, in <code>MM-DD-YYYY</code> format) where the notice should be shown all day.</li>
        <li><strong>After Hours Message:</strong> Use the rich text editor to create the message that will be displayed to users.</li>
    </ul>
</div>