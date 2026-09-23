<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['settings' => AppSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'support_email' => ['nullable', 'email', 'max:255'],
            'announcement_banner' => ['nullable', 'string', 'max:500'],
        ]);

        $data['maintenance_mode'] = $request->boolean('maintenance_mode');

        AppSetting::current()->update($data);

        return back()->with('status', 'Settings saved.');
    }
}
