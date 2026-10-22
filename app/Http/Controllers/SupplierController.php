<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

/** @2026-Alifiandra */
class SupplierController extends Controller
{
    public function index()
    {
        return view('purchasing.suppliers', ['suppliers' => Supplier::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return back()->with('success', 'Supplier ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return back()->with('success', 'Supplier disimpan.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ]) + ['lead_time_days' => 1];
    }
}
