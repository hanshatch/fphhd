<?php

namespace App\Http\Controllers;

use App\Services\ScheduledService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ScheduledController extends Controller
{
    public function __construct(private ScheduledService $service) {}

    public function index(Request $request): View
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();

        return view('pages.scheduled.index', $this->service->monthCalendar($month));
    }
}
