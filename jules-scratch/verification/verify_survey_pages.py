from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # 1. Login
        page.goto("http://localhost:8080/wp-login.php")
        page.fill('input[name="log"]', "admin")
        page.fill('input[name="pwd"]', "password")
        page.click('input[name="wp-submit"]')
        page.wait_for_url("http://localhost:8080/wp-admin/")
        print("Login successful.")

        # 2. Navigate to Manage Questions page and take screenshot
        page.goto("http://localhost:8080/wp-admin/admin.php?page=scp-ats-manage-questions")
        expect(page.get_by_role("heading", name="Manage Survey Questions")).to_be_visible()
        page.screenshot(path="jules-scratch/verification/manage-questions.png")
        print("Manage Questions screenshot taken.")

        # 3. Navigate to Manage Submissions page and take screenshot
        page.goto("http://localhost:8080/wp-admin/admin.php?page=scp-ats-manage-submissions")
        expect(page.get_by_role("heading", name="Manage Survey Submissions")).to_be_visible()
        page.screenshot(path="jules-scratch/verification/manage-submissions.png")
        print("Manage Submissions screenshot taken.")

        # 4. Navigate to View Results page and take screenshot
        page.goto("http://localhost:8080/wp-admin/admin.php?page=scp-ats-view-results")
        expect(page.get_by_role("heading", name="Survey Results")).to_be_visible()
        page.screenshot(path="jules-scratch/verification/view-results.png")
        print("View Results screenshot taken.")

        # 5. Navigate to Settings page and take screenshot
        page.goto("http://localhost:8080/wp-admin/admin.php?page=scp-ats-settings")
        expect(page.get_by_role("heading", name="After Ticket Survey Settings")).to_be_visible()
        page.screenshot(path="jules-scratch/verification/settings.png")
        print("Settings screenshot taken.")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")
        print("Error screenshot taken.")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)