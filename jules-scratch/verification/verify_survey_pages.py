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

        # 2. Navigate to the main survey page and take screenshots of each tab
        base_url = "http://localhost:8080/wp-admin/admin.php?page=scp-ats-survey"
        tabs = ["main", "questions", "submissions", "results", "settings"]

        for tab in tabs:
            page.goto(f"{base_url}&tab={tab}")
            # Use a generic expectation as headings might differ per tab
            expect(page.get_by_role("heading", level=1)).to_be_visible()
            page.screenshot(path=f"jules-scratch/verification/{tab}-tab.png")
            print(f"{tab.capitalize()} tab screenshot taken.")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")
        print("Error screenshot taken.")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)