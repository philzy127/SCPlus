import re
from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # Step 1: Log in to WordPress admin
        page.goto("http://localhost:8080/wp-login.php")
        page.fill('input[name="log"]', 'admin')
        page.fill('input[name="pwd"]', 'password')
        page.click('input[name="wp-submit"]')
        page.wait_for_load_state('networkidle')

        # Step 2: Navigate to the Conditional Hiding settings page
        page.goto("http://localhost:8080/wp-admin/admin.php?page=scp-conditional-hiding")

        # Step 3: Check initial state - No rules message should be visible
        no_rules_message = page.locator("#scp-no-rules-message")
        expect(no_rules_message).to_be_visible()

        # Step 4: Click the "Add New Rule" button
        add_rule_button = page.locator("#scp-add-rule")
        add_rule_button.click()

        # Step 5: Verify the new state
        # The "no rules" message should now be hidden
        expect(no_rules_message).to_be_hidden()

        # A new rule container should be visible
        new_rule = page.locator(".scp-rule")
        expect(new_rule).to_be_visible()

        # Step 6: Take a screenshot
        page.screenshot(path="jules-scratch/verification/verification.png")
        print("Screenshot taken. Verification successful.")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)