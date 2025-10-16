<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends BaseApiController
{
    public function index()
    {
        $payments = Payment::with(['user', 'appointment', 'roomBooking', 'ambulanceRequest', 'processedBy'])
                          ->orderBy('created_at', 'desc')
                          ->get();
        
        return $this->sendResponse($payments, 'Payments berhasil diambil');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'room_booking_id' => 'nullable|exists:room_bookings,id',
            'ambulance_request_id' => 'nullable|exists:ambulance_requests,id',
            'tipe_layanan' => 'required|in:appointment,room_booking,ambulance',
            'amount' => 'required|numeric|min:0',
            'metode_pembayaran' => 'required|in:bpjs,asuransi,mandiri',
            'payment_method' => 'nullable|string|max:50',
            'processed_by' => 'nullable|exists:admins,id',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        // Validate that only one reference ID is provided
        $references = collect([$request->appointment_id, $request->room_booking_id, $request->ambulance_request_id])
                     ->filter()
                     ->count();

        if ($references !== 1) {
            return $this->sendError('Exactly one reference ID (appointment_id, room_booking_id, or ambulance_request_id) must be provided', [], 400);
        }

        // Generate transaction ID
        $transactionId = 'TRX' . now()->format('YmdHis') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        $payment = Payment::create(array_merge($request->all(), [
            'transaction_id' => $transactionId,
            'status' => 'pending'
        ]));

        return $this->sendResponse($payment->load(['user', 'appointment', 'roomBooking', 'ambulanceRequest']), 'Payment berhasil dibuat', 201);
    }

    public function show($id)
    {
        $payment = Payment::with(['user', 'appointment', 'roomBooking', 'ambulanceRequest', 'processedBy'])
                         ->find($id);

        if (!$payment) {
            return $this->sendError('Payment tidak ditemukan', [], 404);
        }

        return $this->sendResponse($payment, 'Payment berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return $this->sendError('Payment tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,paid,failed,refunded',
            'payment_method' => 'sometimes|nullable|string|max:50',
            'midtrans_transaction_id' => 'sometimes|nullable|string',
            'receipt_url' => 'sometimes|nullable|url',
            'processed_by' => 'sometimes|nullable|exists:admins,id',
            'admin_notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $updateData = $request->all();

        // Set paid_at when status changes to paid
        if ($request->status === 'paid' && $payment->status !== 'paid') {
            $updateData['paid_at'] = now();
        }

        $payment->update($updateData);

        return $this->sendResponse($payment->fresh()->load(['user', 'processedBy']), 'Payment berhasil diperbarui');
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return $this->sendError('Payment tidak ditemukan', [], 404);
        }

        $payment->delete();

        return $this->sendResponse([], 'Payment berhasil dihapus');
    }

    public function confirmPayment(Request $request, $id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return $this->sendError('Payment tidak ditemukan', [], 404);
        }

        if ($payment->status === 'paid') {
            return $this->sendError('Payment is already confirmed', [], 400);
        }

        $validator = Validator::make($request->all(), [
            'midtrans_transaction_id' => 'nullable|string',
            'receipt_url' => 'nullable|url',
            'processed_by' => 'required|exists:admins,id',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'midtrans_transaction_id' => $request->midtrans_transaction_id,
            'receipt_url' => $request->receipt_url,
            'processed_by' => $request->processed_by,
            'admin_notes' => $request->admin_notes,
        ]);

        return $this->sendResponse($payment->fresh()->load(['user', 'processedBy']), 'Payment confirmed successfully');
    }
}