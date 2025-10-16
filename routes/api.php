<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomBookingController;
use App\Http\Controllers\Api\AmbulanceController;
use App\Http\Controllers\Api\AmbulanceRequestController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Base route untuk testing
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Healthcare Hub API is working!',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

// Dashboard statistics
Route::get('/dashboard', function () {
    return response()->json([
        'success' => true,
        'message' => 'Dashboard data',
        'data' => [
            'total_users' => \App\Models\User::count(),
            'total_appointments' => \App\Models\Appointment::count(),
            'total_rooms' => \App\Models\Room::count(),
            'available_rooms' => \App\Models\Room::where('status', 'tersedia')->count(),
            'total_ambulances' => \App\Models\Ambulance::count(),
            'available_ambulances' => \App\Models\Ambulance::where('status', 'tersedia')->count(),
            'today_appointments' => \App\Models\Appointment::whereDate('tanggal_appointment', today())->count(),
            'pending_payments' => \App\Models\Payment::where('status', 'pending')->count(),
        ]
    ]);
});

// User Management Routes
Route::apiResource('users', UserController::class);

// Admin Management Routes
Route::apiResource('admins', AdminController::class);

// Doctor Management Routes
Route::apiResource('doctors', DoctorController::class);

// Appointment Management Routes
Route::apiResource('appointments', AppointmentController::class);

// Room Management Routes
Route::get('rooms/available/search', [RoomController::class, 'getAvailableRooms']);
Route::apiResource('rooms', RoomController::class);

// Room Booking Management Routes
Route::apiResource('room-bookings', RoomBookingController::class);

// Ambulance Management Routes
Route::get('ambulances/available/search', [AmbulanceController::class, 'getAvailableAmbulances']);
Route::apiResource('ambulances', AmbulanceController::class);

// Ambulance Request Management Routes
Route::apiResource('ambulance-requests', AmbulanceRequestController::class);

// Payment Management Routes
Route::apiResource('payments', PaymentController::class);
Route::post('payments/{id}/confirm', [PaymentController::class, 'confirmPayment']);

// Notification Management Routes
Route::apiResource('notifications', NotificationController::class);
Route::post('notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);

// Bulk operations
Route::post('notifications/mark-all-read/{userId}', [NotificationController::class, 'markAllAsRead']);

// Search and filter routes
Route::get('search/appointments', function(Request $request) {
    $query = \App\Models\Appointment::with(['user', 'doctor']);

    if ($request->status) {
        $query->where('status', $request->status);
    }

    if ($request->date) {
        $query->whereDate('tanggal_appointment', $request->date);
    }

    if ($request->doctor_id) {
        $query->where('doctor_id', $request->doctor_id);
    }

    $appointments = $query->orderBy('tanggal_appointment', 'desc')->get();

    return response()->json([
        'success' => true,
        'message' => 'Filtered appointments berhasil diambil',
        'data' => $appointments
    ]);
});

Route::get('search/payments', function(Request $request) {
    $query = \App\Models\Payment::with(['user']);

    if ($request->status) {
        $query->where('status', $request->status);
    }

    if ($request->metode_pembayaran) {
        $query->where('metode_pembayaran', $request->metode_pembayaran);
    }

    if ($request->tipe_layanan) {
        $query->where('tipe_layanan', $request->tipe_layanan);
    }

    $payments = $query->orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'message' => 'Filtered payments berhasil diambil',
        'data' => $payments
    ]);
});

// Reports
Route::get('reports/daily', function() {
    $today = today();

    return response()->json([
        'success' => true,
        'message' => 'Daily report',
        'data' => [
            'date' => $today->format('Y-m-d'),
            'appointments' => [
                'total' => \App\Models\Appointment::whereDate('tanggal_appointment', $today)->count(),
                'confirmed' => \App\Models\Appointment::whereDate('tanggal_appointment', $today)->where('status', 'confirmed')->count(),
                'completed' => \App\Models\Appointment::whereDate('tanggal_appointment', $today)->where('status', 'completed')->count(),
                'cancelled' => \App\Models\Appointment::whereDate('tanggal_appointment', $today)->where('status', 'cancelled')->count(),
            ],
            'payments' => [
                'total_amount' => \App\Models\Payment::whereDate('created_at', $today)->where('status', 'paid')->sum('amount'),
                'total_transactions' => \App\Models\Payment::whereDate('created_at', $today)->count(),
                'paid' => \App\Models\Payment::whereDate('created_at', $today)->where('status', 'paid')->count(),
                'pending' => \App\Models\Payment::whereDate('created_at', $today)->where('status', 'pending')->count(),
            ],
            'rooms' => [
                'occupied' => \App\Models\Room::where('status', 'terisi')->count(),
                'available' => \App\Models\Room::where('status', 'tersedia')->count(),
                'maintenance' => \App\Models\Room::where('status', 'maintenance')->count(),
            ]
        ]
    ]);
});

// Health check
Route::get('health', function() {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'timestamp' => now(),
        'database' => 'connected',
        'environment' => app()->environment(),
    ]);
});