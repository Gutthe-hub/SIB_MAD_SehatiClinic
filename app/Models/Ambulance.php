<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ambulance extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor_plat',
        'tipe_ambulance',
        'tarif_base',
        'tarif_per_km',
        'status',
        'driver_nama',
        'driver_phone',
        'current_location',
    ];

    protected $casts = [
        'tarif_base' => 'decimal:2',
        'tarif_per_km' => 'decimal:2',
    ];

    public function ambulanceRequests()
    {
        return $this->hasMany(AmbulanceRequest::class);
    }

    public function currentRequest()
    {
        return $this->hasOne(AmbulanceRequest::class)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->latest();
    }
}