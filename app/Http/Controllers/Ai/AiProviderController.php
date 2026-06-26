<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AiApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiProviderController extends Controller
{
    public function index()
    {
        $providers = AiProvider::with(['apiKeys'])->orderBy('name')->get();
        return view('ai.providers.index', compact('providers'));
    }

    // Adicionar chave a um provider
    public function storeKey(Request $request, AiProvider $provider)
    {
        $data = $request->validate([
            'label'   => 'required|string|max:100',
            'api_key' => 'required|string',
        ]);

        $key = new AiApiKey();
        $key->provider_id = $provider->id;
        $key->label = $data['label'];
        $key->setApiKeyAttribute($data['api_key']);
        $key->created_by = Auth::id();
        $key->save();

        return back()->with('success', 'Chave adicionada com sucesso.');
    }

    public function destroyKey(AiProvider $provider, AiApiKey $key)
    {
        $key->delete();
        return back()->with('success', 'Chave removida.');
    }

    public function toggleKey(AiProvider $provider, AiApiKey $key)
    {
        $key->update(['is_active' => !$key->is_active]);
        return back();
    }
}
