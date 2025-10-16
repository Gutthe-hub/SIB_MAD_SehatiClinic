<?php

namespace App\Http\Controllers\Api;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomController extends BaseApiController
{
    public function index()
    {
        $rooms = Room::with(['roomBookings', 'currentBooking'])
                    ->orderBy('nomor_kamar', 'asc')
                    ->get();
        
        return $this->sendResponse($rooms, 'Rooms berhasil diambil');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nomor_kamar' => 'required|string|max:10|unique:rooms',
            'tipe_kamar' => 'required|in:vip,kelas_1,kelas_2,kelas_3',
            'tarif_per_hari' => 'required|numeric|min:0',
            'fasilitas' => 'nullable|string',
            'status' => 'in:tersedia,terisi,maintenance',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $room = Room::create($request->all());

        return $this->sendResponse($room, 'Room berhasil dibuat', 201);
    }

    public function show($id)
    {
        $room = Room::with(['roomBookings', 'currentBooking'])->find($id);

        if (!$room) {
            return $this->sendError('Room tidak ditemukan', [], 404);
        }

        return $this->sendResponse($room, 'Room berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return $this->sendError('Room tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'nomor_kamar' => 'sometimes|string|max:10|unique:rooms,nomor_kamar,' . $id,
            'tipe_kamar' => 'sometimes|in:vip,kelas_1,kelas_2,kelas_3',
            'tarif_per_hari' => 'sometimes|numeric|min:0',
            'fasilitas' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:tersedia,terisi,maintenance',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $room->update($request->all());

        return $this->sendResponse($room->fresh(), 'Room berhasil diperbarui');
    }

    public function destroy($id)
    {
        $room = Room::find($id);

        if (!$room) {
            return $this->sendError('Room tidak ditemukan', [], 404);
        }

        $room->delete();

        return $this->sendResponse([], 'Room berhasil dihapus');
    }

    /**
     * Get available rooms by date range
     */
    public function getAvailableRooms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'checkin_date' => 'required|date',
            'checkout_date' => 'nullable|date|after:checkin_date',
            'tipe_kamar' => 'nullable|in:vip,kelas_1,kelas_2,kelas_3',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $query = Room::where('status', 'tersedia');

        if ($request->tipe_kamar) {
            $query->where('tipe_kamar', $request->tipe_kamar);
        }

        $availableRooms = $query->whereDoesntHave('roomBookings', function($q) use ($request) {
            $q->where('status', '!=', 'cancelled')
              ->where('status', '!=', 'checkout')
              ->where(function($query) use ($request) {
                  $query->whereBetween('tanggal_checkin', [$request->checkin_date, $request->checkout_date ?? $request->checkin_date])
                        ->orWhereBetween('tanggal_checkout', [$request->checkin_date, $request->checkout_date ?? $request->checkin_date])
                        ->orWhere(function($q) use ($request) {
                            $q->where('tanggal_checkin', '<=', $request->checkin_date)
                              ->where('tanggal_checkout', '>=', $request->checkout_date ?? $request->checkin_date);
                        });
              });
        })->get();

        return $this->sendResponse($availableRooms, 'Available rooms berhasil diambil');
    }
}