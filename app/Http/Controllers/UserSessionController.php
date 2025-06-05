<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class UserSessionController extends Controller
{
    /**
     * Display the user session information
     * GET /user-session
     */
    public function show()
    {
        $lastVisited = self::getLastVisitedPage();

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
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        Cache::forget($cacheKey);

        return redirect()->route('bible.index')->with('success', 'Session data cleared');
    }

    /**
     * Store the last visited page in cache
     */
    public static function storeLastVisitedPage(string $route, array $parameters = []): void
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        $pageData = [
            'route' => $route,
            'parameters' => $parameters,
            'url' => request()->url(),
            'timestamp' => now(),
        ];

        Cache::put($cacheKey, $pageData, now()->addDays(30));
    }

    /**
     * Get the last visited page from cache
     */
    public static function getLastVisitedPage(): ?array
    {
        $sessionId = session()->getId();
        $cacheKey = "last_visited_page_{$sessionId}";

        return Cache::get($cacheKey);
    }
}
