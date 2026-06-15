<?php

// v1.6 — 2026-06-15 | Add refresh_interval_seconds (per-user dashboard auto-refresh rate) +
//                     getRefreshIntervalSeconds() helper enforcing a 30s minimum.
// v1.5 — 2026-06-03 | Add lastOnlineText() helper for human-readable last-seen time.
// v1.4 — 2026-06-02 | Add hidden_from_staff field for admin-controlled staff panel visibility.
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
        'hidden_from_staff',
        'refresh_interval_seconds',
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
            'hidden_from_staff' => 'boolean',
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

    public function isOnline(): bool         { return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5)); }

    /** Dashboard auto-refresh interval, enforcing a 30s floor regardless of stored value. */
    public function getRefreshIntervalSeconds(): int
    {
        return max(30, (int) $this->refresh_interval_seconds);
    }

    public function lastOnlineText(): string
    {
        if (! $this->last_seen_at) {
            return 'Never seen';
        }
        if ($this->isOnline()) {
            return 'Online now';
        }
        $secs = (int) $this->last_seen_at->diffInSeconds(now());
        if ($secs < 60)  return $secs . 's ago';
        $mins = (int) $this->last_seen_at->diffInMinutes(now());
        if ($mins < 60)  return $mins . 'm ago';
        $hrs  = (int) $this->last_seen_at->diffInHours(now());
        if ($hrs < 24)   return $hrs . 'h ago';
        $days = (int) $this->last_seen_at->diffInDays(now());
        return $days . 'd ago';
    }

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

    public function notificationHistory(): HasMany
    {
        return $this->hasMany(NotificationHistory::class);
    }
}
