<?php

namespace App\Http\Controllers;

use App\Jobs\ModerateChirp;
use App\Models\Chirp;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ChirpController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $chirps = Chirp::with('user')->approved()->latest()->take(50)->get();

        return view('home', ['chirps' => $chirps]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:255',
        ], [
            'message.required' => 'Please write something to chirp!',
            'message.max' => 'Chirps must be 255 characters or less.',
        ]);

        $chirp = auth()->user()->chirps()->create($validated);

        // Dispatch AI moderation job
        ModerateChirp::dispatch($chirp);

        return redirect('/')->with('success', 'Your chirp has been posted and is being reviewed!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Chirp $chirp)
    {

        $this->authorize('update', $chirp); // Check if the user is authorized to update the chirp

        return view('chirps.edit', compact('chirp'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Chirp $chirp)
    {
        $this->authorize('update', $chirp); // Check if the user is authorized to update the chirp

        $validated = $request->validate([
            'message' => 'required|string|max:255',
        ], [
            'message.required' => 'Please write something to chirp!',
            'message.max' => 'Chirps must be 255 characters or less.',
        ]);

        $chirp->update($validated);

        // Reset moderation status and dispatch new moderation job
        $chirp->update([
            'moderation_status' => 'pending',
            'moderation_reason' => null,
            'moderated_at' => null,
        ]);

        ModerateChirp::dispatch($chirp);

        return redirect('/')->with('success', 'Your chirp has been updated and is being reviewed!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Chirp $chirp)
    {
        $this->authorize('delete', $chirp); // Check if the user is authorized to delete the chirp

        $chirp->delete();

        return redirect('/')->with('success', 'Your chirp has been deleted!');
    }
}
