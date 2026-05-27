<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

class Permission
{
    public const ROLES = ['admin', 'editor', 'reader', 'writer', 'producer'];

    public const FEATURES = [
        'assignments'        => 'Assignments — View',
        'assignments.manage' => 'Assignments — Create / Edit / Delete',
        'qc'                 => 'QC Queue',
        'archive'            => 'Archive',
        'ratebook'           => 'Ratebook — View',
        'ratebook.edit'      => 'Ratebook — Edit Rates',
        'manual'             => 'Reader Manual — View',
        'manual.edit'        => 'Reader Manual — Edit',
        'team'               => 'Team — View',
        'readers.edit'       => 'Readers — Edit',
        'readers.delete'     => 'Readers — Delete',
        'editors.edit'       => 'Editors — Edit',
        'editors.delete'     => 'Editors — Delete',
    ];

    // Defaults used when no DB record exists yet
    public const DEFAULTS = [
        'assignments'        => ['admin' => true,  'editor' => true,  'reader' => true,  'writer' => false, 'producer' => false],
        'assignments.manage' => ['admin' => true,  'editor' => true,  'reader' => false, 'writer' => false, 'producer' => false],
        'qc'                 => ['admin' => true,  'editor' => true,  'reader' => false, 'writer' => false, 'producer' => false],
        'archive'            => ['admin' => true,  'editor' => true,  'reader' => false, 'writer' => false, 'producer' => false],
        'ratebook'           => ['admin' => true,  'editor' => true,  'reader' => false, 'writer' => false, 'producer' => false],
        'ratebook.edit'      => ['admin' => true,  'editor' => false, 'reader' => false, 'writer' => false, 'producer' => false],
        'manual'             => ['admin' => true,  'editor' => true,  'reader' => true,  'writer' => false, 'producer' => false],
        'manual.edit'        => ['admin' => true,  'editor' => false, 'reader' => false, 'writer' => false, 'producer' => false],
        'team'               => ['admin' => true,  'editor' => true,  'reader' => false, 'writer' => false, 'producer' => false],
        'readers.edit'       => ['admin' => true,  'editor' => true,  'reader' => false, 'writer' => false, 'producer' => false],
        'readers.delete'     => ['admin' => true,  'editor' => true,  'reader' => false, 'writer' => false, 'producer' => false],
        'editors.edit'       => ['admin' => true,  'editor' => false, 'reader' => false, 'writer' => false, 'producer' => false],
        'editors.delete'     => ['admin' => true,  'editor' => false, 'reader' => false, 'writer' => false, 'producer' => false],
    ];

    /** Settings key for a feature+role pair. */
    public static function settingKey(string $feature, string $role): string
    {
        return 'perm_' . $role . '_' . str_replace('.', '_', $feature);
    }

    /** Check whether the given (or currently authenticated) user has a feature permission. Admins always pass. */
    public static function check(string $feature, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) return false;
        if ($user->isAdmin()) return true;

        $role    = $user->role;
        $default = self::DEFAULTS[$feature][$role] ?? false;
        $stored  = Setting::getValue(self::settingKey($feature, $role));

        return $stored !== null ? (bool)(int)$stored : $default;
    }

    /** Load all permissions as [feature][role] => bool for the permissions UI. */
    public static function all(): array
    {
        $allKeys = [];
        foreach (array_keys(self::FEATURES) as $feature) {
            foreach (self::ROLES as $role) {
                if ($role === 'admin') continue;
                $allKeys[] = self::settingKey($feature, $role);
            }
        }

        $stored = Setting::whereIn('key', $allKeys)->pluck('value', 'key')->toArray();

        $grid = [];
        foreach (array_keys(self::FEATURES) as $feature) {
            $grid[$feature] = [];
            foreach (self::ROLES as $role) {
                if ($role === 'admin') {
                    $grid[$feature][$role] = true;
                    continue;
                }
                $key = self::settingKey($feature, $role);
                $grid[$feature][$role] = array_key_exists($key, $stored)
                    ? (bool)(int)$stored[$key]
                    : (self::DEFAULTS[$feature][$role] ?? false);
            }
        }

        return $grid;
    }
}
