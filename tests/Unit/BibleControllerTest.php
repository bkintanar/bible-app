<?php

use App\Http\Controllers\BibleController;
use App\Services\TranslationService;
use App\Services\OsisReader;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Mockery;

describe('BibleController', function () {
    beforeEach(function () {
        $this->translationService = Mockery::mock(TranslationService::class);
        $this->controller = new BibleController($this->translationService);
    });

    afterEach(function () {
        Mockery::close();
    });

    describe('constructor', function () {
        it('injects translation service dependency', function () {
            $controller = new BibleController($this->translationService);
            expect($controller)->toBeInstanceOf(BibleController::class);
        });

        it('requires translation service parameter', function () {
            expect(function() {
                new BibleController();
            })->toThrow(ArgumentCountError::class);
        });
    });

    describe('method availability', function () {
        it('has all required public methods', function () {
            $methods = ['index', 'book', 'chapter', 'search', 'switchTranslation'];

            foreach ($methods as $method) {
                expect(method_exists($this->controller, $method))->toBeTrue();
            }
        });
    });

    describe('index method', function () {
        it('returns bible index view with data', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $mockReader->shouldReceive('getBibleInfo')->andReturn(['name' => 'KJV']);
            $mockReader->shouldReceive('getBooks')->andReturn(collect([['name' => 'Genesis']]));

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);
            $this->translationService->shouldReceive('getCurrentTranslation')->andReturn(['key' => 'kjv']);
            $this->translationService->shouldReceive('getAvailableTranslations')->andReturn(collect([]));

            $result = $this->controller->index();

            expect($result)->toBeInstanceOf(View::class);
        });

        it('throws 500 error when no reader available', function () {
            $this->translationService->shouldReceive('getCurrentReader')->andReturn(null);

            expect(fn() => $this->controller->index())
                ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        });
    });

    describe('book method', function () {
        it('returns book view with chapters', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $mockReader->shouldReceive('getBooks')->andReturn($books);
            $mockReader->shouldReceive('getChapters')->with('Gen')->andReturn(collect([]));

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);
            $this->translationService->shouldReceive('getCurrentTranslation')->andReturn(['key' => 'kjv']);
            $this->translationService->shouldReceive('getAvailableTranslations')->andReturn(collect([]));

            $result = $this->controller->book('Gen');

            expect($result)->toBeInstanceOf(View::class);
        });

        it('throws 500 error when no reader available', function () {
            $this->translationService->shouldReceive('getCurrentReader')->andReturn(null);

            expect(fn() => $this->controller->book('Gen'))
                ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        });

        it('throws 404 error when book not found', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $mockReader->shouldReceive('getBooks')->andReturn($books);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

            expect(fn() => $this->controller->book('InvalidBook'))
                ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        });
    });

    describe('chapter method', function () {
        it('returns chapter view with verses in verse format', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $verses = collect([['verse_number' => 1, 'text' => 'In the beginning...']]);

            $mockReader->shouldReceive('getBooks')->andReturn($books);
            $mockReader->shouldReceive('getVerses')->with('Gen.1')->andReturn($verses);
            $mockReader->shouldReceive('getChapters')->with('Gen')->andReturn(collect([]));

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);
            $this->translationService->shouldReceive('getCurrentTranslation')->andReturn(['key' => 'kjv']);
            $this->translationService->shouldReceive('getAvailableTranslations')->andReturn(collect([]));

            $request = new Request(['style' => 'verse']);
            $result = $this->controller->chapter('Gen', 1, $request);

            expect($result)->toBeInstanceOf(View::class);
        });

        it('returns chapter view with paragraphs in paragraph format', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $paragraphs = collect([['verses' => [['verse_number' => 1, 'text' => 'In the beginning...']]]]);

            $mockReader->shouldReceive('getBooks')->andReturn($books);
            $mockReader->shouldReceive('getVersesParagraphStyle')->with('Gen.1')->andReturn($paragraphs);
            $mockReader->shouldReceive('getChapters')->with('Gen')->andReturn(collect([]));

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);
            $this->translationService->shouldReceive('getCurrentTranslation')->andReturn(['key' => 'kjv']);
            $this->translationService->shouldReceive('getAvailableTranslations')->andReturn(collect([]));

            $request = new Request(['style' => 'paragraph']);
            $result = $this->controller->chapter('Gen', 1, $request);

            expect($result)->toBeInstanceOf(View::class);
        });

        it('defaults to paragraph format when no style specified', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $paragraphs = collect([['verses' => [['verse_number' => 1, 'text' => 'In the beginning...']]]]);

            $mockReader->shouldReceive('getBooks')->andReturn($books);
            $mockReader->shouldReceive('getVersesParagraphStyle')->with('Gen.1')->andReturn($paragraphs);
            $mockReader->shouldReceive('getChapters')->with('Gen')->andReturn(collect([]));

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);
            $this->translationService->shouldReceive('getCurrentTranslation')->andReturn(['key' => 'kjv']);
            $this->translationService->shouldReceive('getAvailableTranslations')->andReturn(collect([]));

            $request = new Request();
            $result = $this->controller->chapter('Gen', 1, $request);

            expect($result)->toBeInstanceOf(View::class);
        });

        it('throws 500 error when no reader available', function () {
            $this->translationService->shouldReceive('getCurrentReader')->andReturn(null);

            $request = new Request();
            expect(fn() => $this->controller->chapter('Gen', 1, $request))
                ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        });

        it('throws 404 error when book not found', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $mockReader->shouldReceive('getBooks')->andReturn($books);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

            $request = new Request();
            expect(fn() => $this->controller->chapter('InvalidBook', 1, $request))
                ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        });

        it('throws 404 error when chapter not found in verse format', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $verses = collect([]); // Empty collection

            $mockReader->shouldReceive('getBooks')->andReturn($books);
            $mockReader->shouldReceive('getVerses')->with('Gen.999')->andReturn($verses);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

            $request = new Request(['style' => 'verse']);
            expect(fn() => $this->controller->chapter('Gen', 999, $request))
                ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        });

        it('throws 404 error when chapter not found in paragraph format', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $paragraphs = collect([]); // Empty collection

            $mockReader->shouldReceive('getBooks')->andReturn($books);
            $mockReader->shouldReceive('getVersesParagraphStyle')->with('Gen.999')->andReturn($paragraphs);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

            $request = new Request(['style' => 'paragraph']);
            expect(fn() => $this->controller->chapter('Gen', 999, $request))
                ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        });
    });

    describe('search method', function () {
        it('performs text search and returns results', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
            $results = collect([
                ['book_id' => 'Gen', 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning God...']
            ]);

            $mockReader->shouldReceive('parseVerseReference')->with('God')->andReturn(null);
            $mockReader->shouldReceive('searchVerses')->with('God', 100)->andReturn($results);
            $mockReader->shouldReceive('getBooks')->andReturn($books);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);
            $this->translationService->shouldReceive('getCurrentTranslation')->andReturn(['key' => 'kjv']);
            $this->translationService->shouldReceive('getAvailableTranslations')->andReturn(collect([]));

            $request = new Request(['q' => 'God', 'limit' => 100]);
            $result = $this->controller->search($request);

            expect($result)->toBeInstanceOf(View::class);
        });

        it('redirects to verse when verse reference provided', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $verseRef = [
                'type' => 'verse',
                'book_osis_id' => 'Acts',
                'chapter' => 2,
                'verse' => 38
            ];

            $mockReader->shouldReceive('parseVerseReference')->with('Acts 2:38')->andReturn($verseRef);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

            $request = new Request(['q' => 'Acts 2:38', 'limit' => 100]);
            $result = $this->controller->search($request);

            expect($result)->toBeInstanceOf(RedirectResponse::class);
        });

        it('redirects to verse range when verse range reference provided', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $verseRef = [
                'type' => 'verse_range',
                'book_osis_id' => 'John',
                'chapter' => 3,
                'start_verse' => 16,
                'end_verse' => 17
            ];

            $mockReader->shouldReceive('parseVerseReference')->with('John 3:16-17')->andReturn($verseRef);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

            $request = new Request(['q' => 'John 3:16-17', 'limit' => 100]);
            $result = $this->controller->search($request);

            expect($result)->toBeInstanceOf(RedirectResponse::class);
        });

        it('redirects to chapter when chapter reference provided', function () {
            $mockReader = Mockery::mock(OsisReader::class);
            $verseRef = [
                'type' => 'chapter',
                'book_osis_id' => 'Ps',
                'chapter' => 23
            ];

            $mockReader->shouldReceive('parseVerseReference')->with('Psalm 23')->andReturn($verseRef);

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

            $request = new Request(['q' => 'Psalm 23', 'limit' => 100]);
            $result = $this->controller->search($request);

            expect($result)->toBeInstanceOf(RedirectResponse::class);
        });

        it('handles search exceptions gracefully', function () {
            $mockReader = Mockery::mock(OsisReader::class);

            $mockReader->shouldReceive('parseVerseReference')->with('test')->andReturn(null);
            $mockReader->shouldReceive('searchVerses')->with('test', 100)->andThrow(new Exception('Search error'));
            $mockReader->shouldReceive('getBooks')->andReturn(collect([]));

            $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);
            $this->translationService->shouldReceive('getCurrentTranslation')->andReturn(['key' => 'kjv']);
            $this->translationService->shouldReceive('getAvailableTranslations')->andReturn(collect([]));

            $request = new Request(['q' => 'test', 'limit' => 100]);
            $result = $this->controller->search($request);

            expect($result)->toBeInstanceOf(View::class);
        });

        it('throws 500 error when no reader available', function () {
            $this->translationService->shouldReceive('getCurrentReader')->andReturn(null);

            $request = new Request(['q' => 'test', 'limit' => 100]);
            expect(fn() => $this->controller->search($request))
                ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        });
    });

    describe('switchTranslation method', function () {
        it('switches translation when valid translation provided', function () {
            $this->translationService->shouldReceive('translationExists')->with('asv')->andReturn(true);
            $this->translationService->shouldReceive('setCurrentTranslation')->with('asv');

            $request = new Request(['translation' => 'asv']);
            $result = $this->controller->switchTranslation($request);

            expect($result)->toBeInstanceOf(RedirectResponse::class);
        });

        it('redirects to default when invalid translation provided', function () {
            $this->translationService->shouldReceive('translationExists')->with('invalid')->andReturn(false);

            $request = new Request(['translation' => 'invalid']);
            $result = $this->controller->switchTranslation($request);

            expect($result)->toBeInstanceOf(RedirectResponse::class);
        });
    });

    describe('API endpoints', function () {
        describe('apiBooks method', function () {
            it('returns JSON response with books', function () {
                $mockReader = Mockery::mock(OsisReader::class);
                $books = collect([['osis_id' => 'Gen', 'name' => 'Genesis']]);
                $mockReader->shouldReceive('getBooks')->andReturn($books);

                $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

                $result = $this->controller->apiBooks();

                expect($result->getStatusCode())->toBe(200);
                expect($result->getData(true))->toEqual($books->toArray());
            });

            it('returns 500 error when no reader available', function () {
                $this->translationService->shouldReceive('getCurrentReader')->andReturn(null);

                $result = $this->controller->apiBooks();

                expect($result->getStatusCode())->toBe(500);
                expect($result->getData(true))->toHaveKey('error');
            });
        });

        describe('apiChapters method', function () {
            it('returns JSON response with chapters', function () {
                $mockReader = Mockery::mock(OsisReader::class);
                $chapters = collect([['chapter_number' => 1, 'verse_count' => 31]]);
                $mockReader->shouldReceive('getChapters')->with('Gen')->andReturn($chapters);

                $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

                $result = $this->controller->apiChapters('Gen');

                expect($result->getStatusCode())->toBe(200);
                expect($result->getData(true))->toEqual($chapters->toArray());
            });

            it('returns 500 error when no reader available', function () {
                $this->translationService->shouldReceive('getCurrentReader')->andReturn(null);

                $result = $this->controller->apiChapters('Gen');

                expect($result->getStatusCode())->toBe(500);
                expect($result->getData(true))->toHaveKey('error');
            });
        });

        describe('apiVerses method', function () {
            it('returns JSON response with verses', function () {
                $mockReader = Mockery::mock(OsisReader::class);
                $verses = collect([['verse_number' => 1, 'text' => 'In the beginning...']]);
                $mockReader->shouldReceive('getVerses')->with('Gen.1')->andReturn($verses);

                $this->translationService->shouldReceive('getCurrentReader')->andReturn($mockReader);

                $result = $this->controller->apiVerses('Gen', 1);

                expect($result->getStatusCode())->toBe(200);
                expect($result->getData(true))->toEqual($verses->toArray());
            });

            it('returns 500 error when no reader available', function () {
                $this->translationService->shouldReceive('getCurrentReader')->andReturn(null);

                $result = $this->controller->apiVerses('Gen', 1);

                expect($result->getStatusCode())->toBe(500);
                expect($result->getData(true))->toHaveKey('error');
            });
        });
    });
});
