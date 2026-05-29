import time
from playwright.sync_api import sync_playwright

def run_ui_tests():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        print("Navigating to app...")
        page.goto("http://localhost:8000")

        print("Logging in...")
        page.fill("input[type='email']", "test@example.com")
        page.fill("input[type='password']", "password")
        page.click("button:has-text('Sign In')")

        # Wait for dashboard to load
        page.wait_for_selector("text=Compile Application")
        time.sleep(2)

        def assert_feature_alert_visible(page, expected_count=1):
            visible_alerts = [loc for loc in page.locator("text=Feature Under Construction").all() if loc.is_visible()]
            assert len(visible_alerts) == expected_count, f"Expected {expected_count} visible Feature Under Construction alerts, found {len(visible_alerts)}"

        # Test Auto-Fill
        print("Testing Auto-Fill tab...")
        page.click("button:has-text('Auto-Fill')")
        time.sleep(1)
        assert page.locator("text=Automatically populate applications").is_visible()
        assert_feature_alert_visible(page, 1)

        # Test Analytics
        print("Testing Analytics tab...")
        page.click("button:has-text('Analytics')")
        time.sleep(1)
        assert page.locator("text=View detailed insights on your applications").is_visible()
        assert_feature_alert_visible(page, 1)

        # Test LinkedIn Integration
        print("Testing LinkedIn Integration tab...")
        page.click("button:has-text('LinkedIn Integration')")
        time.sleep(1)
        assert page.locator("text=Sync your LinkedIn profile directly").is_visible()
        assert_feature_alert_visible(page, 1)

        print("Testing Find Jobs tab...")
        page.click("button:has-text('Find Jobs')")
        time.sleep(1)
        assert page.locator("text=Find Openings").is_visible()
        assert_feature_alert_visible(page, 0)

        print("All UI tests passed successfully!")
        browser.close()

if __name__ == "__main__":
    run_ui_tests()
