<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <p>This guide provides a detailed overview of all the features available in SupportCandy Plus. All settings can be configured from the plugin's admin pages.</p>

    <hr>

    <h2>Ticket Hover Card</h2>
    <p>This feature displays a floating card with ticket details when you hover over a ticket in the list, providing quick access to information without leaving the page.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Found under <strong>SupportCandy Plus > General Settings</strong>. Check this to enable the hover card.</li>
        <li><strong>Hover Delay (ms):</strong> This setting appears when the feature is enabled. Set the time in milliseconds to wait before the hover card appears (e.g., <code>1000</code> for 1 second).</li>
    </ul>

    <hr>

    <h2>General Cleanup</h2>
    <p>These settings help declutter the ticket list for a cleaner interface.</p>
    <ul>
        <li><strong>Hide Empty Columns:</strong> Found under <strong>SupportCandy Plus > General Settings</strong>. Check this to automatically hide any column in the ticket list that has no content in any of its rows. This runs before the conditional hiding rules, so you can still use rules to show a column that was hidden for being empty.</li>
    </ul>

    <hr>

    <h2>Hide Ticket Types from Non-Agents</h2>
    <p>This allows you to restrict which ticket types are available to non-agent users on the ticket submission form, simplifying the user experience and ensuring tickets are categorized correctly.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Found under <strong>SupportCandy Plus > General Settings</strong>. Check this to activate the feature.</li>
        <li><strong>Custom Field Name:</strong> Select the custom field that represents your ticket types (e.g., "Ticket Category"). The plugin automatically finds the correct field ID, making it robust.</li>
        <li><strong>Ticket Types to Hide:</strong> List the ticket types you want to hide from non-agents, with each type on a new line.</li>
    </ul>

    <hr>

    <h2>Conditional Column Hiding Rules</h2>
    <p>This powerful feature allows you to create a set of rules to control the visibility of columns in the ticket list. You can define rules to show or hide specific columns based on the currently selected ticket view (filter), creating a dynamic and context-aware ticket list.</p>
    <p>All settings are under <strong>SupportCandy Plus > Conditional Hiding</strong>.</p>
    <ul>
        <li><strong>Enable Feature:</strong> Check this to activate the rule-based system.</li>
    </ul>

    <h3>How the Rule Builder Works</h3>
    <p>You can create multiple rules, which are processed from top to bottom. The last rule that applies to a specific column will determine its final visibility.</p>
    <p>Each rule consists of four parts:</p>
    <ol>
        <li>
            <strong>Action (SHOW/HIDE):</strong>
            <ul>
                <li><code>SHOW</code>: Make the selected columns visible.</li>
                <li><code>HIDE</code>: Make the selected columns invisible.</li>
            </ul>
        </li>
        <li>
            <strong>Columns:</strong>
            <ul>
                <li>Select one or more columns that this rule will affect. The list is populated automatically from your SupportCandy setup.</li>
            </ul>
        </li>
        <li>
            <strong>Condition (WHEN IN VIEW / WHEN NOT IN VIEW):</strong>
            <ul>
                <li><code>WHEN IN VIEW</code>: The rule will only apply when the selected view is active.</li>
                <li><code>WHEN NOT IN VIEW</code>: The rule will apply when <strong>any other</strong> view is active.</li>
            </ul>
        </li>
        <li>
            <strong>View:</strong>
            <ul>
                <li>Select the SupportCandy ticket view (filter) that this rule is based on. This list is also populated automatically.</li>
            </ul>
        </li>
    </ol>

    <h4>Example Scenario:</h4>
    <p>Imagine you want the "Onboarding Date" column to <strong>only</strong> be visible when you are looking at the "New Hire Tickets" view.</p>
    <ul>
        <li><strong>Rule 1:</strong> <code>HIDE</code> | <code>Onboarding Date</code> | <code>WHEN NOT IN VIEW</code> | <code>New Hire Tickets</code><br>
            <em>This rule hides the "Onboarding Date" column for all views <strong>except</strong> "New Hire Tickets".</em>
        </li>
        <li><strong>Rule 2:</strong> <code>SHOW</code> | <code>Onboarding Date</code> | <code>WHEN IN VIEW</code> | <code>New Hire Tickets</code><br>
            <em>This rule explicitly shows the "Onboarding Date" column when the "New Hire Tickets" view is selected.</em>
        </li>
    </ul>
</div>