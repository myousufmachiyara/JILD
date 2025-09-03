<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    // List all locations
    public function index()
    {
        $locations = Location::all();
        return view('stock-transfer.locations', compact('locations'));
    }

    // Store a new location
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);

        $location = Location::create($request->only('name', 'code'));

        Log::info('Location created', ['location' => $location->toArray()]);

        return redirect()->route('locations.index')->with('success', 'Location created successfully.');
    }

    // Update an existing location
    public function update(Request $request, $id)
    {
        $location = Location::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);

        $location->update($request->only('name', 'code'));

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    // Delete a location
    public function destroy($id)
    {
        $location = Location::findOrFail($id);
        $location->delete();
        \Log::info('Location deleted', ['location_id' => $location->id]);

        return redirect()->route('locations.index')->with('success', 'Location deleted successfully.');
    }
}
