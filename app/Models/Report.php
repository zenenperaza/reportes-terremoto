<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'report_date', 'reporter_first_name', 'reporter_last_name', 'reporter_email',
        'organization', 'other_organization', 'state_id', 'municipality_id', 'parish_id',
        'installation_type', 'place_name', 'latitude', 'longitude', 'altitude', 'gps_accuracy',
        'sector_id', 'activity_id', 'activity_details', 'recurrence_status', 'total_beneficiaries',
        'beneficiary_breakdown', 'people_with_disabilities', 'indigenous_people',
        'pregnant_or_lactating_women', 'qualitative_notes', 'status', 'reviewed_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'beneficiary_breakdown' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function state() { return $this->belongsTo(State::class); }
    public function municipality() { return $this->belongsTo(Municipality::class); }
    public function parish() { return $this->belongsTo(Parish::class); }
    public function sector() { return $this->belongsTo(Sector::class); }
    public function activity() { return $this->belongsTo(Activity::class); }
    public function evidences() { return $this->hasMany(Evidence::class)->orderBy('slot'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
