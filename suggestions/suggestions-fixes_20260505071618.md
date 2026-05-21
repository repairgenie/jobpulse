# JobPulse AI - Suggestions and Fixes

## Fixes Applied
- Explored the system and identified unimplemented roadmap features (Mock Interview Mode, Advanced Analytics Dashboard, LinkedIn Import/Integration, and Auto-Fill Extension).
- Added a "Coming Soon" section to the navigation sidebar in `index.php` that includes links to these features to ensure universal navigation.
- Created a generic "Coming Soon" placeholder view that renders when navigating to these new menus, preventing orphaned or non-functional links.

## Recommendations for Upgrades

### Features
1. **Interactive Kanban Board**: Consider replacing or supplementing the list-based Pipeline Dashboard with a drag-and-drop Kanban board (e.g., using a library like SortableJS) to allow users to easily move jobs between "Applied", "Interviewing", and "Offer" statuses.
2. **Chrome Extension for Auto-fill**: For the planned "Auto-Fill Ext", implement a robust Chromium-based browser extension that can securely parse the user's selected primary resume and automatically map and populate standard ATS (Applicant Tracking System) form fields (Workday, Greenhouse, Lever).
3. **Full Adzuna/RemoteOK Integration**: Finalize the "Find Jobs" functionality by implementing robust server-side API calls to fetch, normalize, and cache real-time job listings from Adzuna and RemoteOK.
4. **Networking & Contact Tracker**: Add a CRM-like feature to track networking contacts, informational interviews, and referrals for each specific job application.

### Security
1. **CSRF Protection**: The application currently relies heavily on simple POST endpoints without CSRF tokens. Implement a session-based anti-CSRF token mechanism for all state-changing endpoints.
2. **Prepared Statements**: Ensure all database interactions (via SQLite or MySQL) use PDO prepared statements to mitigate SQL injection risks as the app migrates fully away from JSON files.
3. **Rate Limiting**: Implement strict rate limiting on authentication (`api/auth.php`) and AI generative endpoints (e.g., `api/optimize_resume.php`) to prevent brute force attacks and abuse of expensive API quotas.
4. **Two-Factor Authentication (2FA)**: Introduce TOTP-based 2FA for users to add an extra layer of security to their application data.

### Performance Tweaks
1. **Asynchronous Background Processing**: AI calls (Gemini API) and PDF generation can be slow. Offload these tasks to a background queue system (e.g., using RabbitMQ or a simple database-backed job table with a cron worker) and poll the frontend for status updates.
2. **Caching Layer (Redis/Memcached)**: Implement Redis caching for frequent API responses (like standard job lookups) and to cache user session data, reducing the I/O bottleneck from file-based operations.
3. **Database Indexing**: As the user base and history grow, ensure critical columns in the SQLite/MySQL schema (`user_id`, `status`, `date_applied`) are properly indexed for rapid retrieval in the Pipeline Dashboard.
4. **Asset Minification and Bundling**: Use a build step (e.g., Vite or Webpack) to minify CSS/JS assets rather than relying entirely on CDN links, to improve initial load times and resilience.
