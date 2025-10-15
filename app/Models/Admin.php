<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'nama',
        'phone',
        'password',
        'role',
        'department',
        'is_active',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'password' => 'hashed',
    ];

    public function createdDoctors()
    {
        return $this->hasMany(Doctor::class, 'created_by');
    }

    public function confirmedAppointments()
    {
        return $this->hasMany(Appointment::class, 'confirmed_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(AdminActivityLog::class);
    }
}