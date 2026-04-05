# JobPulse AI - Biblia (Design Specifications & Requirements)

## Core Requirements
1. **Universal Navigation:** All pages and features MUST be linked on the sidebar menu unless explicitly stated otherwise. No orphaned views or disconnected routes are permitted.
2. **Single Page Application (SPA) Architecture:** The application should primarily operate as an SPA (e.g., using Alpine.js `currentView` patterns) to ensure smooth, page-reload-free interactions unless a separate file is strictly necessary.
3. **Consistent Styling:** Maintain the dark mode styling (`bg-darkbg`, Tailwind utility setup) across all components.

## Data & Migration Considerations
- Legacy JSON data (`history.json`, `users.json`) is being transitioned to an SQLite database structure.
- Views like the Pipeline Dashboard and Resume Manager should utilize the SQLite `jobs` and `resumes` APIs to fetch data, not the legacy JSON files.
