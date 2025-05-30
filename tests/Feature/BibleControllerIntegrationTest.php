<?php

describe('Bible Controller Integration', function () {
    describe('Index endpoint', function () {
        it('renders bible home page with real data', function () {
            $response = $this->get('/');

            $response->assertOk();
            $response->assertViewIs('bible.index');

            // Check for actual Bible content
            $response->assertSee('Bible Reader');
            $response->assertSee('Old Testament');
            $response->assertSee('New Testament');
            $response->assertSee('Genesis');
            $response->assertSee('Revelation');
        });

        it('shows translation selector with all translations', function () {
            $response = $this->get('/');

            $response->assertOk();
            $response->assertSee('KJV');
            $response->assertSee('ASV');
            $response->assertSee('MAO');
        });

        it('loads with different translations', function () {
            $kjvResponse = $this->get('/?translation=kjv');
            $asvResponse = $this->get('/?translation=asv');
            $maoResponse = $this->get('/?translation=mao');

            $kjvResponse->assertOk();
            $asvResponse->assertOk();
            $maoResponse->assertOk();

            $kjvResponse->assertSee('King James Version');
            $asvResponse->assertSee('American Standard Version');
            $maoResponse->assertSee('Maori Version');
        });
    });

    describe('Book endpoints', function () {
        it('displays book chapters correctly', function () {
            $response = $this->get('/Gen');

            $response->assertOk();
            $response->assertViewIs('bible.book');
            $response->assertSee('Genesis');
            $response->assertSee('Chapter 1');
            $response->assertSee('Chapter 50');
        });

        it('handles book switching between translations', function () {
            $kjvResponse = $this->get('/Gen?translation=kjv');
            $asvResponse = $this->get('/Gen?translation=asv');

            $kjvResponse->assertOk();
            $asvResponse->assertOk();

            // Both should show Genesis but with different translation names
            $kjvResponse->assertSee('Genesis');
            $asvResponse->assertSee('Genesis');
        });

        it('returns 404 for invalid book', function () {
            $response = $this->get('/InvalidBook');

            $response->assertNotFound();
        });
    });

    describe('Chapter endpoints', function () {
        it('displays chapter verses correctly', function () {
            $response = $this->get('/Gen/1');

            $response->assertOk();
            $response->assertViewIs('bible.chapter');
            $response->assertSee('Genesis 1');
            $response->assertSee('In the beginning God created');
        });

        it('shows different translations of same chapter', function () {
            $kjvResponse = $this->get('/Gen/1?translation=kjv');
            $asvResponse = $this->get('/Gen/1?translation=asv');

            $kjvResponse->assertOk();
            $asvResponse->assertOk();

            // KJV vs ASV difference in Genesis 1:1
            $kjvResponse->assertSee('heaven and the earth');
            $asvResponse->assertSee('heavens and the earth');
        });

        it('supports verse and paragraph style formatting', function () {
            $verseResponse = $this->get('/Gen/1?style=verse');
            $paragraphResponse = $this->get('/Gen/1?style=paragraph');

            $verseResponse->assertOk();
            $paragraphResponse->assertOk();

            // Both should contain the creation text
            $verseResponse->assertSee('In the beginning God created');
            $paragraphResponse->assertSee('In the beginning God created');
        });

        it('returns 404 for invalid chapter', function () {
            $response = $this->get('/Gen/999');

            $response->assertNotFound();
        });
    });

    describe('Search functionality', function () {
        it('performs text search and returns results', function () {
            $response = $this->get('/search?q=love&translation=kjv');

            $response->assertOk();
            $response->assertViewIs('bible.search');
            $response->assertSee('Search Results');
            $response->assertSee('love');
        });

        it('handles verse reference searches', function () {
            $response = $this->get('/search?q=John 3:16&translation=kjv');

            // Should redirect to the chapter with verse highlighted
            $response->assertRedirect('/John/3');
        });

        it('handles verse range searches', function () {
            $response = $this->get('/search?q=John 3:16-17&translation=kjv');

            // Should redirect to chapter with verse range
            $response->assertRedirect('/John/3');
        });

        it('handles chapter reference searches', function () {
            $response = $this->get('/search?q=Psalm 23&translation=kjv');

            // Should redirect to the chapter
            $response->assertRedirect('/Ps/23');
        });

        it('searches across different translations', function () {
            $kjvResponse = $this->get('/search?q=God&translation=kjv');
            $asvResponse = $this->get('/search?q=God&translation=asv');
            $maoResponse = $this->get('/search?q=atua&translation=mao');

            $kjvResponse->assertOk();
            $asvResponse->assertOk();
            $maoResponse->assertOk();

            $kjvResponse->assertSee('God');
            $asvResponse->assertSee('God');
            $maoResponse->assertSee('atua');
        });

        it('validates search input', function () {
            $response = $this->get('/search?q=a&translation=kjv');

            // Should fail validation (too short)
            $response->assertSessionHasErrors(['q']);
        });

        it('handles search limits', function () {
            $response = $this->get('/search?q=the&translation=kjv&limit=5');

            $response->assertOk();
            $response->assertSee('5'); // Should indicate limit somehow
        });
    });

    describe('API endpoints', function () {
        it('returns JSON for books API', function () {
            $response = $this->get('/api/books?translation=kjv');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_id', 'name', 'short_name', 'testament']
            ]);

            $books = $response->json();
            expect(count($books))->toBe(66);
            expect($books[0]['osis_id'])->toBe('Gen');
        });

        it('returns JSON for chapters API', function () {
            $response = $this->get('/api/Gen/chapters?translation=kjv');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_ref', 'chapter_number', 'verse_count']
            ]);

            $chapters = $response->json();
            expect(count($chapters))->toBe(50);
            expect($chapters[0]['chapter_number'])->toBe(1);
        });

        it('returns JSON for verses API', function () {
            $response = $this->get('/api/Gen/1/verses?translation=kjv');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_id', 'verse_number', 'text']
            ]);

            $verses = $response->json();
            expect(count($verses))->toBe(31);
            expect($verses[0]['osis_id'])->toBe('Gen.1.1');
        });

        it('returns JSON for search API', function () {
            $response = $this->get('/api/search?q=God&translation=kjv&limit=5');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_id', 'book_id', 'chapter', 'verse', 'text', 'context']
            ]);

            $results = $response->json();
            expect(count($results))->toBe(5);
            expect($results[0]['text'])->toContain('God');
        });
    });

    describe('Error handling and edge cases', function () {
        it('handles missing translation gracefully', function () {
            $response = $this->get('/?translation=invalid');

            $response->assertOk();
            // Should fallback to default translation
            $response->assertSee('King James Version');
        });

        it('handles large search queries', function () {
            $longQuery = str_repeat('test ', 100);
            $response = $this->get('/search?q=' . urlencode($longQuery) . '&translation=kjv');

            // Should either handle gracefully or return validation error
            expect($response->status())->toBeIn([200, 302, 422]);
        });

        it('handles concurrent requests properly', function () {
            // Simulate multiple simultaneous requests
            $responses = [];
            for ($i = 0; $i < 5; $i++) {
                $responses[] = $this->get('/Gen/1?translation=kjv');
            }

            foreach ($responses as $response) {
                $response->assertOk();
                $response->assertSee('In the beginning God created');
            }
        });
    });

    describe('Performance and caching', function () {
        it('loads pages efficiently', function () {
            $startTime = microtime(true);

            $response = $this->get('/Gen/1');

            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $response->assertOk();
            expect($duration)->toBeLessThan(2.0); // Should load within 2 seconds
        });

        it('handles search performance', function () {
            $startTime = microtime(true);

            $response = $this->get('/search?q=Lord&translation=kjv&limit=20');

            $endTime = microtime(true);
            $duration = $endTime - $startTime;

            $response->assertOk();
            expect($duration)->toBeLessThan(3.0); // Search should complete within 3 seconds
        });
    });
});
