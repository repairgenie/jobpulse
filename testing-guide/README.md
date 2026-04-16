# Agent Testing & Audit Guide

This guide details how AI agents should autonomously verify and audit the application to check for correctness, look for bugs, and implement fixes.

## 1. Setting up the Environment
Before executing any endpoints or UI tests, agents must set up the environment:
1. **Configuration:** Copy `config.php.new` to `config.php` and populate missing API keys (like `GEMINI_API_KEY`) if needed, although local functionality should be mocked if possible.
2. **Local Server:** Start the built-in PHP web server in the background:
   ```bash
   php -S localhost:8000 > server.log 2>&1 &
   ```
3. **Provisioning Test Users:** To simulate logins and API authorization (where `$_SESSION['user_id']` is checked), a mock user must exist. Agents should provision an initial user in `data/users.json` (ensure passwords are hashed via PHP `password_hash()`) and log in if automated UI tests require it.

## 2. API Endpoints Auditing
1. When fixing API endpoints (in `api/`), agents should:
   - Make sure `session_start()` is called and `$_SESSION['user_id']` is verified before proceeding (IDOR prevention).
   - Verify that arrays returned for frontend consumption apply `array_values()` if sourced from associative arrays.
   - Use commands like `curl` against `localhost:8000/api/your_endpoint.php` to verify responses. Ensure a session cookie is provided if required.

## 3. Frontend / UI Verification
1. The project uses Alpine.js for view state management. Changes to `index.php` should be checked visually.
2. The agent should use Playwright scripts for automated UI test verification and generating screenshots, logging in as the mock user provisioned earlier.
3. Don't forget that if new Lucide icons are added dynamically, `lucide.createIcons()` must be triggered.

## 4. Testing & Code Quality Checks
1. Currently there's no major PHPUnit test suite by default. Agents must verify syntax using `php -l <file>` for modified PHP scripts.
2. When testing classes like `App\User`, agents can override global constants in `bootstrap.php` using the optional constructor injection parameters for `usersFile`, `registrationMode`, and `requireEmailConfirmation`.
3. Read `biblia.md` for overall architecture and coding standards to ensure code aligns with the enterprise-grade objectives.
