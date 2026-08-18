<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_LABELS = [
        'reporter' => 'Registrador',
        'coordinator' => 'Coordinador',
        'admin' => 'Administrador',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'profile_photo_path',
        'password',
        'role',
        'countrywide_access',
        'is_active',
        'can_mark_reported',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_mark_reported' => 'boolean',
            'countrywide_access' => 'boolean',
        ];
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo_path && is_file(public_path($this->profile_photo_path))) {
            return asset($this->profile_photo_path).'?v='.(@filemtime(public_path($this->profile_photo_path)) ?: 1);
        }

        return asset('assets/images/users/user-dummy-img.jpg');
    }

    public function beneficiaries()
    {
        return $this->hasManyThrough(Beneficiary::class, Report::class);
    }

    public function assignedStates()
    {
        return $this->belongsToMany(State::class, 'state_user');
    }

    public function assignedMunicipalities()
    {
        return $this->belongsToMany(Municipality::class, 'municipality_user');
    }

    public function projects()
    {
        return $this->belongsToMany(Proyecto::class, 'proyecto_user')->withTimestamps();
    }

    public function constrainVisibleReports(Builder $query): Builder
    {
        if ($this->role === 'reporter') {
            return $query->where('user_id', $this->id);
        }

        if ($this->isAdministrator() || $this->countrywide_access) {
            return $query;
        }

        $stateIds = $this->assignedStates()->pluck('states.id');
        $municipalityIds = $this->assignedMunicipalities()->pluck('municipalities.id');

        if ($stateIds->isEmpty() && $municipalityIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $locations) use ($stateIds, $municipalityIds): void {
            $locations->whereIn('state_id', $stateIds)
                ->orWhereIn('municipality_id', $municipalityIds);
        });
    }

    public function canViewReport(Report $report): bool
    {
        if ($this->role === 'reporter') {
            return $report->user_id === $this->id;
        }

        if ($this->isAdministrator() || $this->countrywide_access) {
            return true;
        }

        $hasAssignments = $this->assignedStates()->exists() || $this->assignedMunicipalities()->exists();
        if (! $hasAssignments) {
            return false;
        }

        return (
            $this->assignedStates()->whereKey($report->state_id)->exists()
            || $this->assignedMunicipalities()->whereKey($report->municipality_id)->exists()
        );
    }

    public function canAccessLocation(int $stateId, int $municipalityId): bool
    {
        if ($this->isAdministrator() || $this->countrywide_access) {
            return true;
        }

        $this->loadMissing(['assignedStates:id', 'assignedMunicipalities:id']);

        return $this->assignedStates->contains('id', $stateId)
            || $this->assignedMunicipalities->contains('id', $municipalityId);
    }

    public function isCoordinator(): bool
    {
        return in_array($this->role, ['coordinator', 'admin'], true);
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'admin';
    }

    public function canMarkAsReported(): bool
    {
        return $this->isAdministrator() || $this->can_mark_reported;
    }

    /** @return array<string, string> */
    public static function roleLabels(): array
    {
        return self::ROLE_LABELS;
    }
}
