<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Traits\ApiResponseTrait;
use App\Services\BibleService;

class Controller extends BaseController
{
    use ApiResponseTrait;

    protected BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }
}
