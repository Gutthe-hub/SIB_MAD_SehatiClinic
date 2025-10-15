<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseApiController
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        $users = User::with(['appointments', 'roomBookings', 'ambulanceRequests', 'payments'])
                    ->orderBy('created_at', 'desc')
                    ->get();
        
        return $this->sendResponse($users, 'Users retrieved successfully');
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|string|size:16|unique:users',
            'nama' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users',
            'phone' => 'required|string|max:15',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'jenis_kelamin' => 'nullable|in:L,P',
            'no_bpjs' => 'nullable|string|max:20',
            'asuransi' => 'nullable|string|max:50',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $user = User::create([
            'nik' => $request->nik,
            'nama' => $request->nama,
            'email' => $request->email,
            'phone' => $request->phone,
            'tanggal_lahir' => $request->tanggal_lahir,
            'alamat' => $request->alamat,
            'jenis_kelamin' => $request->jenis_kelamin,
            'no_bpjs' => $request->no_bpjs,
            'asuransi' => $request->asuransi,
            'password' => Hash::make($request->password),
        ]);

        return $this->sendResponse($user, 'User created successfully', 201);
    }

    /**
     * Display the specified user
     */
    public function show($id)
    {
        $user = User::with(['appointments', 'roomBookings', 'ambulanceRequests', 'payments'])
                   ->find($id);

        if (!$user) {
            return $this->sendError('User not found', [], 404);
        }

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->sendError('User not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'nik' => 'sometimes|string|size:16|unique:users,nik,' . $id,
            'nama' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|unique:users,email,' . $id,
            'phone' => 'sometimes|string|max:15',
            'tanggal_lahir' => 'sometimes|nullable|date',
            'alamat' => 'sometimes|nullable|string',
            'jenis_kelamin' => 'sometimes|nullable|in:L,P',
            'no_bpjs' => 'sometimes|nullable|string|max:20',
            'asuransi' => 'sometimes|nullable|string|max:50',
            'password' => 'sometimes|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator);
        }

        $updateData = $request->only([
            'nik', 'nama', 'email', 'phone', 'tanggal_lahir',
            'alamat', 'jenis_kelamin', 'no_bpjs', 'asuransi'
        ]);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return $this->sendResponse($user->fresh(), 'User updated successfully');
    }

    /**
     * Remove the specified user
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->sendError('User not found', [], 404);
        }

        $user->delete();

        return $this->sendResponse([], 'User deleted successfully');
    }
}