<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'appointment_id',
        'room_booking_id',
        'ambulance_request_id',
        'tipe_layanan',
        'amount',
        'metode_pembayaran',
        'payment_method',
        'transaction_id',
        'midtrans_transaction_id',
        'status',
        'paid_at',
        'receipt_url',
        'processed_by',
        'admin_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function roomBooking()
    {
        return $this->belongsTo(RoomBooking::class);
    }

    public function ambulanceRequest()
    {
        return $this->belongsTo(AmbulanceRequest::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }
}