<?php

namespace App\Http\Controllers\Api;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppointmentController extends BaseApiController
{
    public function index()
    {
        $appointments = Appointment::with(['user', 'doctor', 'confirmedBy', 'payment'])
                                 ->orderBy('tanggal_appointment', 'desc')
                                 ->get();
        
        return $this->sendResponse($appointments, 'Appointments retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'doctor_id' => 'required|exists:doctors,id',
            'tipe_layanan' => 'required|in:rawat_jalan,rawat_darurat',
            'tanggal_appointment' => 'required|date|after_or_equal:today',
            'waktu_appointment' => 'required|date_format:H:i',
            'keluhan' => 'nullable|string',
            'metode_pembayaran' => 'required|in:bpjs,asuransi,mandiri',
            'total_biaya' => 'required|numeric|min:0',
            'confirmed_by' => 'nullable|exists:admins,id',
            'notes_admin' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        // Generate ticket number
        $today = now()->format('Ymd');
        $count = Appointment::whereDate('created_at', today())->count() + 1;
        $ticketNumber = 'APP' . $today . str_pad($count, 3, '0', STR_PAD_LEFT);

        $appointment = Appointment::create(array_merge($request->all(), [
            'ticket_number' => $ticketNumber,
            'status' => 'pending'
        ]));

        return $this->sendResponse($appointment->load(['user', 'doctor', 'confirmedBy']), 'Appointment created successfully', 201);
    }

    public function show($id)
    {
        $appointment = Appointment::with(['user', 'doctor', 'confirmedBy', 'payment', 'roomBooking'])
                                 ->find($id);

        if (!$appointment) {
            return $this->sendError('Appointment not found', [], 404);
        }

        return $this->sendResponse($appointment, 'Appointment retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return $this->sendError('Appointment not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'sometimes|exists:users,id',
            'doctor_id' => 'sometimes|exists:doctors,id',
            'tipe_layanan' => 'sometimes|in:rawat_jalan,rawat_darurat',
            'tanggal_appointment' => 'sometimes|date',
            'waktu_appointment' => 'sometimes|date_format:H:i',
            'keluhan' => 'sometimes|nullable|string',
            'metode_pembayaran' => 'sometimes|in:bpjs,asuransi,mandiri',
            'status' => 'sometimes|in:pending,confirmed,completed,cancelled',
            'total_biaya' => 'sometimes|numeric|min:0',
            'confirmed_by' => 'sometimes|nullable|exists:admins,id',
            'notes_admin' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $appointment->update($request->all());

        return $this->sendResponse($appointment->fresh()->load(['user', 'doctor', 'confirmedBy']), 'Appointment updated successfully');
    }

    public function destroy($id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return $this->sendError('Appointment not found', [], 404);
        }

        $appointment->delete();

        return $this->sendResponse([], 'Appointment deleted successfully');
    }
}