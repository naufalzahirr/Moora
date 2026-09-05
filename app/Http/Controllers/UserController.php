<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserManagementRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::orderByDesc('active')->orderBy('name')->paginate(15),
        ]);
    }

    public function store(UserManagementRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $user = User::create($request->validated());
        $logger->log($request->user(), 'user.created', "Menambahkan pengguna {$user->username}.", ['user_id' => $user->id]);

        return back()->with('success', 'Pengguna baru berhasil ditambahkan.');
    }

    public function update(UserManagementRequest $request, User $user, ActivityLogger $logger): RedirectResponse
    {
        $payload = $request->validated();
        if (($payload['password'] ?? null) === null || $payload['password'] === '') {
            $payload = Arr::except($payload, 'password');
        }
        $this->guardOwnerAccount($user, $payload, $request->user()->id);

        $user->update($payload);
        $logger->log($request->user(), 'user.updated', "Memperbarui pengguna {$user->username}.", ['user_id' => $user->id]);

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user, ActivityLogger $logger): RedirectResponse
    {
        $this->guardOwnerAccount($user, ['active' => false], request()->user()->id);
        $user->update(['active' => false]);
        $logger->log(request()->user(), 'user.deactivated', "Menonaktifkan pengguna {$user->username}.", ['user_id' => $user->id]);

        return back()->with('success', 'Pengguna dinonaktifkan.');
    }

    /** @param array<string, mixed> $payload */
    private function guardOwnerAccount(User $user, array $payload, int $actorId): void
    {
        $nextRole = $payload['role'] ?? $user->role;
        $nextActive = (bool) ($payload['active'] ?? $user->active);
        if ($user->id === $actorId && ($nextRole !== 'owner' || ! $nextActive)) {
            throw ValidationException::withMessages(['user' => 'Owner yang sedang digunakan tidak dapat menurunkan peran atau menonaktifkan akunnya sendiri.']);
        }
        if ($user->role === 'owner' && $user->active && ($nextRole !== 'owner' || ! $nextActive)) {
            $hasAnotherOwner = User::query()
                ->where('role', 'owner')
                ->where('active', true)
                ->whereKeyNot($user->id)
                ->exists();
            if (! $hasAnotherOwner) {
                throw ValidationException::withMessages(['user' => 'Minimal harus ada satu akun Owner aktif.']);
            }
        }
    }
}
