<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'spesialis',
        'phone',
        'email',
        'jadwal_praktik',
        'tarif_konsultasi',
        'status',
        'created_by',
    ];

    protected $casts = [
        'jadwal_praktik' => 'array',
        'tarif_konsultasi' => 'decimal:2',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}