<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\Audit;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Akun pengguna & peran. @2026-Alifiandra */
class UserController extends Controller
{
    public function index()
    {
        $users = User::where('tenant_id', tenancy()->tenantId())->with('branch')->orderBy('name')->get();
        $branches = Branch::orderBy('name')->get();
        $roles = config('fnb.roles');

        return view('settings.users', compact('users', 'branches', 'roles'));
    }

    public function store(Request $request)
    {
        $tenant = tenancy()->tenant();
        if (User::where('tenant_id', $tenant->id)->count() >= $tenant->max_users) {
            return back()->with('error', "Paket Anda dibatasi {$tenant->max_users} pengguna.");
        }
        $data = $this->validated($request);
        $data['tenant_id'] = $tenant->id;
        $user = User::create($data);
        Audit::log('user.created', $user, null, $user->only(['name', 'email', 'role', 'branch_id']));

        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->tenant_id === tenancy()->tenantId(), 404);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->id === $request->user()->id && ($data['role'] !== 'owner' || ! $data['is_active'])) {
            return back()->with('error', 'Anda tidak dapat menurunkan peran atau menonaktifkan akun sendiri.');
        }
        $before = $user->only(['name', 'email', 'role', 'branch_id', 'is_active']);
        $user->update($data);
        Audit::log('user.updated', $user, $before, $user->only(array_keys($before)));

        return back()->with('success', 'Pengguna disimpan.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys(config('fnb.roles')))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', tenancy()->tenantId())],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
