<?php

// v1.1 — 2026-07-23 | Authorization moved to the manage-permissions Gate ability (AppServiceProvider)

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Permission;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    public function index()
    {
        $this->authorize('manage-permissions');

        $grid = Permission::all();

        return view('admin.permissions', compact('grid'));
    }

    public function update(Request $request)
    {
        $this->authorize('manage-permissions');

        foreach (array_keys(Permission::FEATURES) as $feature) {
            foreach (Permission::ROLES as $role) {
                if ($role === 'admin') continue;
                $inputName = 'perm_' . $role . '_' . str_replace('.', '_', $feature);
                Setting::setValue(Permission::settingKey($feature, $role), $request->has($inputName) ? '1' : '0');
            }
        }

        return back()->with('success', 'Permissions saved.');
    }
}
