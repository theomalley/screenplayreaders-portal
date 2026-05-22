<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Permission;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $grid = Permission::all();

        return view('admin.permissions', compact('grid'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

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
