<?php

namespace App\Http\Controllers;

use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationMemberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $validFunctionRoles = array_keys(OrganizationUser::$functionRoles);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8'],
            'role'           => ['required', 'in:admin,manager,member'],
            'function_roles' => ['nullable', 'array'],
            'function_roles.*' => ['in:' . implode(',', $validFunctionRoles)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $org->users()->attach($user->id, [
            'id'             => Str::uuid(),
            'role'           => $data['role'],
            'function_roles' => json_encode($data['function_roles'] ?? []),
        ]);

        return back()->with('success', 'Usuário criado e adicionado à organização.')->with('tab', 'equipe');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $org = app('currentOrganization');

        abort_unless($org->users()->where('user_id', $user->id)->exists(), 403);

        $validFunctionRoles = array_keys(OrganizationUser::$functionRoles);

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password'         => ['nullable', 'string', 'min:8'],
            'role'             => ['required', 'in:admin,manager,member'],
            'function_roles'   => ['nullable', 'array'],
            'function_roles.*' => ['in:' . implode(',', $validFunctionRoles)],
        ]);

        $updateData = ['name' => $data['name'], 'email' => $data['email']];
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }
        $user->update($updateData);

        $org->users()->updateExistingPivot($user->id, [
            'role'           => $data['role'],
            'function_roles' => json_encode($data['function_roles'] ?? []),
        ]);

        return back()->with('success', 'Usuário atualizado.')->with('tab', 'equipe');
    }

    public function destroy(User $user): RedirectResponse
    {
        $org = app('currentOrganization');

        abort_if($org->owner_user_id === $user->id, 403, 'Não é possível remover o proprietário da organização.');
        abort_if($user->id === auth()->id(), 403, 'Você não pode remover a si mesmo.');
        abort_unless($org->users()->where('user_id', $user->id)->exists(), 403);

        $org->users()->detach($user->id);

        return back()->with('success', 'Membro removido da organização.')->with('tab', 'equipe');
    }
}
