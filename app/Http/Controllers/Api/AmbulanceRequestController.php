<?php

namespace App\Http\Controllers\Api;

use App\Models\AmbulanceRequest;
use App\Models\Ambulance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AmbulanceRequestController extends BaseApiController
{
    /**
     * Display a listing of ambulance requests
     */
    public function index(Request $request)
    {
        $query = AmbulanceRequest::with(['user', 'ambulance', 'dispatchedBy', 'payment']);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->tipe_request) {
            $query->where('tipe_request', $request->tipe_request);
        }

        // Filter by user
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('tanggal_request', [$request->start_date, $request->end_date]);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return $this->sendResponse($requests, 'Ambulance requests berhasil diambil');
    }

    /**
     * Store a newly created ambulance request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'ambulance_id' => 'nullable|exists:ambulances,id',
            'tipe_request' => 'required|in:emergency,scheduled',
            'lokasi_jemput' => 'required|string',
            'lokasi_tujuan' => 'required|string',
            'kondisi_pasien' => 'nullable|string',
            'tanggal_request' => 'required|date',
            'waktu_request' => 'nullable|date_format:H:i',
            'metode_pembayaran' => 'required|in:bpjs,asuransi,mandiri',
            'total_biaya' => 'nullable|numeric|min:0',
            'jarak_km' => 'nullable|numeric|min:0',
            'dispatched_by' => 'nullable|exists:admins,id',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        // For emergency requests, auto-assign available ambulance
        $ambulanceId = $request->ambulance_id;
        $initialStatus = 'pending';

        if ($request->tipe_request === 'emergency') {
            if (!$ambulanceId) {
                // Auto-assign closest available emergency ambulance
                $availableAmbulance = Ambulance::where('status', 'tersedia')
                                               ->where('tipe_ambulance', 'emergency')
                                               ->orderBy('id') // In real app, sort by distance
                                               ->first();

                if ($availableAmbulance) {
                    $ambulanceId = $availableAmbulance->id;
                    $initialStatus = 'dispatched';

                    // Update ambulance status
                    $availableAmbulance->update(['status' => 'beroperasi']);

                    // Auto-calculate estimated cost for emergency
                    if (!$request->total_biaya) {
                        $estimatedDistance = 10; // Default 10km for emergency
                        $estimatedCost = $availableAmbulance->tarif_base + ($estimatedDistance * $availableAmbulance->tarif_per_km);
                        $request->merge(['total_biaya' => $estimatedCost, 'jarak_km' => $estimatedDistance]);
                    }
                } else {
                    return $this->sendError('No emergency ambulance available at the moment', [], 400);
                }
            } else {
                // Verify selected ambulance is available
                $selectedAmbulance = Ambulance::find($ambulanceId);
                if ($selectedAmbulance->status !== 'tersedia') {
                    return $this->sendError('Selected ambulance is not available', [], 400);
                }
                $selectedAmbulance->update(['status' => 'beroperasi']);
                $initialStatus = 'dispatched';
            }
        } else {
            // Scheduled request - validate future date
            if ($request->tanggal_request <= now()->format('Y-m-d')) {
                return $this->sendError('Scheduled requests must be for future dates', [], 400);
            }
        }

        // Generate request number
        $today = now()->format('Ymd');
        $count = AmbulanceRequest::whereDate('created_at', today())->count() + 1;
        $requestNumber = 'AMB' . $today . str_pad($count, 3, '0', STR_PAD_LEFT);

        $ambulanceRequest = AmbulanceRequest::create([
            'user_id' => $request->user_id,
            'ambulance_id' => $ambulanceId,
            'tipe_request' => $request->tipe_request,
            'lokasi_jemput' => $request->lokasi_jemput,
            'lokasi_tujuan' => $request->lokasi_tujuan,
            'kondisi_pasien' => $request->kondisi_pasien,
            'tanggal_request' => $request->tanggal_request,
            'waktu_request' => $request->waktu_request,
            'metode_pembayaran' => $request->metode_pembayaran,
            'total_biaya' => $request->total_biaya,
            'jarak_km' => $request->jarak_km,
            'status' => $initialStatus,
            'request_number' => $requestNumber,
            'dispatched_by' => $request->dispatched_by,
            'admin_notes' => $request->admin_notes,
        ]);

        return $this->sendResponse(
            $ambulanceRequest->load(['user', 'ambulance', 'dispatchedBy']), 
            'Ambulance request berhasil dibuat', 
            201
        );
    }

    /**
     * Display the specified ambulance request
     */
    public function show($id)
    {
        $request = AmbulanceRequest::with(['user', 'ambulance', 'dispatchedBy', 'payment'])
                                  ->find($id);

        if (!$request) {
            return $this->sendError('Ambulance request tidak ditemukan', [], 404);
        }

        return $this->sendResponse($request, 'Ambulance request berhasil diambil');
    }

    /**
     * Update the specified ambulance request
     */
    public function update(Request $request, $id)
    {
        $ambulanceRequest = AmbulanceRequest::find($id);

        if (!$ambulanceRequest) {
            return $this->sendError('Ambulance request tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'ambulance_id' => 'sometimes|nullable|exists:ambulances,id',
            'lokasi_jemput' => 'sometimes|string',
            'lokasi_tujuan' => 'sometimes|string',
            'kondisi_pasien' => 'sometimes|nullable|string',
            'tanggal_request' => 'sometimes|date',
            'waktu_request' => 'sometimes|nullable|date_format:H:i',
            'metode_pembayaran' => 'sometimes|in:bpjs,asuransi,mandiri',
            'status' => 'sometimes|in:pending,dispatched,on_way,arrived,completed,cancelled',
            'total_biaya' => 'sometimes|nullable|numeric|min:0',
            'jarak_km' => 'sometimes|nullable|numeric|min:0',
            'dispatched_by' => 'sometimes|nullable|exists:admins,id',
            'admin_notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $oldStatus = $ambulanceRequest->status;
        $oldAmbulanceId = $ambulanceRequest->ambulance_id;

        // Update the request
        $ambulanceRequest->update($request->all());

        $newStatus = $ambulanceRequest->fresh()->status;
        $newAmbulanceId = $ambulanceRequest->ambulance_id;

        // Handle ambulance status changes
        if ($oldAmbulanceId && $oldAmbulanceId !== $newAmbulanceId) {
            // Free up old ambulance
            Ambulance::find($oldAmbulanceId)->update(['status' => 'tersedia']);
        }

        if ($newAmbulanceId && $oldStatus !== $newStatus) {
            $ambulance = Ambulance::find($newAmbulanceId);
            if (in_array($newStatus, ['dispatched', 'on_way', 'arrived'])) {
                $ambulance->update(['status' => 'beroperasi']);
            } elseif (in_array($newStatus, ['completed', 'cancelled'])) {
                $ambulance->update(['status' => 'tersedia']);
            }
        }

        // Auto-calculate final cost when completed
        if ($newStatus === 'completed' && $ambulanceRequest->jarak_km && $ambulanceRequest->ambulance) {
            $ambulance = $ambulanceRequest->ambulance;
            $finalCost = $ambulance->tarif_base + ($ambulanceRequest->jarak_km * $ambulance->tarif_per_km);
            $ambulanceRequest->update(['total_biaya' => $finalCost]);
        }

        return $this->sendResponse(
            $ambulanceRequest->fresh()->load(['user', 'ambulance', 'dispatchedBy']), 
            'Ambulance request berhasil diperbarui'
        );
    }

    /**
     * Remove the specified ambulance request
     */
    public function destroy($id)
    {
        $request = AmbulanceRequest::find($id);

        if (!$request) {
            return $this->sendError('Ambulance request tidak ditemukan', [], 404);
        }

        // Free up the ambulance if it's assigned
        if ($request->ambulance && in_array($request->status, ['dispatched', 'on_way', 'arrived'])) {
            $request->ambulance->update(['status' => 'tersedia']);
        }

        $request->delete();

        return $this->sendResponse([], 'Ambulance request berhasil dihapus');
    }

    /**
     * Dispatch ambulance (for admin use)
     */
    public function dispatchAmbulance(Request $request, $id)
    {
        $ambulanceRequest = AmbulanceRequest::find($id);

        if (!$ambulanceRequest) {
            return $this->sendError('Ambulance request tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'ambulance_id' => 'required|exists:ambulances,id',
            'dispatched_by' => 'required|exists:admins,id',
            'admin_notes' => 'nullable|string',
            'estimated_distance' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        // Check ambulance availability
        $ambulance = Ambulance::find($request->ambulance_id);
        if ($ambulance->status !== 'tersedia') {
            return $this->sendError('Selected ambulance is not available', [], 400);
        }

        // Calculate estimated cost
        $estimatedCost = $ambulance->tarif_base;
        if ($request->estimated_distance) {
            $estimatedCost += ($request->estimated_distance * $ambulance->tarif_per_km);
        }

        // Update request and ambulance
        $ambulanceRequest->update([
            'ambulance_id' => $request->ambulance_id,
            'status' => 'dispatched',
            'dispatched_by' => $request->dispatched_by,
            'admin_notes' => $request->admin_notes,
            'total_biaya' => $estimatedCost,
            'jarak_km' => $request->estimated_distance,
        ]);

        $ambulance->update(['status' => 'beroperasi']);

        return $this->sendResponse(
            $ambulanceRequest->fresh()->load(['user', 'ambulance', 'dispatchedBy']), 
            'Ambulance dispatched successfully'
        );
    }

    /**
     * Update location/status for real-time tracking
     */
    public function updateStatus(Request $request, $id)
    {
        $ambulanceRequest = AmbulanceRequest::find($id);

        if (!$ambulanceRequest) {
            return $this->sendError('Ambulance request tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:dispatched,on_way,arrived,completed',
            'current_location' => 'nullable|string',
            'notes' => 'nullable|string',
            'actual_distance' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $updateData = ['status' => $request->status];

        if ($request->notes) {
            $updateData['admin_notes'] = $ambulanceRequest->admin_notes . "\n" . now() . ": " . $request->notes;
        }

        if ($request->actual_distance) {
            $updateData['jarak_km'] = $request->actual_distance;

            // Recalculate cost with actual distance
            if ($ambulanceRequest->ambulance) {
                $ambulance = $ambulanceRequest->ambulance;
                $actualCost = $ambulance->tarif_base + ($request->actual_distance * $ambulance->tarif_per_km);
                $updateData['total_biaya'] = $actualCost;
            }
        }

        $ambulanceRequest->update($updateData);

        // Update ambulance location and status
        if ($ambulanceRequest->ambulance) {
            $ambulanceUpdateData = [];

            if ($request->current_location) {
                $ambulanceUpdateData['current_location'] = $request->current_location;
            }

            if ($request->status === 'completed') {
                $ambulanceUpdateData['status'] = 'tersedia';
            }

            if (!empty($ambulanceUpdateData)) {
                $ambulanceRequest->ambulance->update($ambulanceUpdateData);
            }
        }

        return $this->sendResponse(
            $ambulanceRequest->fresh()->load(['user', 'ambulance']), 
            'Ambulance request status berhasil diperbarui'
        );
    }

    /**
     * Get emergency requests (for dispatcher dashboard)
     */
    public function getEmergencyRequests()
    {
        $emergencyRequests = AmbulanceRequest::with(['user', 'ambulance'])
                                           ->where('tipe_request', 'emergency')
                                           ->whereIn('status', ['pending', 'dispatched', 'on_way', 'arrived'])
                                           ->orderBy('created_at', 'desc')
                                           ->get();

        return $this->sendResponse($emergencyRequests, 'Emergency requests berhasil diambil');
    }
}