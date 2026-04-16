<?php

use think\facade\Route;
use app\middleware\AuthMiddleware;
use app\middleware\ThrottleMiddleware;
use app\middleware\StepUpMiddleware;

// Health check (no auth)
Route::get('/api/health', 'Index/health');

// ─── Authentication ───
Route::group('/api/auth', function () {
    Route::post('/login', 'auth.Auth/login');
    Route::post('/logout', 'auth.Auth/logout')->middleware(AuthMiddleware::class);
    Route::post('/change-password', 'auth.Auth/changePassword')->middleware(AuthMiddleware::class);
});

// ─── Admin: Users ───
Route::group('/api/admin/users', function () {
    Route::get('/', 'admin.User/index');
    Route::post('/', 'admin.User/create');
    Route::put('/:id/lock', 'admin.User/lock');
    Route::put('/:id/unlock', 'admin.User/unlock');
    Route::put('/:id', 'admin.User/update');
})->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');

// ─── Appointments ───
// Coordinator/provider access is further narrowed at controller level (object-level auth).
Route::group('/api/appointments', function () {
    Route::get('/', 'appointment.Appointment/index')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR,PROVIDER');
    Route::post('/', 'appointment.Appointment/create')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR')
        ->middleware(ThrottleMiddleware::class, 'appointments_per_hour')
        ->middleware(StepUpMiddleware::class);
    Route::get('/:id', 'appointment.Appointment/read')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR,PROVIDER');
    Route::get('/:id/history', 'appointment.Appointment/history')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR,PROVIDER');
    Route::put('/:id/confirm', 'appointment.Appointment/confirm')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR');
    Route::put('/:id/reschedule', 'appointment.Appointment/reschedule')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR,SYSTEM_ADMIN');
    Route::put('/:id/cancel', 'appointment.Appointment/cancel')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR');
    Route::put('/:id/repair', 'appointment.Appointment/repair')
        ->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');
    Route::put('/:id/check-in', 'appointment.Appointment/checkIn')
        ->middleware(AuthMiddleware::class, 'PROVIDER');
    Route::put('/:id/check-out', 'appointment.Appointment/checkOut')
        ->middleware(AuthMiddleware::class, 'PROVIDER');
    Route::post('/:id/attachments', 'appointment.Appointment/uploadAttachment')
        ->middleware(AuthMiddleware::class, 'PROVIDER');
    Route::get('/:id/attachments', 'appointment.Appointment/listAttachments')
        ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR,PROVIDER');
});

// ─── Provider Queue ───
Route::group('/api/provider/queue', function () {
    Route::get('/', 'provider.Queue/index');
})->middleware(AuthMiddleware::class, 'PROVIDER');

// ─── Production: Work Centers ───
Route::group('/api/production/work-centers', function () {
    Route::get('/', 'production.WorkCenter/index');
    Route::get('/:id', 'production.WorkCenter/read');
    Route::post('/', 'production.WorkCenter/create');
    Route::put('/:id', 'production.WorkCenter/update');
    Route::delete('/:id', 'production.WorkCenter/delete');
})->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER');

// ─── Production: MPS ───
Route::group('/api/production/mps', function () {
    Route::get('/', 'production.Mps/index');
    Route::post('/', 'production.Mps/create');
    Route::put('/:id', 'production.Mps/update');
    Route::delete('/:id', 'production.Mps/delete');
})->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER');

// ─── Production: Work Orders ───
// Planner creates/explodes/reads; OPERATOR is the shop-floor role that starts
// and completes work orders. Repair is admin-only.
Route::group('/api/production/work-orders', function () {
    Route::get('/', 'production.WorkOrder/index')
        ->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER,OPERATOR');
    Route::get('/:id', 'production.WorkOrder/read')
        ->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER,OPERATOR');
    Route::get('/:id/history', 'production.WorkOrder/history')
        ->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER,OPERATOR');
    Route::post('/explode', 'production.WorkOrder/explode')
        ->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER');
    Route::put('/:id/start', 'production.WorkOrder/start')
        ->middleware(AuthMiddleware::class, 'OPERATOR,PRODUCTION_PLANNER');
    Route::put('/:id/complete', 'production.WorkOrder/complete')
        ->middleware(AuthMiddleware::class, 'OPERATOR,PRODUCTION_PLANNER');
    Route::put('/:id/repair', 'production.WorkOrder/repair')
        ->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');
});

