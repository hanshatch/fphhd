<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    public function index(Request $request): View
    {
        $type      = $request->get('type', 'annual');
        $year      = (int) $request->get('year', now()->year);
        $month     = $request->get('month', now()->format('Y-m'));
        $accountId = $request->get('account_id');

        $data = match ($type) {
            'categories' => $this->service->categories($month, $accountId),
            'sources'    => $this->service->sources($month, $accountId),
            default      => $this->service->annual($year, $accountId),
        };

        return view('pages.reports.index', array_merge($data, [
            'type'      => $type,
            'year'      => $year,
            'month'     => $month,
            'accounts'  => Account::where('is_active', true)->orderBy('name')->get(),
            'accountId' => $accountId,
        ]));
    }
}
