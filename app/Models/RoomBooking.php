<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'appointment_id',
        'tanggal_checkin',
        'tanggal_checkout',
        'special_requests',
        'metode_pembayaran',
        'total_biaya',
        'status',
        'booking_number',
        'confirmed_by',
        'checkin_by',
        'checkout_by',
        'admin_notes',
    ];

    protected $casts = [
        'tanggal_checkin' => 'date',
        'tanggal_checkout' => 'date',
        'total_biaya' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(Admin::class, 'confirmed_by');
    }

    public function checkinBy()
    {
        return $this->belongsTo(Admin::class, 'checkin_by');
    }

    public function checkoutBy()
    {
        return $this->belongsTo(Admin::class, 'checkout_by');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}