// ─── Production: Capacity ───
Route::group('/api/production/capacity', function () {
    Route::get('/', 'production.Capacity/index');
})->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER');

// ─── Product Catalog ───
// Separation of duties: PRODUCT_SPECIALIST drafts + submits; CONTENT_MODERATOR
// reviews the queue in /api/moderation. A single role cannot both draft and
// approve. PRODUCTION_PLANNER / REVIEWER retain read-only access for cross-module lookups.
Route::group('/api/catalog/products', function () {
    Route::get('/', 'catalog.Product/index')
        ->middleware(AuthMiddleware::class, 'PRODUCT_SPECIALIST,CONTENT_MODERATOR,PRODUCTION_PLANNER,REVIEWER');
    Route::post('/', 'catalog.Product/create')
        ->middleware(AuthMiddleware::class, 'PRODUCT_SPECIALIST');
    Route::get('/duplicates', 'catalog.Product/duplicates')
        ->middleware(AuthMiddleware::class, 'CONTENT_MODERATOR');
    Route::get('/:id', 'catalog.Product/read')
        ->middleware(AuthMiddleware::class, 'PRODUCT_SPECIALIST,CONTENT_MODERATOR,PRODUCTION_PLANNER,REVIEWER');
    Route::put('/:id', 'catalog.Product/update')
        ->middleware(AuthMiddleware::class, 'PRODUCT_SPECIALIST');
    Route::post('/:id/submit', 'catalog.Product/submit')
        ->middleware(AuthMiddleware::class, 'PRODUCT_SPECIALIST');
});

// ─── Moderation ───
Route::group('/api/moderation', function () {
    Route::get('/pending', 'moderation.Moderation/pending');
    Route::post('/bulk-action', 'moderation.Moderation/bulkAction');
    Route::post('/merge-review', 'moderation.Moderation/mergeReview');
})->middleware(AuthMiddleware::class, 'CONTENT_MODERATOR');

// ─── Reviews ───
// Pool + assignment + scorecard + publish governance is REVIEW_MANAGER
// (SYSTEM_ADMIN inherits access). Review submission is reviewer-owned.
Route::group('/api/reviews', function () {
    Route::get('/reviewers', 'review.Review/listReviewers')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER,REVIEWER');
    Route::post('/reviewers', 'review.Review/createReviewer')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER');
    Route::get('/reviewers/:id/conflicts', 'review.Review/conflicts')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER,REVIEWER');
    Route::post('/assignments/auto', 'review.Review/autoAssign')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER');
    Route::get('/assignments', 'review.Review/listAssignments')
        ->middleware(AuthMiddleware::class, 'REVIEWER,REVIEW_MANAGER');
    Route::post('/assignments', 'review.Review/assign')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER');
    Route::get('/scorecards', 'review.Review/listScorecards')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER,REVIEWER');
    Route::post('/scorecards', 'review.Review/createScorecard')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER');
    Route::post('/submissions/:id/publish', 'review.Review/publish')
        ->middleware(AuthMiddleware::class, 'REVIEW_MANAGER');
    Route::post('/submissions', 'review.Review/submit')
        ->middleware(AuthMiddleware::class, 'REVIEWER');
});

// ─── Finance: Payments ───
// `import` is money-sensitive — wrap with StepUpMiddleware so a held account is
// blocked until an admin clears the flag.
Route::group('/api/finance/payments', function () {
    Route::get('/', 'finance.Payment/index');
    Route::get('/:id', 'finance.Payment/read');
    Route::post('/import', 'finance.Payment/import')
        ->middleware(StepUpMiddleware::class);
})->middleware(AuthMiddleware::class, 'FINANCE_CLERK');

// ─── Finance: Receipts ───
Route::group('/api/finance/receipts', function () {
    Route::get('/', 'finance.Receipt/index');
    Route::post('/callback', 'finance.Receipt/callback');
    Route::get('/:id/verify', 'finance.Receipt/verify');
    Route::put('/:id/bind', 'finance.Receipt/bind');
    Route::get('/:id', 'finance.Receipt/read');
})->middleware(AuthMiddleware::class, 'FINANCE_CLERK');

