<?php

namespace App\Http\Controllers\Api;

use App\Services\BibleService;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller as BaseController;

class Controller extends BaseController
{
    use ApiResponseTrait;

    protected BibleService $bibleService;

    public function __construct(BibleService $bibleService)
    {
        $this->bibleService = $bibleService;
    }
}
