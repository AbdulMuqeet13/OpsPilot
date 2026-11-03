<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoSandboxController;
use App\Http\Controllers\Insights\AiActivityController;
use App\Http\Controllers\Insights\DailyBriefController;
use App\Http\Controllers\Insights\InsightsController;
use App\Http\Controllers\Leads\LeadAiTaskController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Leads\LeadFollowUpController;
use App\Http\Controllers\Leads\LeadImportController;
use App\Http\Controllers\Public\PublicLeadController;
use App\Http\Controllers\Public\PublicTicketController;
use App\Http\Controllers\Settings\AiConnectionTestController;
use App\Http\Controllers\Settings\AiFeatureController;
use App\Http\Controllers\Settings\AiUsageController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\BrandingController;
use App\Http\Controllers\Settings\KnowledgeSnippetController;
use App\Http\Controllers\Settings\LeadProfileController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TicketCategoryController;
use App\Http\Controllers\Tickets\AiSuggestionDecisionController;
use App\Http\Controllers\Tickets\InboundEmailController;
use App\Http\Controllers\Tickets\TicketAiTaskController;
use App\Http\Controllers\Tickets\TicketController;
use App\Http\Controllers\Tickets\TicketReplyController;
use App\Http\Controllers\Tickets\TicketReplyDraftController;
use App\Http\Controllers\Tickets\TicketReplyRewriteController;
use App\Http\Controllers\Tickets\TicketTriageAcceptanceController;
use App\Http\Middleware\EnsureDemoModeState;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome', [
    'demoMode' => (bool) config('demo.enabled'),
]))->name('home');

Route::get('w/{workspace:slug}/contact', [PublicTicketController::class, 'create'])->name('public.tickets.create');
Route::post('w/{workspace:slug}/contact', [PublicTicketController::class, 'store'])
    ->middleware('throttle:public-forms')
    ->name('public.tickets.store');

Route::get('w/{workspace:slug}/quote', [PublicLeadController::class, 'create'])->name('public.leads.create');
Route::post('w/{workspace:slug}/quote', [PublicLeadController::class, 'store'])
    ->middleware('throttle:public-forms')
    ->name('public.leads.store');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::middleware(EnsureDemoModeState::class.':off')->group(function () {
        Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('register.store');
    });

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');

    Route::post('demo/{role}', [DemoSandboxController::class, 'store'])
        ->middleware([EnsureDemoModeState::class.':on', 'throttle:demo-sandbox'])
        ->name('demo.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/inbound-email', [InboundEmailController::class, 'create'])->name('inbound-email.create');
    Route::post('tickets/inbound-email', [InboundEmailController::class, 'store'])->name('inbound-email.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('tickets/{ticket}/replies', [TicketReplyController::class, 'store'])->name('tickets.replies.store');
    Route::post('tickets/{ticket}/triage-acceptance', [TicketTriageAcceptanceController::class, 'store'])->name('tickets.triage-acceptance.store');
    Route::post('ai-suggestions/{suggestion}/decision', [AiSuggestionDecisionController::class, 'store'])->name('ai-suggestions.decision.store');

    Route::get('insights', [InsightsController::class, 'index'])->name('insights.index');
    Route::get('insights/briefs', [DailyBriefController::class, 'index'])->name('daily-briefs.index');
    Route::get('ai-activity', [AiActivityController::class, 'index'])->name('ai-activity.index');

    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/import', [LeadImportController::class, 'create'])->name('lead-imports.create');
    Route::post('leads/import', [LeadImportController::class, 'store'])->name('lead-imports.store');
    Route::get('leads/import/{import}', [LeadImportController::class, 'show'])->name('lead-imports.show');
    Route::post('leads/import/{import}/run', [LeadImportController::class, 'run'])->name('lead-imports.run');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('leads/{lead}/follow-up', [LeadFollowUpController::class, 'store'])->name('leads.follow-up.store');

    Route::middleware('throttle:ai')->group(function () {
        Route::post('insights/briefs', [DailyBriefController::class, 'store'])->name('daily-briefs.store');
        Route::post('leads/{lead}/ai-tasks/{task}', [LeadAiTaskController::class, 'store'])
            ->whereIn('task', ['scoring', 'follow_up'])
            ->name('leads.ai-tasks.store');
        Route::post('tickets/{ticket}/ai-tasks/{task}', [TicketAiTaskController::class, 'store'])
            ->whereIn('task', ['triage', 'draft'])
            ->name('tickets.ai-tasks.store');
        Route::post('tickets/{ticket}/reply-drafts', [TicketReplyDraftController::class, 'store'])->name('tickets.reply-drafts.store');
        Route::post('tickets/{ticket}/reply-rewrites', [TicketReplyRewriteController::class, 'store'])->name('tickets.reply-rewrites.store');
    });

    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
    Route::get('settings/branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::patch('settings/branding', [BrandingController::class, 'update'])->name('branding.update');
    Route::get('settings/ai', [AiFeatureController::class, 'edit'])->name('ai-features.edit');
    Route::put('settings/ai', [AiFeatureController::class, 'update'])->name('ai-features.update');
    Route::post('settings/ai/connection-test', [AiConnectionTestController::class, 'store'])
        ->middleware('throttle:ai')
        ->name('ai-connection-test.store');
    Route::get('settings/ai/usage', [AiUsageController::class, 'index'])->name('ai-usage.index');
    Route::get('settings/ticket-categories', [TicketCategoryController::class, 'index'])->name('ticket-categories.index');
    Route::post('settings/ticket-categories', [TicketCategoryController::class, 'store'])->name('ticket-categories.store');
    Route::patch('settings/ticket-categories/{category}', [TicketCategoryController::class, 'update'])->name('ticket-categories.update');
    Route::delete('settings/ticket-categories/{category}', [TicketCategoryController::class, 'destroy'])->name('ticket-categories.destroy');
    Route::get('settings/knowledge', [KnowledgeSnippetController::class, 'index'])->name('knowledge.index');
    Route::post('settings/knowledge', [KnowledgeSnippetController::class, 'store'])->name('knowledge.store');
    Route::patch('settings/knowledge/{snippet}', [KnowledgeSnippetController::class, 'update'])->name('knowledge.update');
    Route::delete('settings/knowledge/{snippet}', [KnowledgeSnippetController::class, 'destroy'])->name('knowledge.destroy');
    Route::get('settings/lead-scoring', [LeadProfileController::class, 'edit'])->name('lead-profile.edit');
    Route::put('settings/lead-scoring', [LeadProfileController::class, 'update'])->name('lead-profile.update');
    Route::get('settings/api', [ApiKeyController::class, 'edit'])->name('api-key.edit');
    Route::post('settings/api', [ApiKeyController::class, 'store'])->name('api-key.store');
    Route::delete('settings/api', [ApiKeyController::class, 'destroy'])->name('api-key.destroy');
});
