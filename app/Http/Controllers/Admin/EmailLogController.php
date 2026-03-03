<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = EmailLog::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, fn ($q, $search) => $q->where('to', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Email/Log', [
            'logs' => EmailLogResource::collection($logs),
            'filters' => $request->only(['search', 'status']),
            'statuses' => EmailLog::distinct()->pluck('status')->sort()->values(),
        ]);
    }
}
