<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BibleController extends Controller
{
    /**
     * Redirect to appropriate chapter
     * GET /
     */
    public function index(Request $request)
    {
        $lastPage = UserSessionController::getLastVisitedPage();

        // If we have a last visited page, redirect there
        if ($lastPage && isset($lastPage['route'])) {
            try {
                if ($lastPage['route'] === 'chapters.show' && isset($lastPage['parameters']['bookOsisId'], $lastPage['parameters']['chapterNumber'])) {
                    return redirect()->route('chapters.show', [
                        'bookOsisId' => $lastPage['parameters']['bookOsisId'],
                        'chapterNumber' => $lastPage['parameters']['chapterNumber'],
                    ]);
                } elseif ($lastPage['route'] === 'books.show' && isset($lastPage['parameters']['bookOsisId'])) {
                    return redirect()->route('books.show', [
                        'bookOsisId' => $lastPage['parameters']['bookOsisId'],
                    ]);
                }
            } catch (\Exception $e) {
                // Fall through to default
            }
        }

        // Default: redirect to Genesis 1
        return redirect()->route('chapters.show', ['bookOsisId' => 'Gen', 'chapterNumber' => 1]);
    }
}
