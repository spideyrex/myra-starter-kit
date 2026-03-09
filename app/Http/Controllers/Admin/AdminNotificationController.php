<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use App\Notifications\SystemNotification;
use App\Notifications\UserActionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $paginator = DatabaseNotification::query()
            ->where('notifiable_type', 'App\\Models\\User')
            ->with('notifiable:id,name,email')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('data', 'like', "%{$search}%")
                        ->orWhereHasMorph('notifiable', [User::class], function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->type, function ($q, $type) {
                $q->where('data->type', $type);
            })
            ->when($request->status, function ($q, $status) {
                if ($status === 'read') {
                    $q->whereNotNull('read_at');
                } elseif ($status === 'unread') {
                    $q->whereNull('read_at');
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $notifications = [
            'data' => $paginator->through(function (DatabaseNotification $notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? 'system',
                    'title' => $notification->data['title'] ?? '',
                    'message' => $notification->data['message'] ?? '',
                    'action_url' => $notification->data['action_url'] ?? null,
                    'user_name' => $notification->notifiable?->name ?? 'Deleted User',
                    'user_email' => $notification->notifiable?->email ?? '',
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            })->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'links' => $paginator->linkCollection()->toArray(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'filters' => $request->only(['search', 'type', 'status']),
        ]);
    }

    public function create(): Response
    {
        $users = User::select('id', 'name', 'email')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Notifications/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['system', 'user_action', 'security_alert'])],
            'target' => ['required', Rule::in(['all', 'specific'])],
            'user_ids' => ['required_if:target,specific', 'nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'action_url' => ['nullable', 'url', 'max:255'],
            'send_push' => ['nullable', 'boolean'],
        ]);

        $recipients = $validated['target'] === 'all'
            ? User::where('status', 'active')->get()
            : User::whereIn('id', $validated['user_ids'])->get();

        if ($recipients->isEmpty()) {
            return back()->with('error', 'No recipients found.');
        }

        $notification = match ($validated['type']) {
            'system' => new SystemNotification(
                title: $validated['title'],
                message: $validated['message'],
                actionUrl: $validated['action_url'] ?? '',
            ),
            'user_action' => new UserActionNotification(
                title: $validated['title'],
                message: $validated['message'],
                actionUrl: $validated['action_url'] ?? '',
                performedBy: $request->user()->name,
            ),
            'security_alert' => new SecurityAlertNotification(
                title: $validated['title'],
                message: $validated['message'],
                actionUrl: $validated['action_url'] ?? '',
            ),
        };

        $notification->sendPush = $request->boolean('send_push');

        Notification::send($recipients, $notification);

        $count = $recipients->count();

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', "Notification sent to {$count} " . str('user')->plural($count) . '.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string',
            'action' => 'required|in:mark_read,delete',
        ]);

        $notifications = DatabaseNotification::whereIn('id', $request->ids);

        if ($request->action === 'mark_read') {
            $notifications->whereNull('read_at')->update(['read_at' => now()]);
            return back()->with('success', 'Selected notifications marked as read.');
        }

        $notifications->delete();
        return back()->with('success', 'Selected notifications deleted.');
    }
}
