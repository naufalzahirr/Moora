<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $selectedAction = $request->string('action')->toString();
        $search = trim($request->string('q')->toString());
        $selectedUser = $request->integer('user');
        $from = $request->string('from')->toString();
        $until = $request->string('until')->toString();
        $query = ActivityLog::with('user')->latest('id');
        if ($selectedAction !== '') {
            $query->where('action', $selectedAction);
        }
        if ($selectedUser) {
            $query->where('user_id', $selectedUser);
        }
        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('action', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($until !== '') {
            $query->whereDate('created_at', '<=', $until);
        }

        return view('activity-logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'actions' => ActivityLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->mapWithKeys(fn (string $action): array => [$action => (new ActivityLog(['action' => $action]))->actionLabel()]),
            'users' => User::query()->whereIn('id', ActivityLog::query()->select('user_id')->whereNotNull('user_id'))->orderBy('name')->get(),
            'selectedAction' => $selectedAction,
            'selectedUser' => $selectedUser,
            'search' => $search,
            'from' => $from,
            'until' => $until,
        ]);
    }
}
