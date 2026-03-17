# JobPulse AI: Your Intelligent Career Co-Pilot 🚀

**JobPulse AI** is a powerful, AI-driven platform designed to eliminate the friction of job hunting. By combining real-time job searching with deep generative AI capabilities, it helps candidates move from "searching" to "interviewing" with precision-tailored applications.

---

## 🌟 Core Features

### 🔍 1. Intelligent Job Discovery
*   **Global Search**: Integrated with the **Adzuna API** to pull live job listings across various industries and locations.
*   **Smart Filters**: Filter by role, location, and workplace flexibility (Remote/Hybrid/On-site).
*   **One-Click Import**: Instantly pull job descriptions into the optimization pipeline.

### 📄 2. Resume Optimization Wizard
*   **AI Tailoring**: The **Gemini AI** engine analyzes the job description and your master resume to highlight relevant skills and rewrite bullet points for maximum impact.
*   **Cover Letter Generation**: Automatically drafts personalized cover letters that align your background with the company's specific needs.
*   **Live Tiptap Editor**: A premium, distraction-free editing experience that allows you to refine AI-generated content before saving.

### 🧠 3. Tactical Job Analysis
*   **Deep Intelligence**: Beyond simple keyword matching, the AI identifies:
    *   **Company Pain Points**: What problems is this role meant to solve?
    *   **Strategic Goals**: What are the company's likely priorities for the next 12 months?
    *   **Cultural Alignment**: Guidance on values and mission-driven questions.
*   **Interview Prep**: Generates custom interview questions based on the specific job posting.

### 💬 4. Ask AI (Career Co-pilot)
*   **Context-Aware Chat**: A real-time chat interface where the AI knows about the job you're looking at and your resume.
*   **Strategy Sessions**: Ask for advice on salary negotiation, follow-up emails, or how to explain a gap in employment.

### 🗃️ 5. Application History & management
*   **Personal Vault**: Every application is saved with its unique tailored resume, cover letter, and analysis.
*   **Notes & Tracking**: Add status updates (Applied, Interviewing, Offer, Rejected) and keep personal notes for each lead.
*   **Quick Downloads**: Instant PDF generation for all your tailored documents.

---

## 🛠️ Technology Stack
*   **Backend**: PHP 8.1+ (Raw PHP for maximum speed and portability)
*   **AI Engine**: Google Gemini Pro 1.5 & Flash Models
*   **Frontend**: Alpine.js (Reactivity), Tailwind CSS (Premium Design), Lucide Icons
*   **Editor**: Tiptap (Headless rich-text editor)
*   **PDF Engine**: mPDF & PDFParser
*   **Data**: JSON/Flat-file storage (No complex SQL setup required for deployment)

---

## 🚧 Roadmap & Upcoming Features (Not Yet Completed)

We are constantly improving JobPulse AI. The following features are currently in development:

*   **[ ] Mock Interview Mode**: A voice/text interface to practice answering the specific questions generated in the Tactical Analysis.
*   **[ ] Auto-Fill Integration**: Browser extension to help auto-fill application forms using your saved resumes.
*   **[ ] Advanced Analytics Dashboard**: Visualizing your application conversion rate and identifying which resume versions perform best.
*   **[ ] LinkedIn Integration**: Direct import of job postings via URL bookmarklet.

---

## ⚠️ Security Warning

**IMPORTANT:** The present version of JobPulse AI is intended to run **strictly locally**. Security has not been fully vetted for deployment on a public-facing server. Please do not host this application publicly.

---

## 🚀 Getting Started & Installation

To ensure cross-platform compatibility and ease of setup, follow these steps using **XAMPP**:

1. **Install XAMPP & Composer:** Download and install XAMPP and [Composer](https://getcomposer.org/) (PHP dependency manager) for your operating system.
2. **Copy Files:** Copy the entire project repository into the `htdocs` folder within your XAMPP installation directory (e.g., `C:\xampp\htdocs\jobpulse`).

### Option A: Automated Setup (Recommended)
Open a terminal or command prompt in your project directory (`C:\xampp\htdocs\jobpulse`), and run the installation script for your OS:
- **Windows:** Run `.\install.ps1` in PowerShell
- **Mac/Linux:** Run `bash install.sh`
*(This script automatically installs Composer dependencies and creates your default configuration and data files).*

### Option B: Manual Setup
If you prefer not to use the automated scripts, follow these steps manually:
1. Open a terminal in `C:\xampp\htdocs\jobpulse` and run: `composer install`
2. Rename `config.php.new` to `config.php`.
3. Create the `data` directory if it does not exist, and copy `data/users.example.json` to `data/users.json`.

---

### Final Steps (For Both Options)

1. **Configure API Keys:** Open `config.php` and insert your **Gemini API Key**. Obtain one from [Google AI Studio](https://aistudio.google.com/app/apikey).
   - *Note: Adzuna integration is currently a placeholder and has not been fully implemented yet.*
2. **Start Server:** Start Apache in the XAMPP Control Panel and navigate to `http://localhost/jobpulse` in your browser.
3. **Log In:** Use the default admin credentials:
   - **Email:** admin@admin.com
   - **Password:** admin

---

## 📖 Basic Usage

1. **Log In:** Use the default admin credentials to access the dashboard.
2. **Upload Resume:** Navigate to the resume section and upload your master resume.
3. **Select Resume:** Ensure the uploaded resume is selected for the current session.
4. **Input Job details:** Paste the job description of the position you are applying for.
5. **Generate:** Click the generate button to process the application and create an optimized, tailored resume and cover letter.

---

## 📝 License
This project is for personal use and internal application management.
