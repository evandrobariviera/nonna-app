<?php

namespace App\Http\Controllers;

use App\Models\FeatureSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeatureSuggestionController extends Controller
{
    public function index()
    {
        $suggestions = FeatureSuggestion::with('createdBy')
            ->orderBy('done')
            ->orderByDesc('created_at')
            ->get();

        return view('feature-suggestions.index', compact('suggestions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:500',
            'description' => 'nullable|string',
        ]);

        FeatureSuggestion::create([
            ...$data,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('feature-suggestions.index');
    }

    public function updateStatus(Request $request, FeatureSuggestion $suggestion)
    {
        $data = $request->validate([
            'done' => 'required|boolean',
        ]);

        $suggestion->update([
            'done'    => $data['done'],
            'done_at' => $data['done'] ? now() : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }

    public function destroy(FeatureSuggestion $suggestion)
    {
        $suggestion->delete();

        return redirect()->route('feature-suggestions.index');
    }
}
