<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'doctor_id',
        'tipe_layanan',
        'tanggal_appointment',
        'waktu_appointment',
        'keluhan',
        'metode_pembayaran',
        'status',
        'ticket_number',
        'total_biaya',
        'confirmed_by',
        'notes_admin',
    ];

    protected $casts = [
        'tanggal_appointment' => 'date',
        'total_biaya' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(Admin::class, 'confirmed_by');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function roomBooking()
    {
        return $this->hasOne(RoomBooking::class);
    }
}