// ─── Finance: Reconciliation ───
Route::group('/api/finance/reconciliation', function () {
    Route::post('/run', 'finance.Reconciliation/run');
    Route::get('/anomalies', 'finance.Reconciliation/anomalies');
})->middleware(AuthMiddleware::class, 'FINANCE_CLERK');

// ─── Finance: Settlements ───
Route::group('/api/finance/settlements', function () {
    Route::get('/', 'finance.Settlement/index');
    Route::post('/', 'finance.Settlement/create')
        ->middleware(StepUpMiddleware::class);
    Route::get('/:id/report', 'finance.Settlement/report');
})->middleware(AuthMiddleware::class, 'FINANCE_CLERK');

// ─── Admin: Risk & Credit ───
Route::group('/api/admin/risk', function () {
    Route::get('/scores', 'admin.Risk/scores');
    Route::get('/ip-scores', 'admin.Risk/ipScores');
    Route::get('/flags', 'admin.Risk/flags');
    Route::put('/flags/:id/clear', 'admin.Risk/clearFlag');
    Route::get('/throttles', 'admin.Risk/throttles');
    Route::put('/throttles', 'admin.Risk/updateThrottles');
})->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');

// ─── Admin: Audit ───
Route::group('/api/admin/audit', function () {
    Route::get('/logs', 'admin.Audit/logs');
})->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');

// ─── Dashboard (role-aware; every authenticated role sees its slice) ───
Route::group('/api/dashboard', function () {
    Route::get('/', 'admin.Dashboard/index');
})->middleware(AuthMiddleware::class);

// Legacy admin-only alias retained so existing admin clients don't break.
Route::group('/api/admin/dashboard', function () {
    Route::get('/', 'admin.Dashboard/index');
})->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');

// ─── Frontend page routes ───
Route::get('/', 'Index/index');
Route::get('/login', 'page.Page/login');
Route::get('/dashboard', 'page.Page/dashboard')->middleware(AuthMiddleware::class);

// Role-guarded workspace pages — mirrored by the sidebar in view/layout/base.html.
Route::get('/appointments', 'page.Page/appointmentsIndex')
    ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR,PROVIDER');
Route::get('/appointments/create', 'page.Page/appointmentsCreate')
    ->middleware(AuthMiddleware::class, 'SERVICE_COORDINATOR');

Route::get('/provider/queue', 'page.Page/providerQueue')
    ->middleware(AuthMiddleware::class, 'PROVIDER');

Route::get('/production/mps', 'page.Page/productionMps')
    ->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER');
Route::get('/production/work-orders', 'page.Page/productionWorkOrders')
    ->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER,OPERATOR');
Route::get('/production/capacity', 'page.Page/productionCapacity')
    ->middleware(AuthMiddleware::class, 'PRODUCTION_PLANNER');

Route::get('/catalog/products', 'page.Page/catalogProducts')
    ->middleware(AuthMiddleware::class, 'PRODUCT_SPECIALIST,CONTENT_MODERATOR,PRODUCTION_PLANNER,REVIEWER');
Route::get('/moderation', 'page.Page/moderationIndex')
    ->middleware(AuthMiddleware::class, 'CONTENT_MODERATOR');

Route::get('/reviews/assignments', 'page.Page/reviewsAssignments')
    ->middleware(AuthMiddleware::class, 'REVIEWER,REVIEW_MANAGER');
Route::get('/reviews/scorecards', 'page.Page/reviewsScorecards')
    ->middleware(AuthMiddleware::class, 'REVIEWER,REVIEW_MANAGER');

Route::get('/finance/payments', 'page.Page/financePayments')
    ->middleware(AuthMiddleware::class, 'FINANCE_CLERK');
Route::get('/finance/receipts', 'page.Page/financeReceipts')
    ->middleware(AuthMiddleware::class, 'FINANCE_CLERK');
Route::get('/finance/reconciliation', 'page.Page/financeReconciliation')
    ->middleware(AuthMiddleware::class, 'FINANCE_CLERK');
Route::get('/finance/settlements', 'page.Page/financeSettlements')
    ->middleware(AuthMiddleware::class, 'FINANCE_CLERK');

Route::get('/admin/users', 'page.Page/adminUsers')
    ->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');
Route::get('/admin/risk', 'page.Page/adminRisk')
    ->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');
Route::get('/admin/audit', 'page.Page/adminAudit')
    ->middleware(AuthMiddleware::class, 'SYSTEM_ADMIN');
