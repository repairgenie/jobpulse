<?php
session_start();

if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/data');
if (!defined('HISTORY_FILE')) define('HISTORY_FILE', DATA_DIR . '/history.json');
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', __DIR__ . '/uploads');

require_once __DIR__ . '/src/User.php';

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0777, true);

$isLoggedIn = isset($_SESSION['user_id']);
$userEmail = $_SESSION['email'] ?? '';

// Fetch history
$allHistory = file_exists(HISTORY_FILE) ? (json_decode(file_get_contents(HISTORY_FILE), true) ?: []) : [];
$history = array_filter($allHistory, function($item) {
    return isset($item['user_id']) && isset($_SESSION['user_id']) && $item['user_id'] === $_SESSION['user_id'];
});
$history = array_slice($history, 0, 5); 

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobPulse AI</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { 
                        primary: '#6366f1',   
                        secondary: '#8b5cf6', 
                        darkbg: '#0f172a',    
                        sidebar: '#1e293b',
                        card: '#1e293b'   
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Analysis modal prose styles */
        .prose-analysis h1 { font-size: 1.35rem; font-weight: 800; color: #f1f5f9; margin: 1.5rem 0 0.75rem; }
        .prose-analysis h2 { font-size: 1.1rem;  font-weight: 700; color: #e2e8f0; margin: 1.4rem 0 0.6rem; border-bottom: 1px solid rgba(255,255,255,0.07); padding-bottom: 0.35rem; }
        .prose-analysis h3 { font-size: 0.95rem; font-weight: 700; color: #cbd5e1; margin: 1.2rem 0 0.5rem; }
        .prose-analysis p  { color: #94a3b8; line-height: 1.75; margin-bottom: 0.75rem; font-size: 0.875rem; }
        .prose-analysis ul { margin: 0.5rem 0 1rem 0; padding-left: 0; list-style: none; }
        .prose-analysis li { color: #94a3b8; font-size: 0.875rem; line-height: 1.7; padding: 0.3rem 0 0.3rem 1.1rem; position: relative; }
        .prose-analysis li::before { content: '›'; position: absolute; left: 0; color: #6366f1; font-weight: 800; }
        .prose-analysis strong { color: #e2e8f0; font-weight: 700; }
        .prose-analysis hr { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin: 1.5rem 0; }
        .prose-analysis blockquote { border-left: 3px solid #6366f1; padding: 0.5rem 1rem; background: rgba(99,102,241,0.07); border-radius: 0 8px 8px 0; margin: 1rem 0; color: #94a3b8; font-style: italic; }
        /* code inline */
        .prose-analysis code { background: rgba(99,102,241,0.15); color: #a5b4fc; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.8rem; font-family: monospace; }
        
        .custom-scrollbar-light::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar-light::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        /* Tiptap / Paper Style Editor */
        .editor-container { background: #0f172a; min-height: 100vh; padding: 40px 20px; }
        .paper-page {
            background: white;
            color: #1a1a1a;
            width: 100%;
            max-width: 800px;
            min-height: 1032px; /* Roughly A4 ratio */
            margin: 0 auto;
            padding: 60px 80px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            border-radius: 4px;
            position: relative;
            outline: none;
        }
        .paper-editor .ProseMirror {
            outline: none;
            min-height: 900px;
        }
        /* Prose Styles for Editor */
        .paper-editor .ProseMirror h1 { font-size: 24pt; font-weight: 800; margin-bottom: 6pt; border-bottom: 2px solid #6366f1; padding-bottom: 4pt; }
        .paper-editor .ProseMirror h2 { font-size: 14pt; font-weight: 700; margin-top: 14pt; margin-bottom: 4pt; color: #333; }
        .paper-editor .ProseMirror h3 { font-size: 11pt; font-weight: 700; margin-top: 10pt; margin-bottom: 2pt; }
        .paper-editor .ProseMirror p { font-size: 10.5pt; line-height: 1.5; margin-bottom: 8pt; }
        .paper-editor .ProseMirror ul { list-style-type: disc; padding-left: 20pt; margin-bottom: 10pt; }
        .paper-editor .ProseMirror li { font-size: 10.5pt; margin-bottom: 3pt; }

        /* Wizard Modal */
        .wizard-step-active { color: #6366f1; border-bottom: 2px solid #6366f1; }
    </style>
    <!-- Tiptap Editor Scripts (ESM) -->
    <script type="module">
        import { Editor } from 'https://esm.sh/@tiptap/core@2.0.3?bundle';
        import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.0.3?bundle';
        import Underline from 'https://esm.sh/@tiptap/extension-underline@2.0.3?bundle';
        import Link from 'https://esm.sh/@tiptap/extension-link@2.0.3?bundle';

        window.TiptapEditor = Editor;
        window.getTiptapExtensions = () => [
            StarterKit,
            Underline,
            Link.configure({ openOnClick: false })
        ];
    </script>
</head>
<body class="bg-darkbg text-slate-300 font-sans antialiased min-h-screen selection:bg-primary selection:text-white" <?php if(!$isLoggedIn) echo 'x-data="authApp()"'; else echo 'x-data="dashboardApp()"'; ?>>
    
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-primary/10 rounded-full blur-[120px] pointer-events-none z-0"></div>

    <?php if (!$isLoggedIn): ?>
    
    <!-- LANDING / AUTHENTICATION VIEW -->
    <div class="relative z-10 min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-primary to-secondary shadow-[0_0_30px_rgba(99,102,241,0.4)] mb-6 border border-white/10">
                <i data-lucide="zap" class="w-8 h-8 text-white fill-white/20"></i>
            </div>
            <h2 class="text-4xl font-extrabold text-white tracking-tight">JobPulse <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary">AI</span></h2>
            <p class="mt-3 text-sm text-slate-400 font-medium">Your intelligent career co-pilot.</p>
        </div>

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-card/80 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-3xl sm:px-10 border border-slate-700/50 relative overflow-hidden">
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-secondary/20 rounded-full blur-3xl"></div>
                <div class="flex space-x-1 bg-darkbg/50 p-1 rounded-xl mb-8 border border-slate-700/50 relative z-10">
                    <button @click="tab = 'login'; error = null; success = null;" :class="tab === 'login' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'" class="w-1/2 rounded-lg py-2.5 text-sm font-bold transition-all">Log In</button>
                    <button @click="tab = 'register'; error = null; success = null;" :class="tab === 'register' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'" class="w-1/2 rounded-lg py-2.5 text-sm font-bold transition-all">Sign Up</button>
                </div>
                <div x-show="error" x-transition x-cloak class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl flex items-start" style="display: none;">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-400 mt-0.5 mr-3 shrink-0"></i>
                    <span class="text-sm font-medium text-red-400" x-text="error"></span>
                </div>
                <div x-show="success" x-transition x-cloak class="mb-6 p-4 bg-primary/10 border border-primary/20 rounded-xl flex items-start" style="display: none;">
                    <i data-lucide="check-circle-2" class="w-5 h-5 text-primary mt-0.5 mr-3 shrink-0"></i>
                    <span class="text-sm font-medium text-primary" x-text="success"></span>
                </div>
                <form @submit.prevent="submitAuth" class="space-y-4 relative z-10">
                    <div>
                        <input x-model="form.email" type="email" required class="block w-full rounded-xl bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="Email Address">
                    </div>
                    <div>
                        <input x-model="form.password" type="password" required class="block w-full rounded-xl bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="Password">
                    </div>
                    
                    <div x-show="tab === 'register'" x-transition class="grid grid-cols-6 gap-3 pt-2" style="display:none;">
                        <div class="col-span-3">
                            <input x-model="form.first_name" type="text" class="block w-full rounded-xl bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="First Name">
                        </div>
                        <div class="col-span-3">
                            <input x-model="form.last_name" type="text" class="block w-full rounded-xl bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="Last Name">
                        </div>
                        <div class="col-span-6 sm:col-span-3">
                            <input x-model="form.city" type="text" class="block w-full rounded-xl bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="City">
                        </div>
                        <div class="col-span-3 sm:col-span-1">
                            <input x-model="form.state" type="text" class="block w-full rounded-xl bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="State">
                        </div>
                        <div class="col-span-3 sm:col-span-2">
                            <input x-model="form.zip_code" type="text" class="block w-full rounded-xl bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="Zip Code">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-[0_0_20px_rgba(99,102,241,0.25)] text-sm font-bold text-white bg-gradient-to-r from-primary to-secondary hover:from-indigo-500 hover:to-violet-500 transition-all hover:-translate-y-0.5 disabled:opacity-50">
                            <span x-show="!loading" x-text="tab === 'login' ? 'Sign In' : 'Create Account'"></span>
                            <span x-show="loading" class="flex items-center" style="display: none;"><i data-lucide="loader-2" class="animate-spin -ml-1 mr-2 h-4 w-4"></i> Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function authApp() {
            return {
                tab: 'login', form: { email: '', password: '', first_name: '', last_name: '', city: '', state: '', zip_code: '' }, loading: false, error: null, success: null,
                async submitAuth() {
                    this.loading = true; this.error = null; this.success = null;
                    const fd = new FormData(); fd.append('email', this.form.email); fd.append('password', this.form.password);
                    if (this.tab === 'register') {
                        fd.append('first_name', this.form.first_name);
                        fd.append('last_name', this.form.last_name);
                        fd.append('city', this.form.city);
                        fd.append('state', this.form.state);
                        fd.append('zip_code', this.form.zip_code);
                    }
                    try {
                        const res = await fetch(`/api/auth.php?action=${this.tab}`, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            if (this.tab === 'login') window.location.reload();
                            else { this.success = data.message; this.form.password = ''; }
                        } else this.error = data.message;
                    } catch (e) { this.error = "A server error occurred."; } 
                    finally { this.loading = false; }
                }
            }
        }
    </script>
    
    <?php else: ?>
    
    <!-- DASHBOARD VIEW -->
    <div class="h-screen flex overflow-hidden relative z-10" x-init="fetchResumes(); fetchJobs()">
        
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
             <div class="flex flex-col w-72">
                <div class="flex flex-col h-0 flex-1 bg-sidebar border-r border-slate-700/50 shadow-2xl">
                    <div class="flex-1 flex flex-col pt-8 pb-4 overflow-y-auto">
                        <div class="flex items-center flex-shrink-0 px-6 mb-10">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white shadow-lg border border-white/10 shrink-0">
                                <i data-lucide="zap" class="w-5 h-5 fill-white/20"></i>
                            </div>
                            <span class="ml-3 font-extrabold text-2xl tracking-tight text-white">JobPulse</span>
                        </div>
                        <nav class="mt-2 flex-1 px-4 space-y-2">
                            <button @click="currentView = 'vibe_check'; setTimeout(() => lucide.createIcons(), 50)" :class="currentView === 'vibe_check' ? 'bg-primary/10 text-primary border-primary/20' : 'text-slate-400 hover:text-white border-transparent hover:bg-slate-800'" class="w-full group flex items-center px-4 py-3 text-sm font-bold rounded-xl border transition-all">
                                <i data-lucide="layout-dashboard" class="mr-3 h-5 w-5 opacity-100"></i> Compile AI App
                            </button>
                            <button @click="currentView = 'find_jobs'; setTimeout(() => lucide.createIcons(), 50)" :class="currentView === 'find_jobs' ? 'bg-primary/10 text-primary border-primary/20' : 'text-slate-400 hover:text-white border-transparent hover:bg-slate-800'" class="w-full group flex items-center px-4 py-3 text-sm font-bold rounded-xl border transition-all">
                                <i data-lucide="search" class="mr-3 h-5 w-5 opacity-100"></i> Find Jobs
                            </button>
                            <button @click="currentView = 'my_jobs'; setTimeout(() => lucide.createIcons(), 50)" :class="currentView === 'my_jobs' ? 'bg-primary/10 text-primary border-primary/20' : 'text-slate-400 hover:text-white border-transparent hover:bg-slate-800'" class="w-full group flex items-center px-4 py-3 text-sm font-bold rounded-xl border transition-all">
                                <i data-lucide="briefcase" class="mr-3 h-5 w-5 opacity-100"></i> My Jobs
                            </button>
                        </nav>
                    </div>
                    <div class="flex-shrink-0 flex border-t border-slate-700/50 p-4 bg-darkbg/30">
                        <div class="flex items-center">
                            <div class="inline-block h-10 w-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center">
                                <i data-lucide="user" class="h-5 w-5 text-slate-400"></i>
                            </div>
                            <div class="ml-3 flex-1 overflow-hidden">
                                <p class="text-sm font-bold text-white truncate"><?= htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))) ?: htmlspecialchars($userEmail) ?></p>
                            </div>
                            <button @click="logout" class="ml-auto p-2 text-slate-400 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none custom-scrollbar pb-20 md:pb-0">
            
            <!-- VIBE CHECK VIEW -->
            <div x-show="currentView === 'vibe_check'" class="max-w-6xl mx-auto px-4 sm:px-6 md:px-10 py-8 md:py-12" style="display:none;">
                <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-3 tracking-tight drop-shadow-md">Compile Application</h1>
                        <p class="text-slate-400 text-sm md:text-base font-medium max-w-2xl">Use your saved profile to Vibe Check a job description, Generate a Cover Letter, or Optimize your Resume.</p>
                    </div>
                    <button @click="resetFields()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center border border-slate-700 h-fit">
                        <i data-lucide="refresh-ccw" class="w-4 h-4 mr-2"></i> Reset Fields
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column -->
                    <div class="lg:col-span-2 space-y-8">
                        
                        <!-- Step 1: Multi-Resume Profiles -->
                        <div class="bg-card/80 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-6 sm:p-8 shadow-2xl">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Step 1: Select Active Resume Profile</label>
                                    <p class="text-sm text-slate-300 font-medium">Categorize and manage multiple resumes for tailored AI results.</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="showGeneralizeModal = true; generalizeStep = 1" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-sm font-bold shadow-lg transition-colors flex items-center border border-slate-600">
                                        <i data-lucide="wand-2" class="w-4 h-4 mr-1"></i> Generalize
                                    </button>
                                    <button @click="showUploadModal = !showUploadModal" class="px-4 py-2 bg-primary hover:bg-indigo-500 text-white rounded-xl text-sm font-bold shadow-lg transition-colors flex items-center">
                                        <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Add New
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Resume List Box -->
                            <div class="bg-darkbg border border-slate-700 rounded-2xl overflow-hidden shadow-inner">
                                <div x-show="resumes.length === 0" class="p-8 text-center text-slate-500 text-sm font-medium">
                                    No resumes loaded yet. Add your first PDF!
                                </div>
                                
                                <ul class="divide-y divide-slate-700/50">
                                    <template x-for="res in resumes" :key="res.id">
                                        <li class="p-4 flex items-center justify-between transition-colors hover:bg-slate-800/30" :class="{'bg-primary/5 border-l-4 border-l-primary': res.id === activeResumeId}">
                                            <div class="flex items-center flex-1 cursor-pointer" @click="activeResumeId = res.id">
                                                <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-600 flex items-center justify-center mr-4" :class="{'bg-primary/20 border-primary/50 text-primary': res.id === activeResumeId}">
                                                    <i data-lucide="file-check-2" class="w-5 h-5"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-white mb-0.5" x-text="res.filename"></p>
                                                    <div class="flex space-x-2 items-center">
                                                        <span class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded bg-slate-700 text-slate-300" x-text="res.category"></span>
                                                        <span x-show="res.is_primary" class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded bg-yellow-500/20 text-yellow-400 border border-yellow-500/20"><i data-lucide="star" class="w-3 h-3 inline mr-1"></i>Primary</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex space-x-2">
                                                <button x-show="!res.is_primary" @click="setPrimary(res.id)" class="text-xs font-bold text-slate-400 hover:text-white px-2 py-1 bg-slate-800 rounded-lg" title="Make Primary">★</button>
                                                <button @click="deleteResume(res.id)" class="text-xs font-bold text-red-400 hover:text-red-300 px-2 py-1 bg-red-400/10 hover:bg-red-400/20 rounded-lg"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <!-- Upload Modal Area Inside Card -->
                            <div x-show="showUploadModal" x-transition.opacity class="mt-4 p-5 bg-slate-800/50 border border-slate-700 rounded-2xl relative overflow-hidden" style="display:none;">
                                <button @click="showUploadModal = false" class="absolute top-3 right-3 text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
                                <h4 class="text-sm font-bold text-white mb-4">Upload New Category Resume</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <input type="file" x-ref="resumeUpload" class="text-sm text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-indigo-500" accept=".pdf">
                                    <input type="text" x-model="newCategory" placeholder="Category (e.g. Frontend Dev)" class="bg-darkbg border border-slate-700 rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-primary">
                                </div>
                                <div class="mt-4 flex items-center justify-between">
                                    <div x-show="uploadError" class="text-xs text-red-400 font-bold" x-text="uploadError" style="display:none;"></div>
                                    <div x-show="uploading" class="text-xs text-primary font-bold flex items-center" style="display:none;"><i data-lucide="loader-2" class="animate-spin mr-2 w-4 h-4"></i> parsing...</div>
                                    <button @click="uploadResume" :disabled="uploading" class="ml-auto px-5 py-2 bg-gradient-to-r from-primary to-secondary text-white text-sm font-bold rounded-xl hover:shadow-lg disabled:opacity-50 transition-all">Upload & Save</button>
                                </div>
                            </div>
                        </div>

                        <!-- Generalize Resume Modal (fixed overlay) -->
                        <div x-show="showGeneralizeModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showGeneralizeModal = false"></div>
                            <div class="relative bg-[#0f172a] border border-slate-700 rounded-3xl shadow-2xl w-full max-w-xl p-8 z-10">
                                <button @click="showGeneralizeModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>

                                <!-- Step indicator -->
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black" :class="generalizeStep >= 1 ? 'bg-primary text-white' : 'bg-slate-700 text-slate-400'">1</div>
                                        <span class="text-xs font-bold" :class="generalizeStep >= 1 ? 'text-white' : 'text-slate-500'">Select Base Resume</span>
                                    </div>
                                    <div class="flex-1 h-px bg-slate-700"></div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black" :class="generalizeStep >= 2 ? 'bg-primary text-white' : 'bg-slate-700 text-slate-400'">2</div>
                                        <span class="text-xs font-bold" :class="generalizeStep >= 2 ? 'text-white' : 'text-slate-500'">Direction &amp; Label</span>
                                    </div>
                                </div>

                                <!-- STEP 1 -->
                                <div x-show="generalizeStep === 1">
                                    <h3 class="text-xl font-extrabold text-white mb-1">Generalize a Resume</h3>
                                    <p class="text-sm text-slate-400 mb-5">Choose which saved resume to use as the base for the generalized version.</p>
                                    <div class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar pr-1">
                                        <template x-for="res in resumes" :key="res.id">
                                            <div @click="generalizeBaseId = res.id" class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all" :class="generalizeBaseId === res.id ? 'border-primary bg-primary/10' : 'border-slate-700 bg-slate-800/40 hover:border-slate-500'">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="generalizeBaseId === res.id ? 'bg-primary/30 text-primary' : 'bg-slate-700 text-slate-400'">
                                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-bold text-white truncate" x-text="res.filename"></p>
                                                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-slate-700 text-slate-300" x-text="res.category"></span>
                                                </div>
                                                <i x-show="generalizeBaseId === res.id" data-lucide="check-circle-2" class="w-5 h-5 text-primary shrink-0"></i>
                                            </div>
                                        </template>
                                    </div>
                                    <button @click="generalizeStep = 2" :disabled="!generalizeBaseId" class="mt-5 w-full py-3 bg-gradient-to-r from-primary to-secondary text-white font-bold rounded-xl disabled:opacity-40 transition-all flex items-center justify-center gap-2">
                                        Next <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </button>
                                </div>

                                <!-- STEP 2 -->
                                <div x-show="generalizeStep === 2">
                                    <button @click="generalizeStep = 1" class="flex items-center text-xs text-slate-400 hover:text-white mb-4 transition"><i data-lucide="arrow-left" class="w-3.5 h-3.5 mr-1"></i> Back</button>
                                    <h3 class="text-xl font-extrabold text-white mb-1">Direction &amp; Label</h3>
                                    <p class="text-sm text-slate-400 mb-5">Describe the role type or paste an industry-standard job posting. The AI will rewrite your resume to match.</p>

                                    <div class="mb-4">
                                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Label / Category Tag</label>
                                        <input type="text" x-model="generalizeLabel" placeholder="e.g. Sysadmin, Frontend Dev, Data Engineer" class="w-full bg-darkbg border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-primary transition">
                                    </div>

                                    <div class="mb-5">
                                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Direction / Reference Job Posting</label>
                                        <textarea x-model="generalizeDirection" rows="6" placeholder="e.g. \"Focus on Linux sysadmin, Bash scripting, Nagios, and infrastructure management\"&#10;&#10;— OR — paste an industry-standard job posting here." class="w-full bg-darkbg border border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-300 outline-none focus:border-primary transition resize-none custom-scrollbar"></textarea>
                                    </div>

                                    <div x-show="generalizeError" x-text="generalizeError" class="mb-3 text-xs text-red-400 font-bold p-3 bg-red-500/10 rounded-xl border border-red-500/20" style="display:none;"></div>

                                    <button @click="runGeneralizeResume()" :disabled="generalizeBusy || !generalizeDirection.trim()" class="w-full py-3 bg-gradient-to-r from-primary to-secondary text-white font-bold rounded-xl disabled:opacity-40 transition-all flex items-center justify-center gap-2">
                                        <i x-show="generalizeBusy" data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                        <i x-show="!generalizeBusy" data-lucide="wand-2" class="w-4 h-4"></i>
                                        <span x-text="generalizeBusy ? 'Generating...' : 'Generate Generalized Resume'"></span>
                                    </button>
                                </div>

                            </div>
                        </div>

                        <!-- Step 2: Job Description Input -->
                        <div class="bg-card/80 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-6 sm:p-8 shadow-2xl">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Step 2: Target Job Description</label>
                            
                            <textarea x-model="jobDescription" placeholder="Paste the job description here..." class="w-full bg-darkbg border border-slate-700 rounded-xl p-5 text-sm text-slate-300 outline-none focus:border-primary transition-all placeholder-slate-600 min-h-[200px] shadow-inner custom-scrollbar resize-y"></textarea>
                            
                            <!-- Notice activeResumeId handles disabling automatically if no resumes -->
                            <div class="mt-8">
                                <button @click="runOptimization" :disabled="analyzing || optimizing || resumes.length === 0 || !activeResumeId" class="w-full py-4 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-primary to-secondary shadow-[0_0_20px_rgba(99,102,241,0.3)] transition-all hover:-translate-y-1 active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center">
                                    <span x-show="!optimizing" class="flex items-center">
                                        <i data-lucide="sparkles" class="mr-2 h-4 w-4"></i> Generate Cover Letter & Resume
                                    </span>
                                    <span x-show="optimizing" class="flex items-center" style="display:none;">
                                        <i data-lucide="loader-2" class="animate-spin mr-3 h-4 w-4 text-white"></i> Generating...
                                    </span>
                                </button>
                            </div>

                            <!-- Alert Banner for Missing Skills Modal -->
                            <div x-show="missingSkillsAlert.length > 0" x-transition class="mt-6 p-5 bg-yellow-500/10 border border-yellow-500/30 rounded-2xl relative" style="display: none;">
                                <div class="flex items-start">
                                    <i data-lucide="alert-triangle" class="w-6 h-6 text-yellow-500 mr-4 mt-0.5 shrink-0"></i>
                                    <div>
                                        <h4 class="text-sm font-bold text-yellow-500 mb-1">Honesty Check: Missing Skills Detected</h4>
                                        <p class="text-xs text-slate-300 font-medium leading-relaxed mb-3">The job requires the following skills which are not present in your current active resume. We did not inject them to prevent hallucination.</p>
                                        <div class="flex flex-wrap gap-2 mb-4">
                                            <template x-for="skill in missingSkillsAlert">
                                                <span class="px-2 py-0.5 bg-yellow-500/20 text-yellow-400 rounded-md text-[10px] font-bold border border-yellow-500/20 uppercase" x-text="skill"></span>
                                            </template>
                                        </div>
                                        <div class="flex space-x-3">
                                            <button @click="finalizeOptimization(false)" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg border border-slate-700 hover:bg-slate-700 transition">Cancel & Edit PDF Manually</button>
                                            <button @click="finalizeOptimization(true)" class="px-4 py-2 bg-yellow-500/20 text-yellow-500 text-xs font-bold rounded-lg border border-yellow-500/50 hover:bg-yellow-500/30 transition">Proceed Anyway</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div x-show="analyzeError" x-text="analyzeError" class="mt-4 p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl text-sm font-bold" style="display:none;"></div>
                        </div>

                        <!-- Live Results: Optimization Block -->
                        <div x-show="finalOptimizedText" x-transition class="bg-gradient-to-br from-[#1e293b] to-slate-900 border border-primary/50 border-t-4 border-t-primary rounded-3xl p-8 shadow-2xl relative overflow-hidden" style="display: none;">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/10 rounded-full blur-[80px] pointer-events-none"></div>
                            
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 pb-6 border-b border-slate-700/50 relative z-10">
                                <div>
                                    <h2 class="text-2xl font-extrabold text-white tracking-tight mb-1">Optimized Resume Output</h2>
                                    <p class="text-xs font-medium text-slate-400">Tailored to the Job Description</p>
                                </div>
                                <div class="mt-4 sm:mt-0 flex items-center gap-3 flex-wrap">
                                    <!-- Match Score Badge -->
                                    <div class="px-4 py-2 rounded-xl bg-darkbg border border-slate-700 flex items-center shadow-inner">
                                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest mr-2">Match</span>
                                        <span class="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary" x-text="(finalResultObj?.score ?? '—') + '%'"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="px-3 py-2 rounded-xl bg-primary/20 border border-primary/30 flex items-center shadow-inner">
                                            <i data-lucide="check-circle-2" class="w-4 h-4 text-primary mr-2"></i>
                                            <span class="text-xs font-bold text-primary uppercase tracking-widest">Ready</span>
                                        </div>
                                        <button @click="downloadOptTxt" class="text-xs font-bold text-white uppercase tracking-widest hover:text-primary transition flex items-center bg-slate-800 px-3 py-2 rounded-lg border border-slate-600">
                                            <i data-lucide="download" class="w-4 h-4 mr-2"></i> TXT
                                        </button>
                                        <button @click="downloadPDF(finalOptimizedText, 'resume')" class="text-xs font-bold text-white uppercase tracking-widest hover:text-primary transition flex items-center bg-slate-800 px-3 py-2 rounded-lg border border-slate-600">
                                            <i data-lucide="file-text" class="w-4 h-4 mr-2"></i> PDF
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div x-show="finalResultObj?.perfect_matches?.length > 0" class="mb-6 relative z-10">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center"><i data-lucide="check-square" class="w-4 h-4 mr-2 text-green-400"></i>Perfect Matches</h4>
                                <div class="flex flex-wrap gap-2 p-4 bg-green-500/5 rounded-2xl border border-green-500/20">
                                    <template x-for="match in finalResultObj?.perfect_matches">
                                        <span class="px-2.5 py-1 bg-green-500/20 text-green-400 rounded-lg text-xs font-bold border border-green-500/20" x-text="match"></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Missing Skills / Honesty Check -->
                            <div x-show="finalResultObj?.keywords?.length > 0" class="mb-6 relative z-10">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center"><i data-lucide="alert-triangle" class="w-4 h-4 mr-2 text-yellow-400"></i>Honesty Check — Missing Skills</h4>
                                <div class="flex flex-wrap gap-2 p-4 bg-yellow-500/5 rounded-2xl border border-yellow-500/20">
                                    <template x-for="skill in finalResultObj?.keywords">
                                        <span class="px-2.5 py-1 bg-yellow-500/20 text-yellow-400 rounded-lg text-xs font-bold border border-yellow-500/20 uppercase" x-text="skill"></span>
                                    </template>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-2">These skills were required by the job but absent from your resume. They were <strong class="text-slate-400">not added</strong> to prevent hallucination.</p>
                            </div>
                            
                            <div class="mb-8 relative z-10">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center"><i data-lucide="file-signature" class="w-4 h-4 mr-2 text-primary"></i>AI Rewritten Bullets</h4>
                                <div class="p-6 bg-slate-800/40 rounded-2xl border border-white/5 backdrop-blur-sm relative group">
                                    <button @click="copyText(finalOptimizedText, 'optCopied')" title="Copy to Clipboard" class="absolute top-4 right-4 p-2 bg-slate-700 text-white rounded-lg transition-colors hover:bg-slate-600 shadow-md">
                                        <i data-lucide="copy" class="w-4 h-4"></i>
                                    </button>
                                    <button @click="downloadPDF(finalOptimizedText, 'resume')" title="Download PDF" class="absolute top-4 right-14 p-2 bg-slate-700 text-white rounded-lg transition-colors hover:bg-slate-600 shadow-md">
                                        <i data-lucide="file-text" class="w-4 h-4"></i>
                                    </button>
                                    <div class="text-sm text-slate-300 leading-relaxed custom-scrollbar prose-sm" x-html="renderMarkdown(finalOptimizedText)"></div>
                                </div>
                                <div x-show="optCopied" x-transition class="text-xs text-green-400 font-bold mt-2 text-right w-full" style="display:none;">Copied to clipboard!</div>
                            </div>

                            <div x-show="finalResultObj?.cover_letter" class="relative z-10">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center"><i data-lucide="mail" class="w-4 h-4 mr-2 text-secondary"></i>Optimized Cover Letter</h4>
                                <div class="p-6 bg-slate-800/40 rounded-2xl border border-white/5 backdrop-blur-sm relative group">
                                    <button @click="copyText(finalResultObj?.cover_letter, 'optCvlCopied')" title="Copy to Clipboard" class="absolute top-4 right-4 p-2 bg-slate-700 text-white rounded-lg transition-colors hover:bg-slate-600 shadow-md">
                                        <i data-lucide="copy" class="w-4 h-4"></i>
                                    </button>
                                    <button @click="downloadPDF(finalResultObj?.cover_letter, 'cover_letter')" title="Download PDF" class="absolute top-4 right-14 p-2 bg-slate-700 text-white rounded-lg transition-colors hover:bg-slate-600 shadow-md">
                                        <i data-lucide="file-text" class="w-4 h-4"></i>
                                    </button>
                                    <div class="text-sm text-slate-300 leading-relaxed custom-scrollbar prose-sm" x-html="renderMarkdown(finalResultObj?.cover_letter)"></div>
                                </div>
                                <div x-show="optCvlCopied" x-transition class="text-xs text-green-400 font-bold mt-2 text-right w-full" style="display:none;">Copied to clipboard!</div>
                            </div>

                            <!-- Combined PDF + Ask AI + Deep Analysis row -->
                            <div x-show="finalResultObj?.cover_letter && finalOptimizedText" x-transition class="relative z-10 mt-6 flex flex-col sm:flex-row gap-3" style="display:none;">
                                <button @click="downloadCombinedPDF()" class="flex-1 flex items-center justify-center gap-3 px-6 py-4 bg-gradient-to-r from-primary to-secondary hover:opacity-90 text-white font-extrabold tracking-wide rounded-2xl shadow-xl transition-all hover:shadow-primary/40 hover:scale-[1.01]">
                                    <i data-lucide="layers" class="w-5 h-5"></i>
                                    Download Combined PDF
                                    <span class="text-xs font-normal opacity-80 ml-1">(Cover Letter + Resume)</span>
                                </button>
                                <button @click="openAskAi()" class="flex items-center justify-center gap-2 px-6 py-4 bg-slate-800 hover:bg-slate-700 border border-slate-600 hover:border-primary/50 text-white font-extrabold tracking-wide rounded-2xl shadow-xl transition-all">
                                    <i data-lucide="message-circle" class="w-5 h-5 text-primary"></i>
                                    Ask AI
                                </button>
                                <button @click="runDeepAnalysis()" :disabled="analysisBusy" class="flex items-center justify-center gap-2 px-6 py-4 bg-slate-800 hover:bg-slate-700 border border-slate-600 hover:border-yellow-500/50 text-white font-extrabold tracking-wide rounded-2xl shadow-xl transition-all disabled:opacity-50">
                                    <i x-show="!analysisBusy" data-lucide="telescope" class="w-5 h-5 text-yellow-400"></i>
                                    <i x-show="analysisBusy" data-lucide="loader-2" class="w-5 h-5 text-yellow-400 animate-spin"></i>
                                    <span x-text="analysisBusy ? 'Analyzing...' : 'Deep Analysis'"></span>
                                </button>
                            </div>

                        </div>
                        <!-- /Optimization Output Block -->







                        <!-- Live Results: Coverage & Cover Letter Block -->
                        <div x-show="latestResult" x-transition class="bg-gradient-to-br from-[#1e293b] to-slate-900 border border-slate-700/80 rounded-3xl p-8 shadow-2xl relative overflow-hidden" style="display: none;">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-secondary/10 rounded-full blur-[80px] pointer-events-none"></div>
                            
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 pb-6 border-b border-slate-700/50">
                                <div>
                                    <h2 class="text-2xl font-extrabold text-white tracking-tight mb-1">Match Analytics</h2>
                                    <p class="text-xs font-medium text-slate-400">Profile Evaluated: <span class="text-white" x-text="latestResult?.resume_name"></span> (<span x-text="latestResult?.resume_category"></span>)</p>
                                </div>
                                <div class="mt-4 sm:mt-0 px-6 py-3 rounded-2xl bg-darkbg border border-slate-700 flex items-center shadow-inner">
                                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest mr-3">Fit Score</span>
                                    <span class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary" x-text="latestResult?.score + '%'"></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div class="bg-darkbg/50 p-5 rounded-2xl border border-slate-700/50">
                                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center"><i data-lucide="tag" class="w-4 h-4 mr-2"></i>Missing Keywords</h4>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-if="latestResult?.keywords?.length === 0">
                                            <span class="text-sm text-slate-400">Wow! No major keywords missing.</span>
                                        </template>
                                        <template x-for="kw in latestResult?.keywords">
                                            <span class="px-2.5 py-1 bg-red-500/20 text-red-300 rounded-lg text-xs font-bold border border-red-500/20" x-text="kw"></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="bg-darkbg/50 p-5 rounded-2xl border border-slate-700/50">
                                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center"><i data-lucide="compass" class="w-4 h-4 mr-2"></i>Playbook</h4>
                                    <p class="text-sm text-slate-300 leading-relaxed font-medium" x-text="latestResult?.strategy"></p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: History -->
                    <div class="lg:col-span-1">
                        <div class="bg-card/50 backdrop-blur-md border border-slate-700/50 rounded-3xl p-6 shadow-xl sticky top-8">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center justify-between mb-6 pb-4 border-b border-slate-700/50">
                                <span class="flex items-center"><i data-lucide="history" class="w-4 h-4 mr-2"></i> Application History</span>
                                <button @click="currentView='my_jobs'" class="text-[10px] text-primary hover:underline font-bold">View All →</button>
                            </h3>
                            <div class="space-y-4">
                                <template x-if="history.length === 0"><div class="text-sm text-slate-500 p-4 border border-dashed border-slate-700 rounded-2xl text-center">No apps tracked yet.</div></template>
                                <template x-for="item in history" :key="item.id">
                                    <div class="relative group/hist">
                                        <div @click="loadHistory(item)" class="group p-4 rounded-2xl bg-slate-800/30 border border-slate-700/30 hover:bg-slate-800 hover:border-primary/50 transition-all cursor-pointer">
                                            <div class="flex justify-between items-start mb-2">
                                                <span class="font-bold text-white text-sm truncate pr-2 max-w-[120px]" x-text="item.vibe || item.job_title"></span>
                                                <span class="text-xs font-black px-2 py-1 rounded-lg bg-darkbg border border-slate-700 text-primary shadow-inner" x-text="item.score + '%'"></span>
                                            </div>
                                            <div class="text-[10px] text-slate-500" x-text="item.resume_category"></div>
                                        </div>
                                        <button @click="deleteHistory(item.id, $event)" title="Remove" class="absolute top-2 right-2 p-1.5 hidden group-hover/hist:flex items-center justify-center bg-red-500/20 hover:bg-red-500/40 text-red-400 rounded-lg transition-colors">
                                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- FIND JOBS VIEW -->
            <div x-show="currentView === 'find_jobs'" class="max-w-6xl mx-auto px-4 sm:px-6 md:px-10 py-8 md:py-12" style="display:none;" x-cloak>
                <div class="mb-10">
                    <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-3 tracking-tight drop-shadow-md">Find Openings</h1>
                    <p class="text-slate-400 text-sm md:text-base font-medium max-w-2xl">Search real-time listings aggregated by Adzuna, dynamically localized to your Zip Code.</p>
                </div>

                <!-- Feature Status Alert -->
                <div class="mb-8 bg-amber-500/10 border border-amber-500/30 rounded-2xl p-6 flex items-start gap-4 shadow-xl">
                    <div class="p-3 bg-amber-500/20 rounded-xl shrink-0">
                        <i data-lucide="construction" class="w-6 h-6 text-amber-500"></i>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-base font-bold text-amber-500 mb-1">Feature Under Construction</h4>
                        <p class="text-sm text-slate-300 font-medium leading-relaxed">
                            Please note that the <strong class="text-white">Find Openings</strong> search feature is currently <strong class="text-amber-500">not fully implemented</strong> and may not return accurate or real-time results. 
                            We are working hard to integrate the Adzuna API for dynamic job matching.
                        </p>
                    </div>
                </div>

                <!-- Live Search Filter Bar -->
                <div class="bg-card/80 backdrop-blur-xl border border-slate-700/50 rounded-2xl p-4 sm:p-6 shadow-xl mb-8">
                    <div class="flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="h-5 w-5 text-slate-500"></i>
                            </div>
                            <input type="text" x-model.debounce.500ms="jobFilters.query" placeholder="Job title, keywords, or company..." class="block w-full pl-10 pr-3 py-3 border border-slate-700 rounded-xl leading-5 bg-darkbg text-slate-300 placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm transition-colors">
                        </div>
                        <div class="w-full md:w-64 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="map-pin" class="h-5 w-5 text-slate-500"></i>
                            </div>
                            <input type="text" x-model.debounce.500ms="jobFilters.location" placeholder="City or Zip (Blank = Home)" class="block w-full pl-10 pr-3 py-3 border border-slate-700 rounded-xl leading-5 bg-darkbg text-slate-300 placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm transition-colors">
                        </div>
                    </div>
                    
                    <div class="mt-4 flex flex-wrap items-center gap-6 pt-4 border-t border-slate-700/50">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center">
                            <i data-lucide="filter" class="w-3 h-3 mr-1"></i> Mode
                        </span>
                        
                        <label class="flex items-center space-x-2 cursor-pointer group">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" x-model="jobFilters.remote" class="peer sr-only">
                                <div class="w-5 h-5 border-2 border-slate-600 rounded bg-darkbg peer-checked:bg-primary peer-checked:border-primary transition-colors"></div>
                                <i data-lucide="check" class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                            </div>
                            <span class="text-sm font-medium text-slate-300 group-hover:text-white transition-colors">Remote</span>
                        </label>

                        <label class="flex items-center space-x-2 cursor-pointer group">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" x-model="jobFilters.hybrid" class="peer sr-only">
                                <div class="w-5 h-5 border-2 border-slate-600 rounded bg-darkbg peer-checked:bg-primary peer-checked:border-primary transition-colors"></div>
                                <i data-lucide="check" class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 pointer-events-none"></i>
                            </div>
                            <span class="text-sm font-medium text-slate-300 group-hover:text-white transition-colors">Hybrid</span>
                        </label>
                    </div>
                </div>

                <!-- Job Loading State -->
                <div x-show="jobsLoading" class="flex flex-col items-center justify-center py-20" style="display:none;">
                    <i data-lucide="loader-2" class="w-12 h-12 text-primary animate-spin mb-4"></i>
                    <p class="text-slate-400 font-medium">Scanning Adzuna network...</p>
                </div>

                <!-- Job Results List -->
                <div x-show="!jobsLoading" class="space-y-4">
                    <template x-if="jobs.length === 0">
                        <div class="bg-card border border-slate-700 rounded-2xl p-12 text-center text-slate-400">
                            <i data-lucide="ghost" class="w-12 h-12 mx-auto mb-4 opacity-50"></i>
                            <p>No jobs found matching these filters.</p>
                        </div>
                    </template>
                    
                    <template x-for="job in jobs" :key="job.id">
                        <div class="bg-card/50 hover:bg-card border border-slate-700 hover:border-primary/50 rounded-2xl p-6 transition-all shadow-md group">
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold text-white group-hover:text-primary transition-colors mb-1" x-text="job.title"></h3>
                                    <div class="flex items-center space-x-4 mb-3">
                                        <span class="text-sm font-bold text-slate-300 flex items-center"><i data-lucide="building-2" class="w-4 h-4 mr-1 text-slate-500"></i> <span x-text="job.company.display_name"></span></span>
                                        <span class="text-sm font-bold text-slate-300 flex items-center"><i data-lucide="map-pin" class="w-4 h-4 mr-1 text-slate-500"></i> <span x-text="job.location.display_name"></span></span>
                                    </div>
                                    <p class="text-sm text-slate-400 leading-relaxed line-clamp-2" x-text="job.description"></p>
                                </div>
                                <div class="flex md:flex-col gap-3 shrink-0 items-end">
                                    <a :href="job.redirect_url" target="_blank" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-600 rounded-xl text-white text-sm font-bold transition flex items-center w-full justify-center">
                                        View Post <i data-lucide="external-link" class="w-4 h-4 ml-2"></i>
                                    </a>
                                    <button @click="sendToVibeCheck(job.description)" class="px-4 py-2 bg-gradient-to-r from-primary to-secondary hover:shadow-lg rounded-xl text-white text-sm font-bold transition flex items-center w-full justify-center">
                                        Vibe Check <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <!-- MY JOBS VIEW -->
            <div x-show="currentView === 'my_jobs'" class="max-w-6xl mx-auto px-4 sm:px-6 md:px-10 py-8 md:py-12" style="display:none;" x-cloak>
                <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-3 tracking-tight drop-shadow-md">My Jobs History</h1>
                        <p class="text-slate-400 text-sm md:text-base font-medium max-w-2xl">A historical record of your AI-optimized resumes and generated cover letters.</p>
                    </div>
                    <button @click="fetchHistory()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-sm font-bold shadow-lg transition-all flex items-center border border-slate-700 h-fit">
                        <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Refresh Data
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <template x-if="history.length === 0">
                        <div class="col-span-full bg-slate-800/30 border border-slate-700/50 rounded-2xl p-10 text-center flex flex-col items-center">
                            <div class="w-16 h-16 bg-slate-700/50 rounded-full flex items-center justify-center mb-4">
                                <i data-lucide="folder-open" class="w-8 h-8 text-slate-400"></i>
                            </div>
                            <h3 class="text-lg font-bold text-white mb-2">No history yet</h3>
                            <p class="text-slate-400 text-sm max-w-sm">Generate your first optimized application using the Compile AI App tab to see it here.</p>
                        </div>
                    </template>
                    
                    <template x-for="item in history" :key="item.id">
                        <div class="bg-card/80 backdrop-blur-xl border border-slate-700/50 rounded-2xl overflow-hidden shadow-xl hover:shadow-2xl hover:border-slate-500/50 transition-all group flex flex-col h-full">
                            <div class="p-6 flex-grow">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-slate-800 px-3 py-1 rounded-full border border-slate-700" x-text="new Date(item.timestamp * 1000).toLocaleDateString()"></div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black px-2 py-1 rounded-lg bg-darkbg border border-slate-700 text-primary shadow-inner" x-text="item.score + '% Match'"></span>
                                        <button @click="deleteHistory(item.id, $event)" title="Delete" class="p-1.5 bg-red-500/10 hover:bg-red-500/30 text-red-400 rounded-lg transition-colors">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </div>
                                <div x-show="!item._editing">
                                    <h3 class="text-lg font-extrabold text-white leading-snug mb-1 line-clamp-2" x-text="item.job_title && item.job_title !== '(Untitled)' ? item.job_title : (item.vibe || 'Target Role Analysis')"></h3>
                                    <p class="text-sm font-medium text-slate-400 mb-2">at <span x-text="item.job_company && item.job_company !== '(Unknown Company)' ? item.job_company : '(Unknown Company)'"></span></p>
                                    <a x-show="item.job_url" :href="item.job_url" target="_blank" class="text-xs text-primary hover:underline mb-2 inline-block">View Job Post</a>
                                    <div class="flex items-center gap-3 mb-2">
                                        <button @click="item._editing = true; item._editTitle = item.job_title; item._editCompany = item.job_company; item._editUrl = item.job_url;" class="text-xs text-slate-500 hover:text-white flex items-center"><i data-lucide="edit-3" class="w-3 h-3 mr-1"></i>Edit Profile</button>
                                        <button @click="openJobEditor(item)" class="text-xs text-orange-400/80 hover:text-orange-400 flex items-center"><i data-lucide="file-text" class="w-3 h-3 mr-1"></i>View/Edit Job Desc</button>
                                    </div>
                                </div>
                                <div x-show="item._editing" class="space-y-2 mb-4">
                                    <input type="text" x-model="item._editTitle" placeholder="Job Title" class="w-full bg-darkbg border border-slate-700 rounded-lg px-2 py-1 text-xs text-white">
                                    <input type="text" x-model="item._editCompany" placeholder="Company Name" class="w-full bg-darkbg border border-slate-700 rounded-lg px-2 py-1 text-xs text-white">
                                    <input type="text" x-model="item._editUrl" placeholder="Job URL" class="w-full bg-darkbg border border-slate-700 rounded-lg px-2 py-1 text-xs text-white">
                                    <div class="flex space-x-2">
                                        <button @click="saveJobDetails(item)" class="bg-primary hover:bg-indigo-500 text-white px-2 py-1 rounded text-xs">Save</button>
                                        <button @click="item._editing = false" class="bg-slate-700 hover:bg-slate-600 text-white px-2 py-1 rounded text-xs">Cancel</button>
                                        <span x-show="item._saving" class="text-[10px] text-slate-400 flex items-center ml-2"><i data-lucide="loader-2" class="w-3 h-3 animate-spin mr-1"></i> Saving...</span>
                                    </div>
                                </div>
                                <p class="text-xs font-medium text-slate-500 mb-6" x-text="'Target Resume: ' + item.resume_name"></p>
                                
                                <div class="space-y-4">
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="kw in (item.perfect_matches?.slice(0, 3) || [])">
                                            <span class="px-2 py-0.5 bg-green-500/10 text-green-400 rounded-md text-[10px] font-bold border border-green-500/20" x-text="kw"></span>
                                        </template>
                                        <span x-show="item.perfect_matches?.length > 3" class="px-2 py-0.5 text-slate-400 text-[10px] font-bold" x-text="'+' + (item.perfect_matches.length - 3)"></span>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="kw in (item.keywords?.slice(0, 3) || [])">
                                            <span class="px-2 py-0.5 bg-red-500/10 text-red-400 rounded-md text-[10px] font-bold border border-red-500/20" x-text="kw"></span>
                                        </template>
                                        <span x-show="item.keywords?.length > 3" class="px-2 py-0.5 text-slate-400 text-[10px] font-bold" x-text="'+' + (item.keywords.length - 3)"></span>
                                    </div>
                                </div>
                                
                                <div class="mt-6 pt-4 border-t border-slate-700/50">
                                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center"><i data-lucide="bookmark" class="w-4 h-4 mr-2"></i>Job Notes & Status</h4>
                                    
                                    <!-- Note Feed -->
                                    <div class="space-y-3 mb-4 max-h-32 overflow-y-auto custom-scrollbar pr-2">
                                        <template x-for="note in (item.notes || [])">
                                            <div class="flex items-start bg-darkbg/50 p-2.5 rounded border border-slate-700/30">
                                                <div class="flex-shrink-0 mr-3 mt-0.5">
                                                    <span class="inline-block w-2h-2 rounded-full" :class="{
                                                        'bg-primary': note.type === 'System',
                                                        'bg-blue-400': note.type === 'Interviewing',
                                                        'bg-purple-400': note.type === 'Applied',
                                                        'bg-red-400': note.type === 'Rejected',
                                                        'bg-green-400': note.type === 'Offer',
                                                        'bg-slate-400': note.type === 'Custom'
                                                    }"></span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-[10px] text-slate-500 mb-0.5">
                                                        <span class="font-bold border bg-slate-800 border-slate-700 px-1 rounded mr-1" x-text="note.type"></span>
                                                        <span x-text="new Date(note.timestamp * 1000).toLocaleString(undefined, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'})"></span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-300" x-text="note.text"></p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <!-- Add Note Interface -->
                                    <div class="flex space-x-2">
                                        <select x-model="item._newNoteStatus" class="w-1/3 bg-darkbg border border-slate-700 rounded-lg px-2 py-1.5 text-[11px] text-slate-300 outline-none focus:border-primary">
                                            <option value="Custom">Note</option>
                                            <option value="Applied">Applied</option>
                                            <option value="Interviewing">Interviewing</option>
                                            <option value="Offer">Offer!</option>
                                            <option value="Rejected">Rejected</option>
                                        </select>
                                        <input type="text" x-model="item._newNoteText" @keydown.enter="addJobNote(item)" placeholder="Add update..." class="flex-1 bg-darkbg border border-slate-700 rounded-lg px-3 py-1.5 text-[11px] text-slate-300 outline-none focus:border-primary placeholder-slate-600">
                                        <button @click="addJobNote(item)" class="bg-slate-700 hover:bg-primary text-white p-1.5 rounded-lg transition-colors border border-slate-600"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 bg-slate-800/50 border-t border-slate-700/50 space-y-3">
                                <div class="flex space-x-2">
                                    <button @click="downloadPDF(item.optimized_resume_text, 'resume')" class="flex-1 text-[10px] font-bold text-slate-300 uppercase tracking-widest hover:text-white hover:bg-slate-700 transition flex justify-center items-center bg-darkbg py-2 rounded-lg border border-slate-700 shadow-inner">
                                        <i data-lucide="file-text" class="w-3.5 h-3.5 mr-1.5"></i> Resume
                                    </button>
                                    <button @click="item.cover_letter ? downloadPDF(item.cover_letter, 'cover_letter') : alert('Cover letter not generated.')" :class="item.cover_letter ? 'hover:text-white hover:bg-slate-700 text-slate-300 border-slate-700' : 'text-slate-600 border-slate-800 cursor-not-allowed'" class="flex-1 text-[10px] font-bold uppercase tracking-widest transition flex justify-center items-center bg-darkbg py-2 rounded-lg border shadow-inner">
                                        <i data-lucide="mail" class="w-3.5 h-3.5 mr-1.5"></i> Cover Ltr
                                    </button>
                                </div>
                                <div class="flex space-x-2">
                                    <button @click="loadHistoryForAction(item, 'analysis')" class="flex-1 text-[10px] font-bold text-indigo-400 uppercase tracking-widest hover:text-white hover:bg-indigo-500/20 transition flex justify-center items-center bg-indigo-500/10 py-2 rounded-lg border border-indigo-500/30">
                                        <i data-lucide="bar-chart-3" class="w-3.5 h-3.5 mr-1.5"></i> Analysis
                                    </button>
                                    <button @click="loadHistoryForAction(item, 'ask_ai')" class="flex-1 text-[10px] font-bold text-secondary uppercase tracking-widest hover:text-white hover:bg-secondary/20 transition flex justify-center items-center bg-secondary/10 py-2 rounded-lg border border-secondary/30">
                                        <i data-lucide="message-square" class="w-3.5 h-3.5 mr-1.5"></i> Ask AI
                                    </button>
                                </div>
                                <button @click="alert('Mock Interview feature coming soon!')" class="w-full text-[10px] font-bold text-amber-400 uppercase tracking-widest hover:text-white hover:bg-amber-500/20 transition flex justify-center items-center bg-amber-500/10 py-2.5 rounded-lg border border-amber-500/30">
                                    <i data-lucide="mic" class="w-3.5 h-3.5 mr-1.5"></i> Mock Interview (Coming Soon)
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>


            <!-- ═══════════════════════════════ GLOBAL MODALS (Accessible from all views) ═══════════════════════════════ -->

            <!-- Application Wizard Modal -->
            <div x-show="showWizard" x-transition.opacity class="fixed inset-0 z-[60] flex flex-col" style="display:none;" x-cloak>
                <div class="absolute inset-0 bg-[#080e1e]/98 backdrop-blur-2xl"></div>
                <div class="relative flex flex-col w-full h-full z-10 overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-8 py-4 border-b border-slate-700/50 bg-slate-900/50 shrink-0">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-primary to-secondary flex items-center justify-center shadow-lg">
                                <i data-lucide="wand-2" class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-white tracking-tight">Application Wizard</h2>
                                <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest">Step <span x-text="wizardStep"></span> of 3</p>
                            </div>
                        </div>
                        <!-- Stepper Nav -->
                        <div class="hidden md:flex items-center gap-8">
                            <div :class="wizardStep >= 1 ? 'wizard-step-active' : 'text-slate-500'" class="flex items-center gap-2 pb-1 transition-all">
                                <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center text-[10px] font-bold">1</span>
                                <span class="text-xs font-bold uppercase tracking-widest">Cover Letter</span>
                            </div>
                            <div class="w-8 h-px bg-slate-700"></div>
                            <div :class="wizardStep >= 2 ? 'wizard-step-active' : 'text-slate-500'" class="flex items-center gap-2 pb-1 transition-all">
                                <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center text-[10px] font-bold">2</span>
                                <span class="text-xs font-bold uppercase tracking-widest">Resume</span>
                            </div>
                            <div class="w-8 h-px bg-slate-700"></div>
                            <div :class="wizardStep >= 3 ? 'wizard-step-active' : 'text-slate-500'" class="flex items-center gap-2 pb-1 transition-all">
                                <span class="w-6 h-6 rounded-full border border-current flex items-center justify-center text-[10px] font-bold">3</span>
                                <span class="text-xs font-bold uppercase tracking-widest">Proof & Finish</span>
                            </div>
                        </div>
                        <button @click="if(confirm('Quit the wizard? All manual edits will be lost.')) showWizard = false" class="p-2 text-slate-500 hover:text-white transition">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>

                    <!-- Editor Toolbar -->
                    <div x-show="wizardStep < 3" class="flex items-center gap-1 px-4 py-2 border-b border-white/5 bg-slate-800/30 overflow-x-auto shrink-0 no-scrollbar">
                        <button @click="editorInstance.chain().focus().toggleHeading({ level: 1 }).run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Heading 1"><i data-lucide="heading-1" class="w-4 h-4"></i></button>
                        <button @click="editorInstance.chain().focus().toggleHeading({ level: 2 }).run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Heading 2"><i data-lucide="heading-2" class="w-4 h-4"></i></button>
                        <div class="w-px h-4 bg-slate-700 mx-1"></div>
                        <button @click="editorInstance.chain().focus().toggleBold().run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Bold"><i data-lucide="bold" class="w-4 h-4"></i></button>
                        <button @click="editorInstance.chain().focus().toggleItalic().run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Italic"><i data-lucide="italic" class="w-4 h-4"></i></button>
                        <button @click="editorInstance.chain().focus().toggleUnderline().run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Underline"><i data-lucide="underline" class="w-4 h-4"></i></button>
                        <div class="w-px h-4 bg-slate-700 mx-1"></div>
                        <button @click="editorInstance.chain().focus().toggleBulletList().run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Bullet List"><i data-lucide="list" class="w-4 h-4"></i></button>
                        <button @click="editorInstance.chain().focus().toggleOrderedList().run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Ordered List"><i data-lucide="list-ordered" class="w-4 h-4"></i></button>
                        <div class="w-px h-4 bg-slate-700 mx-1"></div>
                        <button @click="editorInstance.chain().focus().undo().run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Undo"><i data-lucide="undo" class="w-4 h-4"></i></button>
                        <button @click="editorInstance.chain().focus().redo().run()" class="p-2 rounded text-slate-300 hover:bg-white/10" title="Redo"><i data-lucide="redo" class="w-4 h-4"></i></button>
                    </div>

                    <!-- Content Area -->
                    <div class="flex-1 overflow-y-auto bg-[#0a1120] custom-scrollbar">
                        <div x-show="wizardStep < 3" class="editor-container py-12">
                            <div class="max-w-4xl mx-auto px-4">
                                <div class="mb-10 bg-indigo-500/10 border border-indigo-500/30 rounded-2xl p-5 flex items-start gap-4 shadow-xl">
                                    <div class="p-2 bg-indigo-500/20 rounded-lg shrink-0"><i data-lucide="info" class="w-5 h-5 text-indigo-400"></i></div>
                                    <div class="min-w-0">
                                        <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-1 leading-none">AI Formatting Note</h4>
                                        <p class="text-[11px] text-slate-300 font-medium leading-relaxed">Formatting artifacts are automatically stripped for your final PDF.</p>
                                    </div>
                                </div>
                                <div id="wizard-editor-mount" class="paper-page paper-editor shadow-2xl"></div>
                            </div>
                        </div>
                        <div x-show="wizardStep === 3" class="p-12">
                            <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                                <div class="space-y-4">
                                    <h4 class="text-xs font-black text-slate-500 uppercase tracking-[0.2em]">Cover Letter</h4>
                                    <div class="bg-white rounded-xl p-8 text-[#1a1a1a] shadow-xl min-h-[500px] overflow-y-auto prose-sm" x-html="editedCvlHTML"></div>
                                </div>
                                <div class="space-y-4">
                                    <h4 class="text-xs font-black text-slate-500 uppercase tracking-[0.2em]">Optimized Resume</h4>
                                    <div class="bg-white rounded-xl p-8 text-[#1a1a1a] shadow-xl min-h-[500px] overflow-y-auto prose-sm" x-html="editedResumeHTML"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-5 border-t border-slate-700/50 bg-slate-900/80 flex items-center justify-between shrink-0">
                        <button x-show="wizardStep > 1" @click="prevWizard()" class="px-6 py-3 bg-slate-800 text-white font-bold rounded-xl border border-slate-600 transition-all">Previous</button>
                        <div x-show="wizardStep === 1"></div>
                        <button x-show="wizardStep < 3" @click="nextWizard()" class="px-8 py-3 bg-primary text-white font-bold rounded-xl shadow-lg transition-all">Next</button>
                        <button x-show="wizardStep === 3" @click="finishWizard()" class="px-10 py-3 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-extrabold rounded-xl shadow-lg transition-all">Finish & Save</button>
                    </div>
                </div>
            </div>

            <!-- Tactical Analysis Modal -->
            <div x-show="showAnalysisModal" x-transition.opacity class="fixed inset-0 z-50 flex flex-col" style="display:none;" x-cloak>
                <div class="absolute inset-0 bg-[#080e1e]/95 backdrop-blur-xl"></div>
                <div class="relative flex flex-col w-full h-full z-10 overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50 bg-[#0a1120]/80 shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-yellow-500 to-orange-500 flex items-center justify-center shadow-lg"><i data-lucide="telescope" class="w-5 h-5 text-white"></i></div>
                            <h2 class="text-base font-extrabold text-white">Tactical Job Analysis</h2>
                        </div>
                        <button @click="showAnalysisModal = false" class="p-1.5 text-slate-400 hover:text-white transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <div class="flex gap-0 border-b border-slate-700/50 bg-[#0a1120]/60 shrink-0 overflow-x-auto">
                        <template x-for="tab in analysisTabs" :key="tab.key">
                            <button @click="analysisTab = tab.key; setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 50)"
                                :class="analysisTab === tab.key ? 'border-b-2 border-yellow-400 text-yellow-400 bg-yellow-500/5' : 'text-slate-400 hover:text-white'"
                                class="flex items-center gap-2 px-5 py-3.5 text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-all">
                                <i :data-lucide="tab.icon" class="w-4 h-4"></i>
                                <span x-text="tab.label"></span>
                            </button>
                        </template>
                    </div>
                    <div class="flex-1 overflow-y-auto custom-scrollbar">
                        <div class="max-w-4xl mx-auto px-6 py-8">
                            <!-- Error Display -->
                            <template x-if="analysisError">
                                <div class="bg-red-500/10 border border-red-500/30 rounded-2xl p-6 text-center mb-8">
                                    <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="alert-circle" class="w-6 h-6 text-red-500"></i>
                                    </div>
                                    <h3 class="text-white font-bold mb-2">Analysis Failed</h3>
                                    <p class="text-slate-400 text-sm mb-4" x-text="analysisError"></p>
                                    <button @click="runDeepAnalysis()" class="px-6 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-bold transition">Try Again</button>
                                </div>
                            </template>

                            <template x-for="tab in analysisTabs" :key="tab.key">
                                <div x-show="analysisTab === tab.key && !analysisError">
                                    <div class="prose-analysis" x-html="renderMarkdown(analysisData ? analysisData[tab.key] : '')"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progressive Generating Modal -->
            <div x-show="showGeneratingModal" x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none;" x-cloak>
                <div class="absolute inset-0 bg-[#060b14]/90 backdrop-blur-xl"></div>
                <div class="relative w-full max-w-md text-center z-10">
                    <div class="mb-8 relative inline-block">
                        <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-indigo-500 to-primary p-0.5 animate-pulse shadow-2xl">
                            <div class="w-full h-full bg-[#0d1526] rounded-[22px] flex items-center justify-center"><i data-lucide="loader-2" class="w-10 h-10 text-white animate-spin"></i></div>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-white mb-2 tracking-tight">Generating Tactical Intelligence</h3>
                    <div class="bg-slate-800/50 border border-slate-700/50 rounded-2xl p-6 shadow-inner">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest" x-text="genProgress < 100 ? 'Synthesizing...' : 'Finalizing!'"></span>
                            <span class="text-xs font-black text-primary" x-text="Math.round(genProgress) + '%'"></span>
                        </div>
                        <div class="h-2 w-full bg-darkbg rounded-full overflow-hidden border border-slate-700 shadow-inner">
                            <div class="h-full bg-gradient-to-r from-indigo-500 to-secondary transition-all duration-300 rounded-full" :style="'width: ' + genProgress + '%'"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Description Editor Modal -->
            <div x-show="showJobEditor" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="display:none;" x-cloak>
                <div class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="showJobEditor = false"></div>
                <div class="relative bg-card border border-slate-700 rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col z-10 overflow-hidden" style="height: 80vh;">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50 bg-slate-900/50">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center"><i data-lucide="file-edit" class="w-5 h-5 text-orange-400"></i></div>
                            <h3 class="text-base font-extrabold text-white">Edit Job Description</h3>
                        </div>
                        <button @click="showJobEditor = false" class="text-slate-400 hover:text-white transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-darkbg" id="job-description-editor-container">
                        <div id="job-description-editor"></div>
                    </div>
                    <div class="px-6 py-4 border-t border-slate-700/50 bg-slate-900/50 flex justify-end gap-3">
                        <button @click="showJobEditor = false" class="px-4 py-2 text-slate-400 hover:text-white text-sm font-bold transition">Cancel</button>
                        <button @click="saveJobDescription()" :disabled="jobEditorSaving" class="px-6 py-2 bg-primary text-white rounded-xl text-sm font-bold shadow-lg transition flex items-center disabled:opacity-50">
                            <span x-text="jobEditorSaving ? 'Saving...' : 'Save Job Details'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ask AI Chat Modal -->
            <div x-show="showAskAi" x-transition.opacity class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" style="display:none;" x-cloak>
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showAskAi = false"></div>
                <div class="relative bg-[#0d1526] border border-slate-700/80 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full sm:max-w-2xl flex flex-col z-10" style="height: 85vh; max-height: 700px;">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50 shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-lg"><i data-lucide="bot" class="w-5 h-5 text-white"></i></div>
                            <h3 class="text-base font-extrabold text-white">Ask AI</h3>
                        </div>
                        <button @click="showAskAi = false" class="p-1.5 text-slate-400 hover:text-white transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4 custom-scrollbar" x-ref="chatMessages">
                        <template x-if="activeTabId && askAiTabs.find(t => t.id === activeTabId)">
                            <div class="space-y-4">
                                <template x-for="(msg, idx) in askAiTabs.find(t => t.id === activeTabId).messages" :key="idx">
                                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                                        <div :class="msg.role === 'user' ? 'bg-primary/20 border border-primary/30 text-white rounded-2xl rounded-tr-sm' : 'bg-slate-800/80 border border-slate-700/50 text-slate-200 rounded-2xl rounded-tl-sm'" class="px-4 py-3 text-sm max-w-full">
                                            <div x-html="renderMarkdown(msg.text)"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                    <div class="px-4 pb-4 pt-3 border-t border-slate-700/50 shrink-0">
                        <div class="flex items-end gap-3 bg-slate-800/70 border border-slate-700 rounded-2xl px-4 py-3">
                            <textarea x-model="askAiInput" @keydown.enter.prevent="if(!$event.shiftKey) sendAiMessage()" rows="1" placeholder="Ask anything about this job..." class="flex-1 bg-transparent text-sm text-slate-200 outline-none resize-none"></textarea>
                            <button @click="sendAiMessage()" :disabled="askAiBusy || !askAiInput.trim()" class="p-2 bg-gradient-to-r from-primary to-secondary rounded-xl hover:opacity-90 transition">
                                <i data-lucide="send" class="w-4 h-4 text-white"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </main>

    </div>



    <script>
        function dashboardApp() {
            return {
                currentView: localStorage.getItem('jobpulse_last_view') || 'vibe_check',
                candidateName: '',

                // Client-side markdown renderer
                renderMarkdown(text) {
                    if (!text) return '';
                    const trimmed = text.trim();
                    // Robust HTML detection: If it starts with a tag (after optional whitespace/newlines)
                    if (/^\s*<[a-z1-6]/i.test(trimmed)) {
                        return text;
                    }

                    // Broad set of bullet markers used by different PDF parsers and AI models
                    // Broad set of bullet markers plus invisible characters
                    const bulletRegex = /^[*\-+•·\u2022\u2023\u2043\u204C\u204D\u25E6\u25AA\u25AB\u25CF\u25CB]\s*$/u;

                    let lines = text.split(/\r?\n/);
                    lines = lines.filter(line => {
                        // Aggressively strip invisible junk
                        const t = line.replace(/[\u200B-\u200D\uFEFF]/g, '').trim();
                        // If the line is exactly a bullet marker (plus optional spaces), strip it
                        if (t && bulletRegex.test(t)) return false;
                        // KEEP empty lines so paragraph spacing is preserved
                        return true;
                    });
                    
                    let html = lines.join('\n')
                        // Escape HTML first
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        // Headers
                        .replace(/^### (.+)$/gm, '<h3 class="text-sm font-bold text-slate-200 mt-4 mb-1 border-b border-slate-700/50 pb-1">$1</h3>')
                        .replace(/^## (.+)$/gm, '<h2 class="text-base font-extrabold text-white mt-5 mb-2 uppercase tracking-wider">$1</h2>')
                        .replace(/^# (.+)$/gm, '<h1 class="text-lg font-black text-white mt-2 mb-1">$1</h1>')
                        // Bold
                        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                        // Unordered bullets (handle markers with or without leading spaces)
                        .replace(/^[*\-+•·\u2022\u2023\u2043\u204C\u204D]\s*(.+)$/gm, '<li class="ml-4 list-disc text-slate-300 leading-relaxed">$1</li>')
                        // Ordered list items (1.item, etc.)
                        .replace(/^\d+\.\s*(.+)$/gm, '<li class="ml-4 list-disc text-slate-300 leading-relaxed">$1</li>')
                        // Wrap consecutive li tags
                        .replace(/(<li[^>]*>.*<\/li>\n?)+/g, m => {
                            // Final safety check: strip any LI tags that ended up empty or just whitespace/junk inside
                            const cleaned = m.replace(/<li[^>]*>(\s|&nbsp;|&#160;)*<\/li>\n?/g, '');
                            if (!cleaned.trim()) return '';
                            return '<ul class="my-2 space-y-1">' + cleaned + '</ul>';
                        })
                        // Paragraphs
                        .replace(/\n{2,}/g, '</p><p class="mb-2">')
                        .replace(/\n/g, '<br>');
                    return '<p class="mb-2">' + html + '</p>';
                },

                resumes: [],
                activeResumeId: null,
                showUploadModal: false,
                uploading: false,
                uploadError: null,
                newCategory: '',

                // History Job Description Editor
                showJobEditor: false,
                editingHistoryItem: null,
                jobEditorInstance: null,
                jobEditorHTML: '',
                jobEditorSaving: false,

                openJobEditor(item) {
                    if (!item) return;
                    console.log("Opening editor for item:", item.id);
                    this.editingHistoryItem = item;
                    this.jobEditorHTML = item.job_description || item.jobDescription || '';
                    this.showJobEditor = true;
                    
                    this.$nextTick(() => {
                        try {
                            if (this.jobEditorInstance) this.jobEditorInstance.destroy();
                            const el = document.querySelector('#job-description-editor');
                            if (!el) throw new Error("Editor mount point not found");

                            this.jobEditorInstance = new window.TiptapEditor({
                                element: el,
                                extensions: [
                                    ...window.getTiptapExtensions()
                                ],
                                content: this.jobEditorHTML,
                                editorProps: {
                                    attributes: { class: 'paper-page prose-analysis text-slate-300 bg-darkbg border border-slate-700 min-h-[400px] p-6 outline-none rounded-2xl' }
                                },
                                onUpdate: ({ editor }) => {
                                    this.jobEditorHTML = editor.getHTML();
                                }
                            });
                        } catch (err) {
                            console.error("Editor init failed", err);
                            alert("Failed to load editor: " + err.message);
                        }
                    });
                },

                async saveJobDescription() {
                    if (!this.editingHistoryItem || this.jobEditorSaving) return;
                    this.jobEditorSaving = true;
                    try {
                        const res = await fetch('/api/update_history_job_description.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                id: this.editingHistoryItem.id,
                                job_description: this.jobEditorHTML
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.editingHistoryItem.job_description = this.jobEditorHTML;
                            this.showJobEditor = false;
                        } else {
                            alert(data.error || "Failed to save job description.");
                        }
                    } catch(e) {
                        alert("Error saving job description: " + e.message);
                    } finally {
                        this.jobEditorSaving = false;
                    }
                },

                // Generalize Resume Wizard State
                showGeneralizeModal: false,
                generalizeStep: 1,
                generalizeBaseId: null,
                generalizeLabel: '',
                generalizeDirection: '',
                generalizeBusy: false,
                generalizeError: null,

                // Analysis State
                showAnalysisModal: false,
                analysisBusy: false,
                analysisData: null,
                analysisError: null,
                analysisTab: 'company_problems',
                
                // Loading / Progress State
                showGeneratingModal: false,
                genProgress: 0,
                genInterval: null,
                
                analysisTabs: [
                    { key: 'company_problems', label: 'Pain Points', icon: 'flame' },
                    { key: 'company_goals', label: 'Strategic Goals', icon: 'target' },
                    { key: 'candidate_alignment', label: 'Your Edge', icon: 'shield-check' },
                    { key: 'interview_prep', label: 'Interview Prep', icon: 'messages-square' },
                    { key: 'questions_to_ask', label: 'Questions to Ask', icon: 'help-circle' },
                    { key: 'cheat_sheet', label: 'Cheat Sheet', icon: 'zap' }
                ],

                // Editing Wizard State
                showWizard: false,
                wizardStep: 1, // 1: CV, 2: Resume, 3: Review
                editedCvlHTML: '',
                editedResumeHTML: '',
                editorInstance: null,

                initWizardEditor(contentHtml) {
                    try {
                        if (this.editorInstance) {
                            this.editorInstance.destroy();
                            this.editorInstance = null;
                        }
                        
                        const el = document.getElementById('wizard-editor-mount');
                        if (!el) {
                            console.error("Wizard editor mount point not found.");
                            return;
                        }

                        if (!window.TiptapEditor) {
                            console.error("TiptapEditor not loaded yet.");
                            return;
                        }

                        this.editorInstance = new window.TiptapEditor({
                            element: el,
                            extensions: window.getTiptapExtensions ? window.getTiptapExtensions() : [],
                            content: contentHtml || '<p>Start typing...</p>',
                            editorProps: {
                                attributes: { class: 'paper-page paper-editor shadow-2xl' }
                            },
                            onUpdate: ({ editor }) => {
                                if (this.wizardStep === 1) this.editedCvlHTML = editor.getHTML();
                                if (this.wizardStep === 2) this.editedResumeHTML = editor.getHTML();
                            }
                        });
                    } catch (err) {
                        console.error("Error initializing Wizard Editor:", err); alert("Editor Error: " + err.message);
                    }
                },

                startWizard() {
                    this.wizardStep = 1;
                    this.showWizard = true;
                    
                    // Pre-render from Markdown to HTML for the editor
                    let cvl = this.renderMarkdown(this.pendingOptimizedObj.cover_letter);
                    let res = this.renderMarkdown(this.pendingOptimizedObj.optimized_resume_text);
                    
                    // Aggressive DOM-based cleanup of residual bullet markers and empty blocks
                    const cleaner = (h) => {
                        const temp = document.createElement('div');
                        temp.innerHTML = h;
                        
                        // Paranoia: elements that look empty after stripping hidden chars and bullet markers
                        temp.querySelectorAll('li, p, h1, h2, h3').forEach(el => {
                            // Strip zero-width/invisible chars and broad range of bullet symbols
                            let t = el.textContent
                                .replace(/[\u200B-\u200D\uFEFF]/g, '') // Invisible junk
                                .replace(/^[*\-+•·\u2022\u2023\u2043\u204C\u204D\u25E6\u25AA\u25AB\u25CF\u25CB]\s*/, '') // Leading markers
                                .trim();
                                
                            // If it has no printable text and no media/structural kids, it's an artifact
                            if (!t && !el.querySelector('img, iframe, hr, strong, em, b, i')) {
                                el.remove();
                            }
                        });
                        
                        // Purge now-empty container lists
                        temp.querySelectorAll('ul, ol, div').forEach(el => {
                            if (!el.textContent.trim()) el.remove();
                        });
                        
                        return temp.innerHTML;
                    };
                    
                    this.editedCvlHTML = cleaner(cvl);
                    this.editedResumeHTML = cleaner(res);
                    
                    this.$nextTick(() => {
                        this.initWizardEditor(this.editedCvlHTML);
                        if(window.lucide) window.lucide.createIcons();
                    });
                },

                nextWizard() {
                    if (this.wizardStep === 1) {
                        this.wizardStep = 2;
                        this.$nextTick(() => this.initWizardEditor(this.editedResumeHTML));
                    } else if (this.wizardStep === 2) {
                        this.wizardStep = 3;
                        if (this.editorInstance) this.editorInstance.destroy();
                    }
                    setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 50);
                },

                prevWizard() {
                    if (this.wizardStep === 2) {
                        this.wizardStep = 1;
                        this.$nextTick(() => this.initWizardEditor(this.editedCvlHTML));
                    } else if (this.wizardStep === 3) {
                        this.wizardStep = 2;
                        this.$nextTick(() => this.initWizardEditor(this.editedResumeHTML));
                    }
                    setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 50);
                },

                finishWizard() {
                    this.finalResultObj = { ...this.pendingOptimizedObj };
                    this.finalResultObj.cover_letter = this.editedCvlHTML;
                    this.finalOptimizedText = this.editedResumeHTML;
                    // Tag them as HTML so the download API knows
                    this.finalResultObj._isHTML = true; 
                    
                    this.showWizard = false;
                    this.pendingOptimizedObj = null;

                    // Immediately fetch fresh history so it shows up in Hub
                    this.fetchHistory();
                    
                    setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 100);
                    setTimeout(() => window.scrollBy({ top: 800, behavior: 'smooth' }), 150);
                },

                async runDeepAnalysis() {
                    if (!this.jobDescription && this.editingHistoryItem) {
                        this.jobDescription = this.editingHistoryItem.job_description || this.editingHistoryItem.jobDescription || '';
                    }
                    if (!this.jobDescription) {
                        alert('Cannot perform analysis: The original job description for this record was not found.');
                        return;
                    }

                    this.analysisError = null;
                    this.analysisData = null;
                    this.showGeneratingModal = true;
                    this.genProgress = 0;
                    
                    if (this.genInterval) clearInterval(this.genInterval);

                    this.genInterval = setInterval(() => {
                        if (this.genProgress < 85) {
                            this.genProgress += Math.random() * 4;
                        }
                    }, 400);

                    try {
                        const res = await fetch('/api/analyze_job.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                job_description: this.jobDescription,
                                resume_text: this.finalOptimizedText,
                                cover_letter: this.finalResultObj?.cover_letter || '',
                                job_company_hint: this.finalResultObj?.job_company || '',
                                job_title_hint: this.finalResultObj?.job_title || '',
                                is_html: true
                            })
                        });
                        const data = await res.json();
                        if (!data.success) throw new Error(data.error || 'Analysis failed');
                        
                        this.analysisData = data.analysis;
                        this.genProgress = 100;

                        if (this.finalResultObj) {
                            if (!this.finalResultObj.job_company || this.finalResultObj.job_company.includes('Unknown')) {
                                this.finalResultObj.job_company = data.extracted_company || this.finalResultObj.job_company;
                            }
                            if (!this.finalResultObj.job_title || this.finalResultObj.job_title.includes('Untitled')) {
                                this.finalResultObj.job_title = data.extracted_role || this.finalResultObj.job_title;
                            }
                        }
                        
                        setTimeout(() => {
                            this.showGeneratingModal = false;
                            this.showAnalysisModal = true;
                            if (window.lucide) window.lucide.createIcons();
                        }, 500);

                    } catch(e) {
                        this.analysisError = e.message;
                        this.showGeneratingModal = false;
                        this.showAnalysisModal = true; 
                        if (window.lucide) window.lucide.createIcons();
                    } finally {
                        clearInterval(this.genInterval);
                    }
                },
 

                buildAnalysisDoc() {
                    if (!this.analysisData) return '';
                    const co = this.finalResultObj?.job_company || 'Company';
                    const ro = this.finalResultObj?.job_title   || 'Position';
                    const dt = new Date().toLocaleDateString();
                    return [
                        `# Tactical Job Analysis Report`,
                        `**Company:** ${co}  |  **Role:** ${ro}  |  **Generated:** ${dt}`,
                        `\n---\n`,
                        `## Company Pain Points\n\n${this.analysisData.company_problems || ''}`,
                        `\n---\n`,
                        `## Company Goals\n\n${this.analysisData.company_goals || ''}`,
                        `\n---\n`,
                        `## Your Strategic Edge\n\n${this.analysisData.candidate_alignment || ''}`,
                        `\n---\n`,
                        `## Interview Prep\n\n${this.analysisData.interview_prep || ''}`,
                        `\n---\n`,
                        `## Questions to Ask\n\n${this.analysisData.questions_to_ask || ''}`,
                        `\n---\n`,
                        `## Interview Cheat Sheet\n\n${this.analysisData.cheat_sheet || ''}`
                    ].join('\n');
                },

                async exportAnalysis(fmt) {
                    if (!this.analysisData) return;
                    const co = this.finalResultObj?.job_company || 'Company';
                    const ro = this.finalResultObj?.job_title   || 'Position';
                    const slug = s => s.replace(/[^a-zA-Z0-9]/g,'_').substring(0, 50); // Truncate long slugs
                    const ts   = Date.now();
                    const doc  = this.buildAnalysisDoc();

                    if (fmt === 'md') {
                        const blob = new Blob([doc], { type: 'text/markdown' });
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = `${slug(co)}_${slug(ro)}__analysis_${ts}.md`;
                        a.click(); URL.revokeObjectURL(a.href);
                        return;
                    }
                    if (fmt === 'txt') {
                        // Strip markdown to plain text
                        const plain = doc
                            .replace(/^#{1,3} /gm, '')
                            .replace(/\*\*(.+?)\*\*/g, '$1')
                            .replace(/\*(.+?)\*/g, '$1')
                            .replace(/^[\*\-] /gm, '  • ');
                        const blob = new Blob([plain], { type: 'text/plain' });
                        const a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = `${slug(co)}_${slug(ro)}__analysis_${ts}.txt`;
                        a.click(); URL.revokeObjectURL(a.href);
                        return;
                    }
                    if (fmt === 'pdf') {
                        try {
                            const res = await fetch('/api/prepare_download.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ content: doc, format: 'pdf', type: 'analysis', company: co, role: ro })
                            });
                            const d = await res.json();
                            if (!d.success) { alert(d.error || 'PDF failed'); return; }
                            const raw = atob(d.data);
                            const bytes = new Uint8Array(raw.length);
                            for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                            const blob = new Blob([bytes], { type: 'application/pdf' });
                            const a = document.createElement('a');
                            a.href = URL.createObjectURL(blob);
                            a.download = d.filename;
                            a.click(); URL.revokeObjectURL(a.href);
                        } catch(e) { alert('PDF error: ' + e.message); }
                    }
                },

                // Ask AI Chat State
                showAskAi: false,
                activeTabId: null,
                askAiTabs: [],
                askAiInput: '',
                askAiBusy: false,
                aiWelcomeMsg: "Hi! I've been loaded with your job posting, optimized resume, and cover letter. Ask me anything — screening question help, interview prep, how to position your experience, or anything else about this application.",

                openAskAi() {
                    this.showAskAi = true;
                    // If no tabs exist, create the first one
                    if (this.askAiTabs.length === 0) {
                        this.addNewTab();
                    } else if (!this.activeTabId) {
                        this.activeTabId = this.askAiTabs[0].id;
                    }
                    
                    this.$nextTick(() => {
                        this.scrollToBottom();
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                addNewTab() {
                    const now = new Date();
                    const id = 'chat_' + Date.now();
                    const name = now.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                    
                    this.askAiTabs.push({
                        id: id,
                        name: name,
                        timestamp: now.getTime(),
                        messages: [{ role: 'assistant', text: this.aiWelcomeMsg }],
                        isRenaming: false,
                        editName: name
                    });
                    this.activeTabId = id;
                    this.scrollToBottom();
                },

                selectTab(id) {
                    this.activeTabId = id;
                    this.scrollToBottom();
                },

                deleteTab(id, event) {
                    if (event) event.stopPropagation();
                    if (this.askAiTabs.length <= 1) {
                        // Just clear messages if last tab
                        const tab = this.askAiTabs[0];
                        tab.messages = [{ role: 'assistant', text: this.aiWelcomeMsg }];
                        tab.name = new Date().toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
                        return;
                    }
                    const idx = this.askAiTabs.findIndex(t => t.id === id);
                    this.askAiTabs = this.askAiTabs.filter(t => t.id !== id);
                    if (this.activeTabId === id) {
                        this.activeTabId = this.askAiTabs[Math.max(0, idx - 1)].id;
                    }
                },

                startRename(tab) {
                    tab.isRenaming = true;
                    tab.editName = tab.name;
                },

                saveRename(tab) {
                    if (tab.editName.trim()) {
                        tab.name = tab.editName.trim();
                    }
                    tab.isRenaming = false;
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.chatMessages;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },

                async sendAiMessage() {
                    const msg = this.askAiInput.trim();
                    if (!msg || this.askAiBusy || !this.activeTabId) return;
                    
                    const currentTab = this.askAiTabs.find(t => t.id === this.activeTabId);
                    if (!currentTab) return;

                    this.askAiInput = '';
                    currentTab.messages.push({ role: 'user', text: msg });
                    this.askAiBusy = true;
                    
                    this.scrollToBottom();

                    try {
                        // Build history from current tab
                        const history = currentTab.messages.slice(0, -1).map(m => ({
                            role: m.role === 'user' ? 'user' : 'assistant',
                            text: m.text
                        }));
                        const res = await fetch('/api/ask_ai.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                message: msg,
                                history,
                                context: {
                                    job_description: this.jobDescription || '',
                                    resume_text: this.finalOptimizedText || '',
                                    cover_letter: this.finalResultObj?.cover_letter || ''
                                }
                            })
                        });
                        const data = await res.json();
                        if (!data.success) throw new Error(data.error || 'AI error');
                        currentTab.messages.push({ role: 'assistant', text: data.reply });
                    } catch(e) {
                        currentTab.messages.push({ role: 'assistant', text: '⚠️ Error: ' + e.message });
                    } finally {
                        this.askAiBusy = false;
                        this.scrollToBottom();
                        setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 100);
                    }
                },

                analyzing: false,
                optimizing: false,
                analyzeError: null,

                latestResult: null,         // For Vibe Check / Cover Letter
                
                // For Optimization state
                pendingOptimizedObj: null, 
                missingSkillsAlert: [],
                finalOptimizedText: null,
                finalResultObj: null,

                history: <?php echo json_encode($history); ?>,
                jobDescription: '',

                cvCopied: false,
                optCopied: false,
                optCvlCopied: false,
                
                // Adzuna Job Search State
                jobs: [],
                jobsLoading: false,
                jobFilters: {
                    query: '',
                    location: '',
                    remote: false,
                    hybrid: false
                },

                init() {
                    // Persistence and Automatic Refresh
                    this.$watch('currentView', (view) => {
                        localStorage.setItem('jobpulse_last_view', view);
                        if (view === 'my_jobs') this.fetchHistory();
                        if (view === 'find_jobs') this.fetchJobs();
                        if (view === 'vibe_check') this.fetchResumes();
                        
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                    });

                    // Initial fetch for the starting view
                    if (this.currentView === 'my_jobs') this.fetchHistory();
                    if (this.currentView === 'find_jobs') this.fetchJobs();
                    if (this.currentView === 'vibe_check') this.fetchResumes();

                    // Auto fetch jobs when filters change deeply
                    this.$watch('jobFilters', (value) => {
                        this.fetchJobs();
                    });
                },

                async fetchHistory() {
                    try {
                        const res = await fetch('/api/history.php');
                        const data = await res.json();
                        if (data.success) {
                            this.history = data.history;
                            setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 100);
                        }
                    } catch(e) { console.error("Failed fetching history"); }
                },

                resetFields() {
                    if (!confirm("Clear all current fields to start a new application?")) return;
                    this.jobDescription = '';
                    this.latestResult = null;
                    this.pendingOptimizedObj = null;
                    this.finalOptimizedText = null;
                    this.finalResultObj = null;
                    this.analyzeError = null;
                    this.missingSkillsAlert = [];
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                
                async fetchResumes() {
                    try {
                        const res = await fetch('/api/resumes.php');
                        const data = await res.json();
                        if (data.success) {
                            this.resumes = data.resumes;
                            const primary = this.resumes.find(r => r.is_primary);
                            if (primary && !this.activeResumeId) this.activeResumeId = primary.id;
                            else if(this.resumes.length > 0 && !this.activeResumeId) this.activeResumeId = this.resumes[0].id;
                            setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 100);
                        }
                    } catch(e) { console.error("Failed fetching resumes"); }
                },

                async fetchJobs() {
                    this.jobsLoading = true;
                    try {
                        const params = new URLSearchParams({
                            q: this.jobFilters.query,
                            l: this.jobFilters.location,
                            remote: this.jobFilters.remote,
                            hybrid: this.jobFilters.hybrid
                        });
                        
                        const res = await fetch(`/api/search_jobs.php?${params.toString()}`);
                        const data = await res.json();
                        
                        if (data.success) {
                            this.jobs = data.results;
                            setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 100);
                        }
                    } catch(e) {
                        console.error('Job fetch error', e);
                    } finally {
                        this.jobsLoading = false;
                    }
                },

                async runGeneralizeResume() {
                    this.generalizeBusy = true;
                    this.generalizeError = null;
                    try {
                        const res = await fetch('/api/generalize_resume.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                resume_id: this.generalizeBaseId,
                                direction: this.generalizeDirection,
                                label: this.generalizeLabel || 'General'
                            })
                        });
                        const data = await res.json();
                        if (!data.success) throw new Error(data.error || 'Failed to generate.');
                        // Close modal, reset state, refresh resumes
                        this.showGeneralizeModal = false;
                        this.generalizeStep = 1;
                        this.generalizeBaseId = null;
                        this.generalizeLabel = '';
                        this.generalizeDirection = '';
                        await this.fetchResumes();
                        setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 150);
                        alert(`✅ ${data.message}`);
                    } catch(e) {
                        this.generalizeError = e.message;
                    } finally {
                        this.generalizeBusy = false;
                    }
                },

                sendToVibeCheck(description) {
                    this.currentView = 'vibe_check';
                    this.jobDescription = description;
                    
                    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 100);
                    setTimeout(() => { if(window.lucide) window.lucide.createIcons(); }, 150);
                },

                async uploadResume() {
                    const fileInput = this.$refs.resumeUpload;
                    const file = fileInput.files[0];
                    if (!file) return;
                    
                    this.uploading = true;
                    this.uploadError = null;
                    
                    const fd = new FormData();
                    fd.append('resume', file);
                    fd.append('category', this.newCategory || 'General');
                    
                    try {
                        const res = await fetch('/api/upload_resume.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            this.showUploadModal = false;
                            this.newCategory = '';
                            fileInput.value = '';
                            await this.fetchResumes();
                        } else {
                            this.uploadError = data.message || "Failed to parse PDF.";
                        }
                    } catch(err) {
                        this.uploadError = "Server Error.";
                    } finally {
                        this.uploading = false;
                    }
                },

                async setPrimary(id) {
                    try {
                        const res = await fetch('/api/resumes.php', {
                            method: 'POST', body: JSON.stringify({ action: 'set_primary', resume_id: id })
                        });
                        const data = await res.json();
                        if (data.success) await this.fetchResumes();
                    } catch(e) {}
                },

                async deleteResume(id) {
                    if(!confirm("Are you sure you want to delete this resume?")) return;
                    try {
                        const res = await fetch('/api/resumes.php', {
                            method: 'POST', body: JSON.stringify({ action: 'delete', resume_id: id })
                        });
                        const data = await res.json();
                        if (data.success) {
                            if(this.activeResumeId === id) this.activeResumeId = null;
                            await this.fetchResumes();
                        }
                    } catch(e) {}
                },
                


                async runOptimization() {
                    const textContent = this.jobDescription;
                    if (!textContent || !textContent.trim()) return this.analyzeError = "Please paste a Job Description.";
                    if (!this.activeResumeId) return this.analyzeError = "Please select an active resume.";

                    this.optimizing = true; this.analyzeError = null; this.latestResult = null; 
                    this.finalOptimizedText = null; this.finalResultObj = null; this.missingSkillsAlert = []; this.pendingOptimizedObj = null;

                    try {
                        const res = await fetch('/api/optimize_resume.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                job_description: textContent, 
                                resume_id: this.activeResumeId
                            })
                        });
                        
                        const data = await res.json();
                        if (!res.ok || !data.success) throw new Error(data.error || "Optimization failed.");
                        
                        const resultObj = data.result;
                        this.pendingOptimizedObj = resultObj;
                        this.candidateName = resultObj.candidate_name || '';
                        
                        if (resultObj.missing_skills && resultObj.missing_skills.length > 0) {
                            // Trigger Honesty Check UI Block
                            this.missingSkillsAlert = resultObj.missing_skills;
                            setTimeout(() => window.scrollBy({ top: 400, behavior: 'smooth' }), 100);
                        } else {
                            // Go to Wizard
                            this.startWizard();
                        }
                        
                    } catch(e) { this.analyzeError = e.message; } 
                    finally { this.optimizing = false; }
                },

                finalizeOptimization(proceed) {
                    this.missingSkillsAlert = [];
                    if (proceed) {
                        this.startWizard();
                    } else {
                        this.pendingOptimizedObj = null;
                        this.analyzeError = "Optimization cancelled. Please update your resume manually and re-upload under a new category.";
                    }
                },
                
                async downloadCombinedPDF() {
                    if (!this.finalOptimizedText || !this.finalResultObj?.cover_letter) return;
                    try {
                        const res = await fetch('/api/prepare_download.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                content: this.finalOptimizedText,
                                cover_letter: this.finalResultObj.cover_letter,
                                format: 'combined',
                                is_html: true, // Wizard output is always HTML
                                company: this.finalResultObj?.job_company || '',
                                role: this.finalResultObj?.job_title || '',
                                name: this.candidateName
                            })
                        });
                        const d = await res.json();
                        if (!d.success) { alert(d.error || 'Combined PDF failed'); return; }
                        const raw = atob(d.data);
                        const bytes = new Uint8Array(raw.length);
                        for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                        const blob = new Blob([bytes], { type: 'application/pdf' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = d.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        setTimeout(() => URL.revokeObjectURL(url), 10000);
                    } catch (e) {
                        alert('Error generating Combined PDF: ' + e.message);
                    }
                },

                async downloadOptTxt() {
                    if (!this.finalResultObj) return;
                    
                    let content = "=== OPTIMIZED RESUME BULLETS ===\n\n";
                    content += this.finalOptimizedText + "\n\n";
                    
                    if (this.finalResultObj.perfect_matches?.length > 0) {
                        content += "=== PERFECT MATCHES ===\n";
                        content += this.finalResultObj.perfect_matches.join(", ") + "\n\n";
                    }
                    
                    if (this.finalResultObj.cover_letter) {
                        content += "=== COVER LETTER ===\n\n";
                        content += this.finalResultObj.cover_letter + "\n";
                    }

                    try {
                        const res = await fetch('/api/prepare_download.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                content,
                                format: 'txt',
                                is_html: this.finalResultObj?._isHTML || false,
                                company: this.finalResultObj?.job_company || '',
                                role: this.finalResultObj?.job_title || '',
                                name: this.candidateName
                            })
                        });
                        const d = await res.json();
                        if (!d.success) { alert(d.error || 'Download failed'); return; }

                        // Decode base64 → binary → Blob (all in-memory, no URL navigation)
                        const raw = atob(d.data);
                        const bytes = new Uint8Array(raw.length);
                        for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                        const blob = new Blob([bytes], { type: d.mime });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = d.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        setTimeout(() => URL.revokeObjectURL(url), 10000);
                    } catch (e) {
                        console.error('TXT download error:', e);
                        alert('Download error: ' + e.message);
                    }
                },

                copyText(text, flag) {
                    if (text) {
                        navigator.clipboard.writeText(text);
                        this[flag] = true;
                        setTimeout(() => this[flag] = false, 2000);
                    }
                },

                async downloadPDF(content, type) {
                    if (!content) return;
                    try {
                        const res = await fetch('/api/prepare_download.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                content,
                                type,
                                format: 'pdf',
                                is_html: this.finalResultObj?._isHTML || false,
                                company: this.finalResultObj?.job_company || '',
                                role: this.finalResultObj?.job_title || '',
                                name: this.candidateName
                            })
                        });
                        const d = await res.json();
                        if (!d.success) { alert(d.error || 'PDF failed'); return; }

                        // Decode base64 → binary → Blob (all in-memory, no URL navigation)
                        const raw = atob(d.data);
                        const bytes = new Uint8Array(raw.length);
                        for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                        const blob = new Blob([bytes], { type: 'application/pdf' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = d.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        setTimeout(() => URL.revokeObjectURL(url), 10000);
                    } catch (e) {
                        console.error('PDF download error:', e);
                        alert('Error generating PDF: ' + e.message);
                    }
                },

                loadHistory(item) {
                    this.latestResult = item;
                    this.finalOptimizedText = null;
                    setTimeout(() => window.scrollBy({ top: 600, behavior: 'smooth' }), 100);
                },

                loadHistoryForAction(item, action) {
                    if (!item) return;
                    
                    // Force state update before async triggers
                    this.jobDescription = item.job_description || item.jobDescription || '';
                    this.finalOptimizedText = item.optimized_resume_text || '';
                    this.finalResultObj = {
                        cover_letter: item.cover_letter || '',
                        job_company: item.job_company || '(Unknown Company)',
                        job_title: item.job_title || '(Untitled)',
                        _isHTML: true
                    };

                    // Use setTimeout to ensure Alpine state is fully committed
                    setTimeout(() => {
                        try {
                            if (action === 'analysis') {
                                this.runDeepAnalysis();
                            } else if (action === 'ask_ai') {
                                this.askAiTabs = [];
                                this.activeTabId = null;
                                this.openAskAi();
                            }
                        } catch (err) {
                            console.error("History action failed", err);
                            alert("Action failed: " + err.message);
                        }
                    }, 0);
                },

                async deleteHistory(id, event) {
                    event.stopPropagation();
                    if (!confirm('Remove this application from your history?')) return;
                    try {
                        await fetch('/api/delete_history.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id })
                        });
                        await this.fetchHistory(); // Sync full state
                        if (this.latestResult?.id === id) this.latestResult = null;
                    } catch(e) { alert('Failed to delete.'); }
                },

                async saveJobDetails(item) {
                    if (item._saving) return;
                    item._saving = true;
                    try {
                        const res = await fetch('/api/update_job_details.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                job_id: item.id,
                                title: item._editTitle,
                                company: item._editCompany,
                                url: item._editUrl
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            item.job_title = item._editTitle;
                            item.job_company = item._editCompany;
                            item.job_url = item._editUrl;
                            item._editing = false;
                        }
                    } catch(e) { console.error("Could not update details", e); }
                    item._saving = false;
                },

                async addJobNote(item) {
                    const status = item._newNoteStatus || 'Custom';
                    const text = item._newNoteText || '';
                    if (!text.trim() && status === 'Custom') return;

                    try {
                        const res = await fetch('/api/update_job_note.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ job_id: item.id, status: status, text: text })
                        });
                        const data = await res.json();
                        if (data.success) {
                            if (!item.notes) item.notes = [];
                            item.notes.push(data.note);
                            item._newNoteText = '';
                            item._newNoteStatus = 'Custom';
                        }
                    } catch (e) { console.error("Could not add note"); }
                },

                async logout() {
                    await fetch('/api/auth.php?action=logout', { method: 'POST' });
                    window.location.reload();
                }
            }
        }
    </script>
    <?php endif; ?>

    <script>document.addEventListener("DOMContentLoaded", () => { if (window.lucide) window.lucide.createIcons(); });</script>
</body>
</html>
