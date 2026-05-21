# Recommendations for JobPulse AI

## Changes Implemented

- Added "Coming Soon" nav section in the frontend navigation to reflect items identified in README.md as planned items. These planned items are:
    * Mock Interview Mode
    * Advanced Analytics Dashboard
    * LinkedIn Import/Integration
    * Auto-Fill Extension

## Upgrade Recommendations

1. **Test Coverage & Automated QA**: Currently there are manual tests and minimal tests in `tests/`. Adding a real test framework (e.g., PHPUnit) and CI/CD pipelines will greatly help maintain stability.
2. **Move away from inline scripts**: `index.php` contains a large amount of inline javascript handling Alpine states. Moving these to external `.js` files will make it easier to maintain.
3. **API Rate Limiting**: The current AI tools make heavy use of the Gemini API which can be easily abused or exhausted. Consider implementing rate limiting to prevent run-away AI calls.
