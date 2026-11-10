<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Support\ResumePosition;

/** Legacy - routes and controller are removed in plan Task 5.2. */
class UserSessionController extends Controller
{
    /**
     * Display the user session information
     * GET /user-session
     */
    public function show()
    {
        $lastVisited = ResumePosition::get();

        return view('user-session.show', compact('lastVisited'));
    }

    /**
     * Update the user session
     * PUT/PATCH /user-session
     */
    public function update()
    {
        // This could handle updating session preferences
        return back()->with('success', 'Session updated successfully');
    }

    /**
     * Clear the user session data
     * DELETE /user-session
     */
    public function destroy(): RedirectResponse
    {
        ResumePosition::forget();

        return redirect()->route('bible.index')->with('success', 'Session data cleared');
    }
}
