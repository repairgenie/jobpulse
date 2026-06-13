# JobPulse AI - Undeveloped Features Fixes & Upgrade Recommendations

## What We Fixed
* **Identified Undeveloped/Pending Features:** The application's core documentation and roadmap reference upcoming features such as **Auto-Fill**, **Analytics**, and **LinkedIn Integration** that were completely disconnected.
* **Universal Navigation Enforcement:** In adherence to the "Universal Navigation" mandate from `biblia.md` (which states all pages/features MUST be linked on the sidebar menu with no orphaned views), we updated the `index.php` Alpine.js frontend.
* **Added Sidebar Links:** We injected universal navigation sidebar buttons for **Auto-Fill**, **Analytics**, and **LinkedIn Integration** using the respective `currentView` mappings (`'autofill'`, `'analytics'`, `'linkedin'`). Used Lucide icons `pen-tool`, `bar-chart`, and `link` for each item.
* **Added Placeholder Views:** Developed placeholder blocks (`x-show="currentView === '...'"` ) for each of these three missing sections. Used a structured format utilizing the existing `<i data-lucide="construction">` feature status alert style to explicitly state to users that these views are active on the roadmap but pending development.

## Recommendations for Upgrades

### 1. Feature Upgrades
* **Dynamic Adzuna Job Search (Find Openings):**
  - *Current State:* Under construction.
  - *Recommendation:* Finish integrating `api/search_jobs.php` to pull live data from the Adzuna API, parsing and caching results directly to the SQLite backend. Provide real-time localized listings.
* **Analytics Dashboard:**
  - *Recommendation:* Expand the current limited "Match Analytics" shown post-compile into a comprehensive history. Show job application conversion rates over time using Chart.js or an Alpine-native charting library. Track keywords historically to suggest which new skills a job seeker should learn.
* **Automated Auto-Fill Extension:**
  - *Recommendation:* Build a companion Chrome/Firefox browser extension that interacts securely with the local server to extract the user's compiled resume and directly inject it into Greenhouse, Lever, and Workday forms.
* **OAuth / LinkedIn Integration:**
  - *Recommendation:* Allow users to sign in via LinkedIn OAuth. Provide an import utility that pulls LinkedIn Profile JSON/PDF formats directly into the "Resumes" storage, automatically seeding their baseline profile.

### 2. Security Tweaks
* **CSRF Protection:**
  - *Recommendation:* Implement anti-CSRF tokens for all state-changing API endpoints, passing them either as a hidden form field or a required API header.
* **Rate Limiting:**
  - *Recommendation:* Enforce strict rate-limiting on authentication and third-party API execution endpoints (e.g., `api/auth.php` and `api/search_jobs.php`) to avoid abuse or API cost overruns.
* **Secure Session Handling:**
  - *Recommendation:* Enforce `session_regenerate_id(true)` upon login and configure cookies to `HttpOnly`, `Secure`, and `SameSite=Strict`.

### 3. Performance Enhancements
* **Asset Minification & Bundling:**
  - *Recommendation:* Implement a build step (using Vite, Webpack, or similar) to compile Tailwind classes and bundle frontend scripts (Alpine, Lucide). This will reduce initial paint times and payload sizes.
* **Asynchronous Processing:**
  - *Recommendation:* Move CPU/API heavy tasks like resume parsing, AI calls to Gemini, and PDF compilations to a background worker queue (e.g., using Redis or a daemon-based worker pattern) rather than holding HTTP requests open.
* **Database Optimization:**
  - *Recommendation:* Transition entirely from legacy JSON `data/*.json` files to the SQLite structure to prevent I/O blocking during concurrent reads/writes and use proper indexing on `job_id`, `user_id`, and `status`.
