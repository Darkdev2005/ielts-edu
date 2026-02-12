<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $query = User::query()
            ->orderByDesc('is_super_admin')
            ->orderByDesc('is_admin')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15)->withQueryString();

        return view('admin.admins.index', compact('users', 'search'));
    }

    public function toggleAdmin(Request $request, User $user)
    {
        if ($user->is_super_admin) {
            return back()->withErrors(['admin' => __('app.super_admin_protected')]);
        }

        if ($request->user()?->id === $user->id) {
            return back()->withErrors(['admin' => __('app.cannot_change_self')]);
        }

        $user->is_admin = !$user->is_admin;
        $user->save();

        return back()->with('status', $user->is_admin
            ? __('app.admin_granted')
            : __('app.admin_revoked'));
    }
}
