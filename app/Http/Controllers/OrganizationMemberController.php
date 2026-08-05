<?php

namespace App\Http\Controllers;

use App\Models\FunctionalRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationMemberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $org = app('currentOrganization');

        $validFunctionRoles = FunctionalRole::where('organization_id', $org->id)->pluck('key')->all();

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8'],
            'role'           => ['required', 'in:admin,manager,member'],
            'function_roles' => ['nullable', 'array'],
            'function_roles.*' => ['in:' . implode(',', $validFunctionRoles)],
            'avatar'         => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if ($request->hasFile('avatar')) {
            $disk = config('filesystems.default', 'r2');
            $path = $request->file('avatar')->store("avatars/{$user->id}", $disk);
            $user->update(['avatar_path' => $path, 'avatar_disk' => $disk]);
        }

        $org->users()->attach($user->id, [
            'id'   => Str::uuid(),
            'role' => $data['role'],
        ]);

        $this->syncFunctionalRoles($org->id, $user, $data['function_roles'] ?? []);

        return back()->with('success', 'Usuário criado e adicionado à organização.')->with('tab', 'equipe');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $org = app('currentOrganization');

        abort_unless($org->users()->where('user_id', $user->id)->exists(), 403);

        $validFunctionRoles = FunctionalRole::where('organization_id', $org->id)->pluck('key')->all();

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password'         => ['nullable', 'string', 'min:8'],
            'role'             => ['required', 'in:admin,manager,member'],
            'function_roles'   => ['nullable', 'array'],
            'function_roles.*' => ['in:' . implode(',', $validFunctionRoles)],
            'avatar'           => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
            'remove_avatar'    => ['nullable', 'boolean'],
        ]);

        $updateData = ['name' => $data['name'], 'email' => $data['email']];
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk($user->avatar_disk)->delete($user->avatar_path);
            }
            $disk = config('filesystems.default', 'r2');
            $updateData['avatar_path'] = $request->file('avatar')->store("avatars/{$user->id}", $disk);
            $updateData['avatar_disk'] = $disk;
        } elseif ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk($user->avatar_disk)->delete($user->avatar_path);
            $updateData['avatar_path'] = null;
            $updateData['avatar_disk'] = null;
        }

        $user->update($updateData);

        $org->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
        ]);

        $this->syncFunctionalRoles($org->id, $user, $data['function_roles'] ?? []);

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

    // Vincula um usuário órfão (existe em `users` mas não em nenhuma organização —
    // normalmente sobra de registro público de teste) à organização atual, virando
    // um membro de verdade. Alternativa ao destroyOrphan() abaixo.
    public function attach(Request $request, User $user): RedirectResponse
    {
        $org = app('currentOrganization');
        abort_if($org->users()->where('user_id', $user->id)->exists(), 422, 'Esse usuário já faz parte desta organização.');

        $data = $request->validate([
            'role' => ['required', 'in:admin,manager,member'],
        ]);

        $org->users()->attach($user->id, [
            'id'   => Str::uuid(),
            'role' => $data['role'],
        ]);

        return back()->with('success', 'Usuário adicionado à organização.')->with('tab', 'equipe');
    }

    // Exclusão definitiva de usuário órfão (nunca pertenceu a uma organização, então
    // "remover da equipe" não se aplica). Não usar em membro de verdade — pra isso é
    // destroy() acima (detach). Se o usuário tiver qualquer registro vinculado
    // (tarefa criada, planejamento, anexo etc.) a FK do Postgres bloqueia o delete —
    // não tentamos adivinhar isso na mão, só devolvemos o erro de forma legível.
    public function destroyOrphan(User $user): RedirectResponse
    {
        abort_if($user->organizations()->exists(), 422, 'Esse usuário pertence a uma organização — remova-o pela lista de Equipe.');
        abort_if($user->id === auth()->id(), 403);

        try {
            $user->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return back()
                ->withErrors(['orphan_delete' => 'Não foi possível excluir — esse usuário tem registros vinculados no sistema (tarefas, planejamentos, anexos etc).'])
                ->with('tab', 'equipe');
        }

        return back()->with('success', 'Usuário excluído.')->with('tab', 'equipe');
    }

    // sync() troca TODO o relacionamento do usuário (o pivot não tem organization_id) — pra
    // não apagar papéis que ele tenha em outra organização, preserva o que não é desta org e
    // só substitui a seleção desta.
    private function syncFunctionalRoles(string $organizationId, User $user, array $keys): void
    {
        $orgRoleIds = FunctionalRole::where('organization_id', $organizationId)->pluck('id');

        $selectedIds = FunctionalRole::where('organization_id', $organizationId)
            ->whereIn('key', $keys)
            ->pluck('id');

        $otherOrgsIds = $user->functionalRoles()
            ->whereNotIn('functional_roles.id', $orgRoleIds)
            ->pluck('functional_roles.id');

        $user->functionalRoles()->sync($otherOrgsIds->merge($selectedIds));
    }
}
