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
                        primary: '#6366f1', // Indigo 500
                        secondary: '#8b5cf6', // Violet 500
                        accent: '#14b8a6', // Teal 500
                        darkbg: '#0f172a', // Slate 900
                        darkcard: '#1e293b' // Slate 800
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Alpine JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        .glass {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        /* Custom scrollbar for dark mode */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="bg-darkbg text-slate-300 antialiased min-h-screen selection:bg-primary selection:text-white transition-colors duration-300">
    
    <!-- Background glowing accents -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-primary/20 rounded-full blur-3xl animate-pulse-slow"></div>
        <div class="absolute top-1/2 -left-20 w-80 h-80 bg-secondary/10 rounded-full blur-3xl animate-pulse-slow"></div>
    </div>

    <div x-data="jobPulseApp()" x-init="initData()" class="min-h-screen flex flex-col relative z-10">
        
        <!-- Navigation -->
        <nav class="glass sticky top-0 z-50 border-b border-slate-700 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-secondary flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-primary/20 border border-white/10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <span class="font-bold text-xl tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-slate-100 to-slate-400">JobPulse AI</span>
                    </div>
                    <div class="flex items-center space-x-2 sm:space-x-4">
                        <button @click="currentView = 'dashboard'" :class="currentView === 'dashboard' ? 'text-white bg-white/10' : 'text-slate-400 hover:text-white hover:bg-white/5'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">Dashboard</button>
                        <button @click="currentView = 'resumes'" :class="currentView === 'resumes' ? 'text-white bg-white/10' : 'text-slate-400 hover:text-white hover:bg-white/5'" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">Resumes</button>
                        <button @click="isAnalyzeModalOpen = true" class="px-3 sm:px-4 py-2 rounded-lg text-sm font-semibold bg-gradient-to-r from-primary to-secondary text-white hover:shadow-lg hover:shadow-primary/25 transition-all duration-200 shadow-md">Analyze</button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="flex-1 w-full max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Toast Notification -->
            <div x-show="toast.show" x-transition.opacity.duration.300ms x-cloak class="fixed bottom-6 right-6 z-50">
                <div :class="toast.type === 'error' ? 'bg-red-500/90 border-red-500' : 'bg-primary/90 border-primary'" class="text-white px-6 py-4 rounded-xl shadow-2xl backdrop-blur-md flex items-center space-x-3 border border-t-[1px] border-l-[1px]">
                    <svg x-show="toast.type !== 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <svg x-show="toast.type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-medium" x-text="toast.message"></span>
                </div>
            </div>

            <!-- Include Dashboard View Content passed from index.php -->
            <?php echo $content ?? ''; ?>

        </main>
        
        <footer class="py-8 text-center text-slate-500 text-sm border-t border-slate-800/50 mt-auto bg-darkbg/50 backdrop-blur-sm z-10">
            <div class="flex flex-col items-center justify-center space-y-2">
                <span class="font-semibold text-slate-400 tracking-wider text-xs uppercase">Powering Modern Careers</span>
                <span>&copy; <?php echo date('Y'); ?> JobPulse AI. All rights reserved.</span>
            </div>
        </footer>
    </div>

    <!-- Application Logic -->
    <script>
        function jobPulseApp() {
            return {
                currentView: 'dashboard',
                jobs: [],
                resumes: [],
                loading: false,
                toast: { show: false, message: '', type: 'success' },
                
                // Job Form State
                jobForm: { company_name: '', job_title: '', status: 'Applied', notes: '' },
                isJobModalOpen: false,
                
                // Resume Form State
                resumeFile: null,
                isResumeModalOpen: false,
                uploading: false,

                // AI Analyze State
                isAnalyzeModalOpen: false,
                analyzing: false,
                analyzeForm: {
                    company_name: '',
                    job_title: '',
                    job_description: '',
                    resume_id: ''
                },

                showToast(msg, type = 'success') {
                    this.toast.message = msg;
                    this.toast.type = type;
                    this.toast.show = true;
                    setTimeout(() => { this.toast.show = false; }, 4000);
                },

                async initData() {
                    this.loading = true;
                    await Promise.all([this.fetchJobs(), this.fetchResumes()]);
                    if(this.resumes.length > 0 && !this.analyzeForm.resume_id) {
                        this.analyzeForm.resume_id = this.resumes[0].id;
                    }
                    this.loading = false;
                },

                async fetchJobs() {
                    try {
                        const res = await fetch('/api/jobs');
                        if (res.ok) this.jobs = await res.json();
                    } catch (e) {
                        console.error('Failed to fetch jobs', e);
                    }
                },

                async fetchResumes() {
                    try {
                        const res = await fetch('/api/resumes');
                        if (res.ok) {
                            this.resumes = await res.json();
                            if(this.resumes.length > 0 && !this.analyzeForm.resume_id) {
                                this.analyzeForm.resume_id = this.resumes[0].id;
                            }
                        }
                    } catch (e) {
                        console.error('Failed to fetch resumes', e);
                    }
                },

                async submitJob() {
                    try {
                        const res = await fetch('/api/jobs', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.jobForm)
                        });
                        if (res.ok) {
                            this.showToast('Job added successfully!');
                            this.isJobModalOpen = false;
                            this.jobForm = { company_name: '', job_title: '', status: 'Applied', notes: '' };
                            await this.fetchJobs();
                        } else {
                            this.showToast('Failed to add job.', 'error');
                        }
                    } catch (e) {
                        console.error(e);
                        this.showToast('An error occurred.', 'error');
                    }
                },

                async submitAnalysis() {
                    this.analyzing = true;
                    try {
                        const res = await fetch('/analyze.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.analyzeForm)
                        });
                        const data = await res.json();
                        
                        if (res.ok && data.success) {
                            this.showToast('Job analyzed and saved!');
                            this.isAnalyzeModalOpen = false;
                            this.analyzeForm.job_description = '';
                            this.analyzeForm.company_name = '';
                            this.analyzeForm.job_title = '';
                            await this.fetchJobs();
                            // Switch to dashboard to see it
                            this.currentView = 'dashboard';
                        } else {
                            this.showToast(data.error || 'Analysis failed.', 'error');
                        }
                    } catch (e) {
                        console.error(e);
                        this.showToast('Server error during analysis.', 'error');
                    } finally {
                        this.analyzing = false;
                    }
                },

                async deleteJob(id) {
                    if(!confirm('Are you sure you want to delete this job?')) return;
                    try {
                        const res = await fetch(`/api/jobs?id=${id}`, { method: 'DELETE' });
                        if(res.ok) {
                            this.showToast('Job deleted.');
                            await this.fetchJobs();
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                handleFileSelect(event) {
                    if(event.target.files.length > 0) {
                        this.resumeFile = event.target.files[0];
                    }
                },

                async uploadResume() {
                    if(!this.resumeFile) return;
                    this.uploading = true;
                    
                    const formData = new FormData();
                    formData.append('resume', this.resumeFile);
                    
                    try {
                        const res = await fetch('/api/resumes', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (res.ok) {
                            this.showToast('Resume uploaded successfully!');
                            this.isResumeModalOpen = false;
                            this.resumeFile = null;
                            document.getElementById('file-upload').value = '';
                            await this.fetchResumes();
                        } else {
                            const data = await res.json();
                            this.showToast(data.error || 'Upload failed.', 'error');
                        }
                    } catch (e) {
                        console.error(e);
                        this.showToast('Upload error.', 'error');
                    } finally {
                        this.uploading = false;
                    }
                },

                async deleteResume(id) {
                    if(!confirm('Delete this resume?')) return;
                    try {
                        const res = await fetch(`/api/resumes?id=${id}`, { method: 'DELETE' });
                        if(res.ok) {
                            this.showToast('Resume deleted.');
                            await this.fetchResumes();
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                formatDate(dateString) {
                    if(!dateString) return '';
                    const d = new Date(dateString);
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                },
                
                getStatusColor(status) {
                    const colors = {
                        'Applied': 'bg-blue-500/20 text-blue-300 border-blue-500/30',
                        'Interviewing': 'bg-purple-500/20 text-purple-300 border-purple-500/30',
                        'Offer': 'bg-accent/20 text-accent border-accent/30',
                        'Rejected': 'bg-red-500/20 text-red-300 border-red-500/30',
                        'Analyzed': 'bg-primary/20 text-indigo-300 border-primary/30'
                    };
                    return colors[status] || 'bg-slate-700 text-slate-300 border-slate-600';
                }
            }
        }
    </script>
</body>
</html>
