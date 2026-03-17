<?php
ob_start();
?>

<!-- Views -->

<!-- Dashboard View (Jobs) -->
<div x-show="currentView === 'dashboard'" x-transition.opacity.duration.300ms x-cloak>
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 relative z-10">
        <div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight drop-shadow-sm">Job Applications</h1>
            <p class="text-slate-400 mt-2 text-sm">Track and analyze your job hunt gracefully.</p>
        </div>
        <div class="flex space-x-3 mt-4 md:mt-0">
            <button @click="isJobModalOpen = true" class="inline-flex items-center px-4 py-2 bg-darkcard border border-slate-600 hover:border-slate-500 hover:bg-slate-700 text-white text-sm font-medium rounded-xl shadow-lg transition-all active:scale-95">
                <svg class="w-5 h-5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Manual Add
            </button>
            <button @click="isAnalyzeModalOpen = true" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-primary to-secondary hover:from-indigo-400 hover:to-violet-400 text-white text-sm font-bold rounded-xl shadow-[0_0_15px_rgba(99,102,241,0.5)] transition-all active:scale-95">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                AI Analyze
            </button>
        </div>
    </div>

    <!-- Stats row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 relative z-10">
        <div class="glass p-6 rounded-2xl shadow-xl relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-primary/20 rounded-full blur-2xl group-hover:bg-primary/30 transition-all duration-500"></div>
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Total Applied</p>
            <p class="text-4xl font-extrabold text-white mt-2" x-text="jobs.length"></p>
        </div>
        <div class="glass p-6 rounded-2xl shadow-xl relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-secondary/20 rounded-full blur-2xl group-hover:bg-secondary/30 transition-all duration-500"></div>
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Interviewing</p>
            <p class="text-4xl font-extrabold text-white mt-2" x-text="jobs.filter(j => j.status === 'Interviewing').length"></p>
        </div>
        <div class="glass p-6 rounded-2xl shadow-xl relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-accent/20 rounded-full blur-2xl group-hover:bg-accent/30 transition-all duration-500"></div>
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Offers</p>
            <p class="text-4xl font-extrabold text-white mt-2" x-text="jobs.filter(j => j.status === 'Offer').length"></p>
        </div>
    </div>

    <!-- Jobs Table -->
    <div class="glass rounded-2xl shadow-2xl overflow-hidden relative z-10 border-t border-l border-white/10">
        <template x-if="loading">
            <div class="p-12 text-center text-slate-400">
                <svg class="animate-spin h-10 w-10 text-primary mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span class="font-medium tracking-wide">Loading your pipeline...</span>
            </div>
        </template>
        <template x-if="!loading && jobs.length === 0">
            <div class="p-16 text-center flex flex-col items-center">
                <div class="w-20 h-20 bg-darkcard/50 ring-1 ring-white/10 text-slate-500 rounded-full flex items-center justify-center mb-6 shadow-inner">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                </div>
                <h3 class="text-xl font-bold text-white">Your Pipeline is Empty</h3>
                <p class="text-slate-400 mt-2 max-w-sm text-sm">You haven't added any job applications. Add one manually or use AI Analysis to get started.</p>
                <div class="mt-8 flex space-x-4">
                    <button @click="isAnalyzeModalOpen = true" class="px-6 py-3 bg-gradient-to-r from-primary to-secondary text-white font-bold rounded-xl shadow-lg hover:shadow-primary/30 transition-all">AI Analyze First Job</button>
                </div>
            </div>
        </template>
        <template x-if="jobs.length > 0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-700/50">
                    <thead class="bg-darkcard/40 backdrop-blur-md">
                        <tr>
                            <th scope="col" class="px-6 py-5 text-left text-xs font-bold text-slate-400 uppercase tracking-widest">Company & Role</th>
                            <th scope="col" class="px-6 py-5 text-left text-xs font-bold text-slate-400 uppercase tracking-widest">Status</th>
                            <th scope="col" class="px-6 py-5 text-left text-xs font-bold text-slate-400 uppercase tracking-widest">Date Applied</th>
                            <th scope="col" class="px-6 py-5 text-right text-xs font-bold text-slate-400 uppercase tracking-widest">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        <template x-for="job in jobs" :key="job.id">
                            <tr class="hover:bg-slate-800/40 transition-colors duration-200 group">
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center text-primary font-bold mr-4">
                                            <span x-text="job.company_name.substring(0,1).toUpperCase()"></span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-white" x-text="job.company_name"></div>
                                            <div class="text-xs text-slate-400 mt-0.5" x-text="job.job_title"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-md border" :class="getStatusColor(job.status)" x-text="job.status"></span>
                                    <template x-if="job.ai_analysis">
                                        <div class="mt-2 text-xs text-slate-400">
                                            AI Score: <span class="text-primary font-bold" x-text="JSON.parse(job.ai_analysis).score + '/10'"></span>
                                        </div>
                                    </template>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap text-sm text-slate-400 font-medium">
                                    <span x-text="formatDate(job.date_applied)"></span>
                                </td>
                                <td class="px-6 py-5 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="deleteJob(job.id)" class="p-2 text-slate-500 hover:text-red-400 bg-slate-800/0 hover:bg-slate-800 rounded-lg transition-all opacity-0 group-hover:opacity-100">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</div>

