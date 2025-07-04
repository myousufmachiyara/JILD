<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
public function index()
{
    $users = User::with('roles')->get();
    
    // Debug: Check if files exist and log the results
    foreach ($users as $user) {
        if ($user->signature) {
            $storagePath = storage_path('app/public/signatures/' . $user->signature);
            $publicPath = public_path('storage/signatures/' . $user->signature);
            $symlinkExists = is_link(public_path('storage'));
            
            \Log::info('File existence check:', [
                'user_id' => $user->id,
                'filename' => $user->signature,
                'storage_exists' => file_exists($storagePath),
                'public_exists' => file_exists($publicPath),
                'symlink_exists' => $symlinkExists,
                'storage_path' => $storagePath,
                'public_path' => $publicPath,
                'asset_url' => asset('storage/signatures/' . $user->signature),
                'public_storage_dir_exists' => file_exists(public_path('storage')),
                'public_signatures_dir_exists' => file_exists(public_path('storage/signatures'))
            ]);
        }
    }
    
    $roles = Role::all();
    return view('users.index', compact('users', 'roles'));
}

    public function create()
    {
        $roles = Role::all();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        // Validate request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|exists:roles,id',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB
        ]);

        // Prepare data
        $data = $request->only(['name', 'email']);
        $data['password'] = Hash::make($request->password);

        // Handle signature upload
        if ($request->hasFile('signature')) {
            $file = $request->file('signature');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/signatures', $filename);
            $data['signature'] = 'signatures/' . $filename; // Save relative path
        }

        // Create user
        $user = User::create($data);

        // Assign role
        $role = Role::findById($request->role);
        $user->assignRole($role);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|exists:roles,id',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Get basic user data
        $data = $request->only(['name', 'email','signature']);

        // Handle signature upload
        if ($request->hasFile('signature') && $request->file('signature')->isValid()) {
            try {
                // Delete old signature if exists
                if ($user->signature && \Storage::exists('public/signatures/' . $user->signature)) {
                    \Storage::delete('public/signatures/' . $user->signature);
                    \Log::info('Old signature deleted: ' . $user->signature);
                }

                $file = $request->file('signature');
                
                // Generate unique filename
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                // Ensure signatures directory exists
                $directory = storage_path('app/public/signatures');
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                    \Log::info('Created signatures directory: ' . $directory);
                }

                \Log::info('Attempting to upload file:', [
                    'original_name' => $file->getClientOriginalName(),
                    'new_filename' => $filename,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'directory' => $directory
                ]);

                // Try to store the file
                $path = $file->storeAs('public/signatures', $filename);
                
                if ($path) {
                    $data['signature'] = $filename;
                    \Log::info('File uploaded successfully:', [
                        'path' => $path,
                        'filename' => $filename,
                        'full_path' => storage_path('app/' . $path)
                    ]);
                } else {
                    \Log::error('Failed to store file using storeAs method');
                    return redirect()->back()
                        ->with('error', 'Failed to upload signature file.')
                        ->withInput();
                }

            } catch (\Exception $e) {
                \Log::error('Signature upload error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                
                return redirect()->back()
                    ->with('error', 'Error uploading signature: ' . $e->getMessage())
                    ->withInput();
            }
        }

        try {
            // Update user data
            $user->update($data);
            \Log::info('User data updated:', ['user_id' => $user->id, 'data' => $data]);

            // Update user role
            $role = Role::findById($request->role);
            if ($role) {
                $user->syncRoles([$role->name]);
                \Log::info('User role updated:', [
                    'user_id' => $user->id, 
                    'role' => $role->name
                ]);
            } else {
                \Log::error('Role not found:', ['role_id' => $request->role]);
                return redirect()->back()
                    ->with('error', 'Selected role not found.')
                    ->withInput();
            }

            return redirect()->route('users.index')
                ->with('success', 'User updated successfully.');

        } catch (\Exception $e) {
            \Log::error('User update error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->with('error', 'Error updating user: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        return view('users.edit', compact('user', 'roles'));
    }

    public function show($id)
    {
        $user = User::with('roles:id,name')->findOrFail($id);
        return response()->json([
            'status' => true,
            'data' => $user
        ]);
    }


    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->back();
    }
}
