<?php

use App\Http\Controllers\BibleController;
use App\Services\TranslationService;

describe('BibleController', function () {
    describe('constructor', function () {
        it('injects translation service dependency', function () {
            $service = $this->createMock(TranslationService::class);
            $controller = new BibleController($service);

            expect($controller)->toBeInstanceOf(BibleController::class);
        });

        it('requires translation service parameter', function () {
            expect(function () {
                new BibleController(null);
            })->toThrow(TypeError::class);
        });
    });

    describe('method availability', function () {
        it('has all required public methods', function () {
            $service = $this->createMock(TranslationService::class);
            $controller = new BibleController($service);

            expect(method_exists($controller, 'index'))->toBeTrue();
            expect(method_exists($controller, 'book'))->toBeTrue();
            expect(method_exists($controller, 'chapter'))->toBeTrue();
            expect(method_exists($controller, 'search'))->toBeTrue();
        });
    });
});
