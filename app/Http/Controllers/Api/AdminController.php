<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends BaseApiController
{
    public function index()
    {
        $admins = Admin::with(['createdDoctors', 'confirmedAppointments', 'activityLogs'])
                     ->orderBy('created_at', 'desc')
                     ->get();
        
        return $this->sendResponse($admins, 'Admins berhasil diambil');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:admins',
            'email' => 'required|email|unique:admins',
            'nama' => 'required|string|max:100',
            'phone' => 'nullable|string|max:15',
            'password' => 'required|string|min:6',
            'role' => 'required|in:super_admin,receptionist,finance,medical_staff,it_support',
            'department' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $admin = Admin::create([
            'username' => $request->username,
            'email' => $request->email,
            'nama' => $request->nama,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'department' => $request->department,
            'is_active' => $request->get('is_active', true),
        ]);

        return $this->sendResponse($admin, 'Admin sukses dibuat', 201);
    }

    public function show($id)
    {
        $admin = Admin::with(['createdDoctors', 'confirmedAppointments', 'activityLogs'])
                    ->find($id);

        if (!$admin) {
            return $this->sendError('Admin tidak ditemukan', [], 404);
        }

        return $this->sendResponse($admin, 'Admin berhasil diambil');
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return $this->sendError('Admin tidak ditemukan', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:50|unique:admins,username,' . $id,
            'email' => 'sometimes|email|unique:admins,email,' . $id,
            'nama' => 'sometimes|string|max:100',
            'phone' => 'sometimes|nullable|string|max:15',
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|in:super_admin,receptionist,finance,medical_staff,it_support',
            'department' => 'sometimes|nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $updateData = $request->only([
            'username', 'email', 'nama', 'phone', 'role', 'department', 'is_active'
        ]);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $admin->update($updateData);

        return $this->sendResponse($admin->fresh(), 'Admin berhasil diperbarui');
    }

    public function destroy($id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return $this->sendError('Admin tidak ditemukan', [], 404);
        }

        $admin->delete();

        return $this->sendResponse([], 'Admin berhasil dihapus');
    }
}