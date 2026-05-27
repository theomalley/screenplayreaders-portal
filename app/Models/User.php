<?php

// v1.3 — 2026-05-24 | Remove dead isWriter() and isProducer() role helpers.
// v1.2 — 2026-05-24 | Add last_seen_at tracking and isOnline() helper.
// v1.1 — 2026-05-24 | Add editorProfile() relationship.
// v1.0 — 2026-05-16 | Initial scaffold: role enum, reader profile + assignment relationships

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // --- Role helpers ---

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isOnline(): bool         { return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinute()); }

    public function isAdmin(): bool          { return $this->role === 'admin'; }
    public function isEditor(): bool         { return $this->role === 'editor'; }
    public function isReader(): bool         { return $this->role === 'reader'; }
    public function isAdminOrEditor(): bool  { return $this->hasAnyRole(['admin', 'editor']); }

    // Admin and editor have the same assignment-management privileges
    public function canManageAssignments(): bool
    {
        return $this->hasAnyRole(['admin', 'editor']);
    }

    // --- Relationships ---

    public function readerProfile(): HasOne
    {
        return $this->hasOne(ReaderProfile::class);
    }

    public function editorProfile(): HasOne
    {
        return $this->hasOne(EditorProfile::class);
    }

    /** Assignments this user has accepted as reader */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assigned_reader_id');
    }

    /** Assignments where a customer specifically requested this reader */
    public function requestedAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'requested_reader_id');
    }
}
