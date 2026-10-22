<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;

/** Pengaturan tema tenant + preferensi mode per pengguna. @2026-Alifiandra */
class ThemeController extends Controller
{
    public function edit()
    {
        $theme = ThemeSetting::firstOrCreate([]);

        return view('settings.theme', ['themeSetting' => $theme, 'presets' => config('fnb.theme_presets')]);
    }

    public function update(Request $request)
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $data = $request->validate([
            'mode' => ['required', 'in:light,dark,system'],
            'preset' => ['nullable', 'string', 'max:30'],
            'primary' => $hex, 'accent' => $hex, 'background' => $hex,
            'surface' => $hex, 'text' => $hex, 'border' => $hex,
            'font' => ['required', 'in:Plus Jakarta Sans,Inter,Nunito,Poppins,System'],
        ]);
        ThemeSetting::firstOrCreate([])->update($data);

        return back()->with('success', 'Tema disimpan.');
    }

    public function reset()
    {
        $p = config('fnb.theme_presets.terracotta');
        ThemeSetting::firstOrCreate([])->update($p + ['mode' => 'system', 'preset' => 'terracotta', 'font' => 'Plus Jakarta Sans']);

        return back()->with('success', 'Tema dikembalikan ke bawaan.');
    }

    public function userMode(Request $request)
    {
        $data = $request->validate(['theme_mode' => ['nullable', 'in:light,dark,system']]);
        $request->user()->update(['theme_mode' => $data['theme_mode'] ?: null]);

        return back();
    }
}
