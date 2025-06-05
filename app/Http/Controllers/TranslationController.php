<?php

namespace App\Http\Controllers;

use App\Services\BibleService;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\SwitchTranslationRequest;

class TranslationController extends Controller
{
    private BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }

    /**
     * Display the current translation
     * GET /translation
     */
    public function show()
    {
        $currentTranslation = $this->bibleService->getCurrentTranslation();
        $availableTranslations = $this->bibleService->getAvailableTranslations();

        return view('translation.show', compact('currentTranslation', 'availableTranslations'));
    }

    /**
     * Update the current translation
     * PUT/PATCH /translation
     */
    public function update(SwitchTranslationRequest $request): RedirectResponse
    {
        $translationKey = $request->getTranslationKey();

        $this->bibleService->setCurrentTranslation($translationKey);

        return back()->with('success', 'Translation updated successfully');
    }
}
