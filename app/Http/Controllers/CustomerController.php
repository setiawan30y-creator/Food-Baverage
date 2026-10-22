<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

/** @2026-Alifiandra */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::withCount('orders')->withSum(['orders as spent' => fn ($q) => $q->where('payment_status', 'paid')], 'total')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->orderByDesc('spent')->paginate(30)->withQueryString();

        return view('customers.index', compact('customers'));
    }
}
