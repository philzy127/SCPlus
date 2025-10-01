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
    <p>You can create multiple rules to define the visibility of your columns. The rules are processed in a logical order to determine the final state of each column in any given view.</p>
    <p>Each rule consists of four parts:</p>
    <ol>
        <li>
            <strong>Action (SHOW / SHOW ONLY / HIDE):</strong> This is the core of the rule.
            <ul>
                <li><code>HIDE</code>: Explicitly hides a column in a specific view. This is the most powerful action and acts as a final veto, overriding any other rules for that column in that view.</li>
                <li><code>SHOW ONLY</code>: This is the best way to handle columns that are only relevant in one context. It makes the column visible in the specified view but implicitly hides it in <strong>all other views</strong> by default.</li>
                <li><code>SHOW</code>: Explicitly shows a column. This is primarily used to create exceptions and override the implicit hiding caused by a <code>SHOW ONLY</code> rule in another view.</li>
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
    <p>Imagine you have a column named "Billing Code" that should only be seen by the Accounting department. You also have a special "Manager" view where managers need to see it as well.</p>
    <ul>
        <li>
            <strong>Rule 1:</strong> <code>SHOW ONLY</code> | <code>Billing Code</code> | <code>WHEN IN VIEW</code> | <code>Accounting View</code><br>
            <em>This single rule accomplishes two things: it makes "Billing Code" visible in the "Accounting View" and hides it everywhere else by default. You no longer need extra "hide" rules for every other view.</em>
        </li>
        <li>
            <strong>Rule 2:</strong> <code>SHOW</code> | <code>Billing Code</code> | <code>WHEN IN VIEW</code> | <code>Manager View</code><br>
            <em>This rule creates an exception. It overrides the default hiding from the "Show Only" rule and makes the "Billing Code" column visible in the "Manager View" as well.</em>
        </li>
    </ul>
</div>