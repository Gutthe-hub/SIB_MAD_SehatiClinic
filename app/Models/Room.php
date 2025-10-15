<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor_kamar',
        'tipe_kamar',
        'tarif_per_hari',
        'fasilitas',
        'status',
    ];

    protected $casts = [
        'tarif_per_hari' => 'decimal:2',
    ];

    public function roomBookings()
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function currentBooking()
    {
        return $this->hasOne(RoomBooking::class)
                    ->whereNotIn('status', ['cancelled', 'checkout'])
                    ->latest();
    }
}