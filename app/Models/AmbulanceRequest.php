<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmbulanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ambulance_id',
        'tipe_request',
        'lokasi_jemput',
        'lokasi_tujuan',
        'kondisi_pasien',
        'tanggal_request',
        'waktu_request',
        'metode_pembayaran',
        'total_biaya',
        'jarak_km',
        'status',
        'request_number',
        'dispatched_by',
        'admin_notes',
    ];

    protected $casts = [
        'tanggal_request' => 'date',
        'total_biaya' => 'decimal:2',
        'jarak_km' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ambulance()
    {
        return $this->belongsTo(Ambulance::class);
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(Admin::class, 'dispatched_by');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}