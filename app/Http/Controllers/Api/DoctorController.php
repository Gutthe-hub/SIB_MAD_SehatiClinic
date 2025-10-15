<?php

namespace App\Http\Controllers\Api;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorController extends BaseApiController
{
    public function index()
    {
        $doctors = Doctor::with(['appointments', 'createdBy'])
                        ->orderBy('created_at', 'desc')
                        ->get();
        
        return $this->sendResponse($doctors, 'Doctors retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'spesialis' => 'required|string|max:50',
            'phone' => 'nullable|string|max:15',
            'email' => 'nullable|email',
            'jadwal_praktik' => 'required|array',
            'tarif_konsultasi' => 'required|numeric|min:0',
            'status' => 'in:aktif,tidak_aktif',
            'created_by' => 'nullable|exists:admins,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $doctor = Doctor::create($request->all());

        return $this->sendResponse($doctor->load('createdBy'), 'Doctor created successfully', 201);
    }

    public function show($id)
    {
        $doctor = Doctor::with(['appointments', 'createdBy'])->find($id);

        if (!$doctor) {
            return $this->sendError('Doctor not found', [], 404);
        }

        return $this->sendResponse($doctor, 'Doctor retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return $this->sendError('Doctor not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:100',
            'spesialis' => 'sometimes|string|max:50',
            'phone' => 'sometimes|nullable|string|max:15',
            'email' => 'sometimes|nullable|email',
            'jadwal_praktik' => 'sometimes|array',
            'tarif_konsultasi' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:aktif,tidak_aktif',
            'created_by' => 'sometimes|nullable|exists:admins,id',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $doctor->update($request->all());

        return $this->sendResponse($doctor->fresh()->load('createdBy'), 'Doctor updated successfully');
    }

    public function destroy($id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return $this->sendError('Doctor not found', [], 404);
        }

        $doctor->delete();

        return $this->sendResponse([], 'Doctor deleted successfully');
    }
}