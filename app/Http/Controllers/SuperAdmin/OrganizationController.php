<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::with('owner')
            ->withCount('users')
            ->latest()
            ->paginate(20);

        return view('superadmin.organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        return view('superadmin.organizations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'slug'           => ['required', 'string', 'max:60', 'unique:organizations,slug', 'regex:/^[a-z0-9\-]+$/'],
            'plan'           => ['required', 'in:' . implode(',', array_keys(Organization::$plans))],
            'owner_name'     => ['required', 'string', 'max:120'],
            'owner_email'    => ['required', 'email', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8'],
        ]);

        $owner = User::create([
            'name'     => $data['owner_name'],
            'email'    => $data['owner_email'],
            'password' => $data['owner_password'],
        ]);

        $org = Organization::create([
            'name'          => $data['name'],
            'slug'          => $data['slug'],
            'plan'          => $data['plan'],
            'status'        => 'trial',
            'owner_user_id' => $owner->id,
        ]);

        $org->users()->attach($owner->id, ['role' => 'owner']);

        return redirect()->route('superadmin.organizations.index')
            ->with('success', "Organização \"{$org->name}\" criada com sucesso.");
    }

    public function edit(Organization $organization): View
    {
        return view('superadmin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'plan'   => ['required', 'in:' . implode(',', array_keys(Organization::$plans))],
            'status' => ['required', 'in:' . implode(',', array_keys(Organization::$statuses))],
        ]);

        $organization->update($data);

        return redirect()->route('superadmin.organizations.index')
            ->with('success', "Organização \"{$organization->name}\" atualizada.");
    }
}
