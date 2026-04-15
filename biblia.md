# JobPulse AI - Biblia (Design Specifications & Requirements)

## Core Requirements
1. **Universal Navigation:** All pages and features MUST be linked on the sidebar menu unless explicitly stated otherwise. No orphaned views or disconnected routes are permitted.
2. **Single Page Application (SPA) Architecture:** The application should primarily operate as an SPA (e.g., using Alpine.js `currentView` patterns) to ensure smooth, page-reload-free interactions unless a separate file is strictly necessary.
3. **Consistent Styling:** Maintain the dark mode styling (`bg-darkbg`, Tailwind utility setup) across all components.

## Data & Migration Considerations
- Legacy JSON data (`history.json`, `users.json`) is being transitioned to an SQLite database structure.
- Views like the Pipeline Dashboard and Resume Manager should utilize the SQLite `jobs` and `resumes` APIs to fetch data, not the legacy JSON files.

## Coding Standards & Conventions
- **API Security & Authentication:** API endpoints interacting with user-specific data must initialize a session via `session_start()` and verify the presence of `$_SESSION['user_id']` before proceeding to ensure authorized access.
- **IDOR Prevention:** All CRUD operations in repositories (e.g., `App\JobRepository`) must enforce ownership by requiring a `userId` parameter.
- **Data Formatting for Frontend:** The frontend expects data lists (like job history or resumes) as sequential JSON arrays. PHP code returning data from associative sources must apply `array_values()` before JSON encoding.
- **Error Handling:** To prevent information disclosure, return generic error messages in JSON responses. Detailed exception messages and internal details (e.g., cURL errors) should be logged server-side using `error_log()`.
- **Code Style:** Avoid redundant comments or logic explanations that describe self-documenting code flow to improve maintainability and readability.
- **Security:** User passwords must be hashed using PHP's native `password_hash()` function.
- **Data Storage:** Data storage uses JSON files (e.g., `data/users.json`) and supports dual-database backends (SQLite default for local deployment, MySQL for production) via `config.php`.

## Architecture Details
- The project uses object-oriented PHP classes stored in the `src/` directory, while API endpoints and CLI cron scripts are located in the `api/` directory.
- The frontend UI is primarily built as a single-page interface within `index.php`. It relies on Alpine.js for view state management and routing (using the `currentView` variable), Tailwind CSS for styling, and Lucide for icons (requiring calls to `lucide.createIcons()` after DOM updates).
- Configuration and application-wide constants like `USERS_FILE` and `DATA_DIR` are initialized in `bootstrap.php` based on `config.php`.

## Testing Rules & Setup
- For local development and verification of API endpoints, start the built-in web server using `php -S localhost:8000`.
- Automated frontend verification (e.g., using Playwright) requires an active session. A mock user must be provisioned in `data/users.json` to simulate login.
- The `App\User` class constructor supports optional injection for `usersFile`, `registrationMode`, and `requireEmailConfirmation`, allowing these to be overridden during testing to bypass the global constants set in `bootstrap.php`.
