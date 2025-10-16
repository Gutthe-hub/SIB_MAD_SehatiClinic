<?php

namespace App\Http\Controllers\Api;

use App\Models\RoomBooking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RoomBookingController extends BaseApiController
{
    /**
     * Display a listing of room bookings
     */
    public function index(Request $request)
    {
        $query = RoomBooking::with(['user', 'room', 'appointment', 'confirmedBy', 'checkinBy', 'checkoutBy', 'payment']);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by room type
        if ($request->tipe_kamar) {
            $query->whereHas('room', function($q) use ($request) {
                $q->where('tipe_kamar', $request->tipe_kamar);
            });
        }

        // Filter by date range
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('tanggal_checkin', [$request->start_date, $request->end_date]);
        }

        // Filter by checkin date
        if ($request->checkin_date) {
            $query->whereDate('tanggal_checkin', $request->checkin_date);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        return $this->sendResponse($bookings, 'Room bookings berhasil diambil');
    }

    /**
     * Store a newly created room booking
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'tanggal_checkin' => 'required|date|after_or_equal:today',
            'tanggal_checkout' => 'nullable|date|after:tanggal_checkin',
            'special_requests' => 'nullable|string',
            'metode_pembayaran' => 'required|in:bpjs,asuransi,mandiri',
            'confirmed_by' => 'nullable|exists:admins,id',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        // Check room availability
        $room = Room::find($request->room_id);

        if ($room->status !== 'tersedia') {
            return $this->sendError('Room is not available', [], 400);
        }

        // Check if room is already booked for the requested dates
        $checkinDate = $request->tanggal_checkin;
        $checkoutDate = $request->tanggal_checkout ?? Carbon::parse($checkinDate)->addDays(1)->format('Y-m-d');

        $conflictingBooking = RoomBooking::where('room_id', $request->room_id)
                                        ->whereNotIn('status', ['cancelled', 'checkout'])
                                        ->where(function($query) use ($checkinDate, $checkoutDate) {
                                            $query->whereBetween('tanggal_checkin', [$checkinDate, $checkoutDate])
                                                  ->orWhereBetween('tanggal_checkout', [$checkinDate, $checkoutDate])
                                                  ->orWhere(function($q) use ($checkinDate, $checkoutDate) {
                                                      $q->where('tanggal_checkin', '<=', $checkinDate)
                                                        ->where('tanggal_checkout', '>=', $checkoutDate);
                                                  });
                                        })
                                        ->exists();

        if ($conflictingBooking) {
            return $this->sendError('Kamar telah dibooking di tanggal yang terpilih', [], 400);
        }

        // Calculate total cost
        $checkinCarbon = Carbon::parse($checkinDate);
        $checkoutCarbon = Carbon::parse($checkoutDate);
        $days = $checkinCarbon->diffInDays($checkoutCarbon);
        $days = $days > 0 ? $days : 1; // Minimum 1 day

        $totalBiaya = $room->tarif_per_hari * $days;

        // Generate booking number
        $today = now()->format('Ymd');
        $count = RoomBooking::whereDate('created_at', today())->count() + 1;
        $bookingNumber = 'ROOM' . $today . str_pad($count, 3, '0', STR_PAD_LEFT);

        $booking = RoomBooking::create([
            'user_id' => $request->user_id,
            'room_id' => $request->room_id,
            'appointment_id' => $request->appointment_id,
            'tanggal_checkin' => $checkinDate,
            'tanggal_checkout' => $checkoutDate,
            'special_requests' => $request->special_requests,
            'metode_pembayaran' => $request->metode_pembayaran,
            'total_biaya' => $totalBiaya,
            'status' => $request->confirmed_by ? 'confirmed' : 'pending',
            'booking_number' => $bookingNumber,
            'confirmed_by' => $request->confirmed_by,
            'admin_notes' => $request->admin_notes,
        ]);

        // Update room status if confirmed
        if ($request->confirmed_by) {
            $room->update(['status' => 'terisi']);
        }

        return $this->sendResponse(
            $booking->load(['user', 'room', 'appointment', 'confirmedBy']), 
            'Room booking berhasil dibuat', 
            201
        );
    }

    /**
     * Display the specified room booking
     */
    public function show($id)
    {
        $booking = RoomBooking::with([
            'user', 
            'room', 
            'appointment', 
            'confirmedBy', 
            'checkinBy', 
            'checkoutBy', 
            'payment'
        ])->find($id);

        if (!$booking) {
            return $this->sendError('Room booking tidak ditemukan', [], 404);
        }

        return $this->sendResponse($booking, 'Room booking berhasil diambil');
    }

    /**
     * Update the specified room booking
     */
    public function update(Request $request, $id)
    {
        $booking = RoomBooking::find($id);

        if (!$booking) {
            return $this->sendError('Room booking tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'room_id' => 'sometimes|exists:rooms,id',
            'appointment_id' => 'sometimes|nullable|exists:appointments,id',
            'tanggal_checkin' => 'sometimes|date',
            'tanggal_checkout' => 'sometimes|nullable|date|after:tanggal_checkin',
            'special_requests' => 'sometimes|nullable|string',
            'metode_pembayaran' => 'sometimes|in:bpjs,asuransi,mandiri',
            'status' => 'sometimes|in:pending,confirmed,checkin,checkout,cancelled',
            'confirmed_by' => 'sometimes|nullable|exists:admins,id',
            'checkin_by' => 'sometimes|nullable|exists:admins,id',
            'checkout_by' => 'sometimes|nullable|exists:admins,id',
            'admin_notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $oldStatus = $booking->status;
        $oldRoomId = $booking->room_id;

        // Update booking
        $booking->update($request->all());

        $newStatus = $booking->fresh()->status;
        $newRoomId = $booking->room_id;

        // Handle room status changes
        if ($oldRoomId && $oldRoomId !== $newRoomId) {
            // Free up old room
            Room::find($oldRoomId)->update(['status' => 'tersedia']);
        }

        if ($newRoomId && $oldStatus !== $newStatus) {
            $room = Room::find($newRoomId);

            switch ($newStatus) {
                case 'confirmed':
                case 'checkin':
                    $room->update(['status' => 'terisi']);
                    break;

                case 'checkout':
                case 'cancelled':
                    $room->update(['status' => 'tersedia']);
                    break;
            }
        }

        // Recalculate total cost if dates changed
        if ($request->has('tanggal_checkin') || $request->has('tanggal_checkout')) {
            $booking->refresh();
            $checkinDate = $booking->tanggal_checkin;
            $checkoutDate = $booking->tanggal_checkout ?? Carbon::parse($checkinDate)->addDays(1)->format('Y-m-d');

            $checkinCarbon = Carbon::parse($checkinDate);
            $checkoutCarbon = Carbon::parse($checkoutDate);
            $days = $checkinCarbon->diffInDays($checkoutCarbon);
            $days = $days > 0 ? $days : 1;

            $totalBiaya = $booking->room->tarif_per_hari * $days;
            $booking->update(['total_biaya' => $totalBiaya]);
        }

        return $this->sendResponse(
            $booking->fresh()->load(['user', 'room', 'confirmedBy', 'checkinBy', 'checkoutBy']), 
            'Room booking berhasil diperbarui'
        );
    }

    /**
     * Remove the specified room booking
     */
    public function destroy($id)
    {
        $booking = RoomBooking::find($id);

        if (!$booking) {
            return $this->sendError('Room booking tidak ditemukan', [], 404);
        }

        // Free up the room if it was occupied
        if ($booking->room && in_array($booking->status, ['confirmed', 'checkin'])) {
            $booking->room->update(['status' => 'tersedia']);
        }

        $booking->delete();

        return $this->sendResponse([], 'Room booking berhasil dihapus');
    }

    /**
     * Confirm booking (for admin use)
     */
    public function confirmBooking(Request $request, $id)
    {
        $booking = RoomBooking::find($id);

        if (!$booking) {
            return $this->sendError('Room booking tidak ditemukan', [], 404);
        }

        if ($booking->status !== 'pending') {
            return $this->sendError('Only pending bookings can be confirmed', [], 400);
        }

        $validator = Validator::make($request->all(), [
            'confirmed_by' => 'required|exists:admins,id',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $booking->update([
            'status' => 'confirmed',
            'confirmed_by' => $request->confirmed_by,
            'admin_notes' => $request->admin_notes,
        ]);

        // Update room status
        $booking->room->update(['status' => 'terisi']);

        return $this->sendResponse(
            $booking->fresh()->load(['user', 'room', 'confirmedBy']), 
            'Room booking confirmed successfully'
        );
    }

    /**
     * Check-in process
     */
    public function checkin(Request $request, $id)
    {
        $booking = RoomBooking::find($id);

        if (!$booking) {
            return $this->sendError('Room booking tidak ditemukan', [], 404);
        }

        if ($booking->status !== 'confirmed') {
            return $this->sendError('Only confirmed bookings can be checked in', [], 400);
        }

        $validator = Validator::make($request->all(), [
            'checkin_by' => 'required|exists:admins,id',
            'admin_notes' => 'nullable|string',
            'actual_checkin_time' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $updateData = [
            'status' => 'checkin',
            'checkin_by' => $request->checkin_by,
        ];

        if ($request->admin_notes) {
            $updateData['admin_notes'] = ($booking->admin_notes ? $booking->admin_notes . "\n" : '') . 
                                        "Check-in: " . $request->admin_notes;
        }

        $booking->update($updateData);

        return $this->sendResponse(
            $booking->fresh()->load(['user', 'room', 'checkinBy']), 
            'Room check-in completed successfully'
        );
    }

    /**
     * Check-out process
     */
    public function checkout(Request $request, $id)
    {
        $booking = RoomBooking::find($id);

        if (!$booking) {
            return $this->sendError('Room booking tidak ditemukan', [], 404);
        }

        if ($booking->status !== 'checkin') {
            return $this->sendError('Only checked-in bookings can be checked out', [], 400);
        }

        $validator = Validator::make($request->all(), [
            'checkout_by' => 'required|exists:admins,id',
            'admin_notes' => 'nullable|string',
            'actual_checkout_date' => 'nullable|date',
            'additional_charges' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $updateData = [
            'status' => 'checkout',
            'checkout_by' => $request->checkout_by,
        ];

        if ($request->actual_checkout_date) {
            $updateData['tanggal_checkout'] = $request->actual_checkout_date;

            // Recalculate cost with actual checkout date
            $checkinDate = $booking->tanggal_checkin;
            $checkoutDate = $request->actual_checkout_date;

            $checkinCarbon = Carbon::parse($checkinDate);
            $checkoutCarbon = Carbon::parse($checkoutDate);
            $days = $checkinCarbon->diffInDays($checkoutCarbon);
            $days = $days > 0 ? $days : 1;

            $newTotal = $booking->room->tarif_per_hari * $days;
            if ($request->additional_charges) {
                $newTotal += $request->additional_charges;
            }

            $updateData['total_biaya'] = $newTotal;
        }

        if ($request->admin_notes) {
            $updateData['admin_notes'] = ($booking->admin_notes ? $booking->admin_notes . "\n" : '') . 
                                        "Check-out: " . $request->admin_notes;
        }

        $booking->update($updateData);

        // Free up the room
        $booking->room->update(['status' => 'tersedia']);

        return $this->sendResponse(
            $booking->fresh()->load(['user', 'room', 'checkoutBy']), 
            'Room check-out completed successfully'
        );
    }

    /**
     * Get room occupancy report
     */
    public function getOccupancyReport(Request $request)
    {
        $startDate = $request->start_date ?? now()->subDays(30)->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');

        $occupancyData = RoomBooking::selectRaw('
                rooms.tipe_kamar,
                COUNT(*) as total_bookings,
                AVG(DATEDIFF(COALESCE(tanggal_checkout, CURDATE()), tanggal_checkin)) as avg_length_of_stay,
                SUM(total_biaya) as total_revenue
            ')
            ->join('rooms', 'room_bookings.room_id', '=', 'rooms.id')
            ->whereNotIn('room_bookings.status', ['cancelled'])
            ->whereBetween('tanggal_checkin', [$startDate, $endDate])
            ->groupBy('rooms.tipe_kamar')
            ->get();

        return $this->sendResponse($occupancyData, 'Room occupancy report berhasil diambil');
    }

    /**
     * Get available rooms for specific dates
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

        $checkinDate = $request->checkin_date;
        $checkoutDate = $request->checkout_date ?? Carbon::parse($checkinDate)->addDays(1)->format('Y-m-d');

        $query = Room::where('status', 'tersedia');

        if ($request->tipe_kamar) {
            $query->where('tipe_kamar', $request->tipe_kamar);
        }

        $availableRooms = $query->whereDoesntHave('roomBookings', function($q) use ($checkinDate, $checkoutDate) {
            $q->whereNotIn('status', ['cancelled', 'checkout'])
              ->where(function($query) use ($checkinDate, $checkoutDate) {
                  $query->whereBetween('tanggal_checkin', [$checkinDate, $checkoutDate])
                        ->orWhereBetween('tanggal_checkout', [$checkinDate, $checkoutDate])
                        ->orWhere(function($q) use ($checkinDate, $checkoutDate) {
                            $q->where('tanggal_checkin', '<=', $checkinDate)
                              ->where('tanggal_checkout', '>=', $checkoutDate);
                        });
              });
        })->orderBy('tipe_kamar')->orderBy('nomor_kamar')->get();

        // Calculate estimated cost for each room
        $checkinCarbon = Carbon::parse($checkinDate);
        $checkoutCarbon = Carbon::parse($checkoutDate);
        $days = $checkinCarbon->diffInDays($checkoutCarbon);
        $days = $days > 0 ? $days : 1;

        $availableRooms->each(function($room) use ($days) {
            $room->estimated_total_cost = $room->tarif_per_hari * $days;
            $room->estimated_days = $days;
        });

        return $this->sendResponse($availableRooms, 'Available rooms berhasil diambil');
    }
}