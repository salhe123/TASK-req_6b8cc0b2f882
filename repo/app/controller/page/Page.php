<?php
declare(strict_types=1);

namespace app\controller\page;

use app\BaseController;

class Page extends BaseController
{
    public function login()
    {
        return view('auth/login');
    }

    public function dashboard()
    {
        return view('dashboard/index', [
            'title'      => 'Dashboard - Precision Portal',
            'breadcrumb' => 'Dashboard',
        ]);
    }

    // ─── Appointments workspace ───
    public function appointmentsIndex()
    {
        return view('appointments/index', [
            'title'      => 'Appointments - Precision Portal',
            'breadcrumb' => 'Appointments',
        ]);
    }

    public function appointmentsCreate()
    {
        return view('appointments/create', [
            'title'      => 'New Appointment - Precision Portal',
            'breadcrumb' => 'Appointments / New',
        ]);
    }

    // ─── Provider queue ───
    public function providerQueue()
    {
        return view('provider/queue', [
            'title'      => 'My Queue - Precision Portal',
            'breadcrumb' => 'Provider / Queue',
        ]);
    }

    // ─── Production ───
    public function productionMps()
    {
        return view('production/mps', [
            'title'      => 'MPS Schedule - Precision Portal',
            'breadcrumb' => 'Production / MPS',
        ]);
    }

    public function productionWorkOrders()
    {
        return view('production/work-orders', [
            'title'      => 'Work Orders - Precision Portal',
            'breadcrumb' => 'Production / Work Orders',
        ]);
    }

    public function productionCapacity()
    {
        return view('production/capacity', [
            'title'      => 'Capacity - Precision Portal',
            'breadcrumb' => 'Production / Capacity',
        ]);
    }

    // ─── Catalog + Moderation ───
    public function catalogProducts()
    {
        return view('catalog/products', [
            'title'      => 'Products - Precision Portal',
            'breadcrumb' => 'Catalog / Products',
        ]);
    }

    public function moderationIndex()
    {
        return view('moderation/index', [
            'title'      => 'Moderation Queue - Precision Portal',
            'breadcrumb' => 'Moderation',
        ]);
    }

    // ─── Reviews ───
    public function reviewsAssignments()
    {
        return view('reviews/assignments', [
            'title'      => 'My Reviews - Precision Portal',
            'breadcrumb' => 'Reviews / My Assignments',
        ]);
    }

    public function reviewsScorecards()
    {
        return view('reviews/scorecards', [
            'title'      => 'Scorecards - Precision Portal',
            'breadcrumb' => 'Reviews / Scorecards',
        ]);
    }

    // ─── Finance ───
    public function financePayments()
    {
        return view('finance/payments', [
            'title'      => 'Payments - Precision Portal',
            'breadcrumb' => 'Finance / Payments',
        ]);
    }

    public function financeReceipts()
    {
        return view('finance/receipts', [
            'title'      => 'Receipts - Precision Portal',
            'breadcrumb' => 'Finance / Receipts',
        ]);
    }

    public function financeReconciliation()
    {
        return view('finance/reconciliation', [
            'title'      => 'Reconciliation - Precision Portal',
            'breadcrumb' => 'Finance / Reconciliation',
        ]);
    }

    public function financeSettlements()
    {
        return view('finance/settlements', [
            'title'      => 'Settlements - Precision Portal',
            'breadcrumb' => 'Finance / Settlements',
        ]);
    }

    // ─── Admin ───
    public function adminUsers()
    {
        return view('admin/users', [
            'title'      => 'Users - Precision Portal',
            'breadcrumb' => 'Admin / Users',
        ]);
    }

    public function adminRisk()
    {
        return view('admin/risk', [
            'title'      => 'Risk & Credit - Precision Portal',
            'breadcrumb' => 'Admin / Risk',
        ]);
    }

    public function adminAudit()
    {
        return view('admin/audit', [
            'title'      => 'Audit Logs - Precision Portal',
            'breadcrumb' => 'Admin / Audit',
        ]);
    }
}
