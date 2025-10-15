<?php

namespace App\Http\Controllers\Api;

use App\Models\Ambulance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AmbulanceController extends BaseApiController
{
    public function index()
    {
        $ambulances = Ambulance::with(['ambulanceRequests', 'currentRequest'])
                              ->orderBy('created_at', 'desc')
                              ->get();
        
        return $this->sendResponse($ambulances, 'Ambulances retrieved successfully');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nomor_plat' => 'required|string|max:15|unique:ambulances',
            'tipe_ambulance' => 'required|in:emergency,transport,icu',
            'tarif_base' => 'required|numeric|min:0',
            'tarif_per_km' => 'nullable|numeric|min:0',
            'driver_nama' => 'nullable|string|max:100',
            'driver_phone' => 'nullable|string|max:15',
            'current_location' => 'nullable|string',
            'status' => 'in:tersedia,beroperasi,maintenance',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $ambulance = Ambulance::create($request->all());

        return $this->sendResponse($ambulance, 'Ambulance created successfully', 201);
    }

    public function show($id)
    {
        $ambulance = Ambulance::with(['ambulanceRequests', 'currentRequest'])->find($id);

        if (!$ambulance) {
            return $this->sendError('Ambulance not found', [], 404);
        }

        return $this->sendResponse($ambulance, 'Ambulance retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $ambulance = Ambulance::find($id);

        if (!$ambulance) {
            return $this->sendError('Ambulance not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'nomor_plat' => 'sometimes|string|max:15|unique:ambulances,nomor_plat,' . $id,
            'tipe_ambulance' => 'sometimes|in:emergency,transport,icu',
            'tarif_base' => 'sometimes|numeric|min:0',
            'tarif_per_km' => 'sometimes|nullable|numeric|min:0',
            'driver_nama' => 'sometimes|nullable|string|max:100',
            'driver_phone' => 'sometimes|nullable|string|max:15',
            'current_location' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:tersedia,beroperasi,maintenance',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $ambulance->update($request->all());

        return $this->sendResponse($ambulance->fresh(), 'Ambulance updated successfully');
    }

    public function destroy($id)
    {
        $ambulance = Ambulance::find($id);

        if (!$ambulance) {
            return $this->sendError('Ambulance not found', [], 404);
        }

        $ambulance->delete();

        return $this->sendResponse([], 'Ambulance deleted successfully');
    }

    public function getAvailableAmbulances(Request $request)
    {
        $query = Ambulance::where('status', 'tersedia');

        if ($request->tipe_ambulance) {
            $query->where('tipe_ambulance', $request->tipe_ambulance);
        }

        if ($request->location) {
            // Simple location filtering - in real app, you'd use geospatial queries
            $query->where('current_location', 'like', '%' . $request->location . '%');
        }

        $ambulances = $query->orderBy('tarif_base', 'asc')->get();

        return $this->sendResponse($ambulances, 'Available ambulances retrieved successfully');
    }
}