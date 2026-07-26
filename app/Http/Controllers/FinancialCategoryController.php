<?php

namespace App\Http\Controllers;

use App\Models\FinancialCategory;
use Illuminate\Http\Request;

class FinancialCategoryController extends Controller
{
    public function index()
    {
        $categories = FinancialCategory::orderBy('name')->get()->groupBy('type');

        return view('financial.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'  => 'required|in:' . implode(',', array_keys(FinancialCategory::$types)),
            'name'  => 'required|string|max:60',
            'color' => 'nullable|string|max:20',
        ]);

        FinancialCategory::create($data);

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, FinancialCategory $financialCategory)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:60',
            'color'     => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $financialCategory->update($data);

        return back()->with('success', 'Categoria atualizada.');
    }
}