<!-- Resumes View -->
<div x-show="currentView === 'resumes'" x-transition.opacity.duration.300ms x-cloak class="relative z-10">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Your Resumes</h1>
            <p class="text-slate-400 mt-2 text-sm">Upload standard PDFs for AI Analysis parsing.</p>
        </div>
        <button @click="isResumeModalOpen = true" class="mt-4 md:mt-0 inline-flex items-center px-5 py-2.5 bg-secondary hover:bg-violet-400 text-white text-sm font-bold rounded-xl shadow-[0_0_15px_rgba(139,92,246,0.3)] transition-all active:scale-95">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            Upload Resume
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <template x-if="resumes.length === 0 && !loading">
            <div class="col-span-full p-16 glass rounded-2xl border border-slate-700/50 border-dashed text-center">
                <div class="w-16 h-16 bg-darkcard/50 ring-1 ring-white/10 text-slate-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-white">No Resumes Found</h3>
                <p class="text-sm text-slate-400 mt-2">Upload a standard format PDF so the AI can parse and analyze your fit against job descriptions.</p>
            </div>
        </template>
        <template x-for="resume in resumes" :key="resume.id">
            <div class="glass rounded-2xl border-t border-l border-white/5 p-6 shadow-xl hover:shadow-primary/10 transition-shadow relative group">
                <div class="w-14 h-14 rounded-xl bg-darkcard/80 flex items-center justify-center text-secondary mb-5 border border-slate-700/50 shadow-inner">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                </div>
                <h4 class="font-bold text-white truncate text-lg tracking-tight" :title="resume.original_name" x-text="resume.original_name"></h4>
                <p class="text-xs font-medium text-slate-400 mt-2" x-text="'Uploaded ' + formatDate(resume.upload_date)"></p>
                
                <div class="absolute top-5 right-5">
                    <button @click="deleteResume(resume.id)" class="p-2.5 text-slate-500 bg-slate-800/50 hover:bg-red-500/20 hover:text-red-400 rounded-xl transition-all opacity-0 group-hover:opacity-100 backdrop-blur-sm shadow-sm ring-1 ring-white/5 hover:ring-red-500/30">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>

<!-- AI Analyze Modal -->
<div x-show="isAnalyzeModalOpen" x-transition.opacity x-cloak class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-darkbg/80 backdrop-blur-xl transition-opacity" @click="if(!analyzing) isAnalyzeModalOpen = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-darkcard rounded-3xl text-left overflow-hidden shadow-2xl shadow-primary/20 transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-700/50">
            <!-- Loading Overlay -->
            <div x-show="analyzing" x-transition.opacity class="absolute inset-0 z-10 bg-darkcard/90 backdrop-blur-sm flex flex-col items-center justify-center">
                <div class="w-20 h-20 mb-6 relative">
                    <div class="absolute inset-0 rounded-full border-t-2 border-primary animate-spin"></div>
                    <div class="absolute inset-2 rounded-full border-r-2 border-secondary animate-spin" style="animation-direction: reverse; animation-duration: 1.5s;"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-primary">
                        <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">AI is Analyzing the Fit...</h3>
                <p class="text-slate-400 text-sm">Parsing resume PDF and generating a match score.</p>
            </div>

            <div class="px-6 py-6 md:px-8 md:py-8 relative">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary to-secondary"></div>
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-white tracking-tight" id="modal-title">AI Job Analyzer</h3>
                        <p class="text-sm text-slate-400 mt-1">Paste the job description and let AI determine your fit.</p>
                    </div>
                    <button @click="isAnalyzeModalOpen = false" class="text-slate-500 hover:text-white transition-colors bg-slate-800 p-2 rounded-full" :disabled="analyzing">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submitAnalysis" class="space-y-5">
                    <!-- Setup row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 p-4 rounded-xl bg-slate-800/50 border border-slate-700/50">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Company Name</label>
                            <input type="text" x-model="analyzeForm.company_name" required class="block w-full rounded-lg bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="e.g. Google">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Role/Title</label>
                            <input type="text" x-model="analyzeForm.job_title" required class="block w-full rounded-lg bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 transition-colors" placeholder="e.g. Senior Frontend Engineer">
                        </div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Paste Job Description</label>
                        </div>
                        <textarea x-model="analyzeForm.job_description" required rows="6" placeholder="Paste the full job description text here..." class="block w-full rounded-xl bg-darkbg border border-slate-700 text-slate-300 shadow-inner focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-5 py-4 font-mono text-xs leading-relaxed transition-colors resize-none custom-scrollbar"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Select Resume to Analyze Against</label>
                        <template x-if="resumes.length === 0">
                            <div class="p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-sm text-red-400 flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                You must upload a resume in the Resumes tab first.
                            </div>
                        </template>
                        <template x-if="resumes.length > 0">
                            <select x-model="analyzeForm.resume_id" required class="block w-full rounded-lg bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 appearance-none font-medium">
                                <template x-for="resume in resumes" :key="resume.id">
                                    <option :value="resume.id" x-text="resume.original_name"></option>
                                </template>
                            </select>
                        </template>
                    </div>

                    <div class="pt-4 flex justify-end space-x-3 border-t border-slate-800">
                        <button type="button" @click="isAnalyzeModalOpen = false" class="px-5 py-2.5 bg-transparent border border-slate-600 rounded-xl text-sm font-bold text-slate-300 hover:bg-slate-800 transition-colors">Cancel</button>
                        <button type="submit" :disabled="resumes.length === 0 || analyzing" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent rounded-xl shadow-[0_0_15px_rgba(99,102,241,0.4)] text-sm font-bold text-white bg-gradient-to-r from-primary to-secondary hover:from-indigo-500 hover:to-violet-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Analyze Fit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Job Modal Overlay (Manual) -->
<div x-show="isJobModalOpen" x-transition.opacity x-cloak class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-darkbg/80 backdrop-blur-sm transition-opacity" @click="isJobModalOpen = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-darkcard rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-700 p-6 md:p-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white" id="modal-title">Log Manual Application</h3>
                <button @click="isJobModalOpen = false" class="text-slate-500 hover:text-white bg-slate-800 p-1.5 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form @submit.prevent="submitJob" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Company</label>
                        <input type="text" x-model="jobForm.company_name" required class="block w-full rounded-lg bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-2.5 placeholder-slate-600">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Role/Title</label>
                        <input type="text" x-model="jobForm.job_title" required class="block w-full rounded-lg bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-2.5 placeholder-slate-600">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Status</label>
                    <select x-model="jobForm.status" class="block w-full rounded-lg bg-darkbg border border-slate-700 text-white shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-2.5 appearance-none font-medium">
                        <option>Applied</option>
                        <option>Interviewing</option>
                        <option>Offer</option>
                        <option>Rejected</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Notes (Optional)</label>
                    <textarea x-model="jobForm.notes" rows="3" class="block w-full rounded-lg bg-darkbg border border-slate-700 text-slate-300 shadow-sm focus:border-primary focus:ring-1 focus:ring-primary sm:text-sm px-4 py-3 placeholder-slate-600 custom-scrollbar"></textarea>
                </div>

                <div class="mt-8 flex justify-end space-x-3">
                    <button type="button" @click="isJobModalOpen = false" class="px-5 py-2.5 bg-transparent border border-slate-600 rounded-xl text-sm font-bold text-slate-300 hover:bg-slate-800 transition-colors">Cancel</button>
                    <button type="submit" class="inline-flex justify-center px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-white border border-slate-600 rounded-xl text-sm font-bold shadow-md transition-colors">Save Manual Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Resume Modal -->
<div x-show="isResumeModalOpen" x-transition.opacity x-cloak class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-darkbg/80 backdrop-blur-sm transition-opacity" @click="isResumeModalOpen = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-darkcard rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-700 p-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white tracking-tight" id="modal-title">Upload Core Resume</h3>
                <button @click="isResumeModalOpen = false" class="text-slate-500 hover:text-white bg-slate-800 p-1.5 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <form @submit.prevent="uploadResume" class="space-y-6">
                <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-600 border-dashed rounded-2xl bg-darkbg/50 transition-colors" :class="resumeFile ? 'border-secondary/50 bg-secondary/10' : 'hover:border-slate-500'">
                    <div class="space-y-2 text-center">
                        <svg class="mx-auto h-12 w-12" :class="resumeFile ? 'text-secondary' : 'text-slate-500'" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-slate-400 justify-center">
                        <label for="file-upload" class="relative cursor-pointer rounded-md font-bold text-secondary hover:text-violet-400 focus-within:outline-none transition-colors">
                            <span x-text="resumeFile ? resumeFile.name : 'Select a PDF file'"></span>
                            <input id="file-upload" name="file-upload" type="file" accept=".pdf,.doc,.docx,.txt" class="sr-only" @change="handleFileSelect">
                        </label>
                        </div>
                        <p class="text-xs text-slate-500 font-medium tracking-wide" x-show="!resumeFile">Ideally standard .pdf (up to 10MB) for best AI parsing results.</p>
                    </div>
                </div>

                <div class="pt-2 flex justify-end space-x-3">
                    <button type="button" @click="isResumeModalOpen = false" class="px-5 py-2.5 bg-transparent border border-slate-600 rounded-xl text-sm font-bold text-slate-300 hover:bg-slate-800 transition-colors">Cancel</button>
                    <button type="submit" :disabled="!resumeFile || uploading" class="inline-flex justify-center px-6 py-2.5 border border-transparent rounded-xl shadow-[0_0_15px_rgba(139,92,246,0.3)] text-sm font-bold text-white bg-secondary hover:bg-violet-500 transition-all disabled:opacity-50">
                        <span x-show="!uploading">Upload Document</span>
                        <span x-show="uploading" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Uploading...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
