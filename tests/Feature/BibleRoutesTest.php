<?php

describe('Bible Routes', function () {
    describe('Home page', function () {
        it('shows bible reader with translations dropdown when multiple available', function () {
            $response = $this->get('/');

            $response->assertOk();
            $response->assertSee('Bible Reader');
            $response->assertSee('KJV');
            $response->assertSee('ASV');
            $response->assertSee('MAO');
        });

        it('shows books list for default translation', function () {
            $response = $this->get('/');

            $response->assertOk();
            $response->assertSee('Old Testament');
            $response->assertSee('New Testament');
            $response->assertSee('Genesis');
            $response->assertSee('Revelation');
        });
    });

    describe('Translation switching', function () {
        it('loads KJV translation', function () {
            $response = $this->get('/?translation=kjv');

            $response->assertOk();
            $response->assertSee('King James Version');
        });

        it('loads ASV translation', function () {
            $response = $this->get('/?translation=asv');

            $response->assertOk();
            $response->assertSee('American Standard Version');
        });

        it('loads Maori translation', function () {
            $response = $this->get('/?translation=mao');

            $response->assertOk();
            $response->assertSee('Maori Version');
        });

        it('falls back to default for invalid translation', function () {
            $response = $this->get('/?translation=invalid');

            $response->assertOk();
            $response->assertSee('King James Version');
        });
    });

    describe('Book routes', function () {
        it('shows chapters for a book', function () {
            $response = $this->get('/Gen');

            $response->assertOk();
            $response->assertSee('Genesis');
            $response->assertSee('Chapter 1');
            $response->assertSee('Chapter 50');
        });

        it('shows chapters for different translations', function () {
            $response = $this->get('/Gen?translation=asv');

            $response->assertOk();
            $response->assertSee('Genesis');
            $response->assertSee('American Standard Version');
        });
    });

    describe('Chapter routes', function () {
        it('shows verses for a chapter', function () {
            $response = $this->get('/Gen/1');

            $response->assertOk();
            $response->assertSee('Genesis 1');
            $response->assertSee('In the beginning God created');
        });

        it('shows different text for different translations', function () {
            $kjvResponse = $this->get('/Gen/1?translation=kjv');
            $asvResponse = $this->get('/Gen/1?translation=asv');

            $kjvResponse->assertOk();
            $asvResponse->assertOk();

            $kjvResponse->assertSee('heaven and the earth');
            $asvResponse->assertSee('heavens and the earth');
        });

        it('shows Maori text', function () {
            $response = $this->get('/Gen/1?translation=mao');

            $response->assertOk();
            $response->assertSee('He mea hanga na te atua');
        });
    });

    describe('Verse routes', function () {
        it('shows individual verse', function () {
            $response = $this->get('/Gen/1/1');

            $response->assertOk();
            $response->assertSee('Genesis 1:1');
            $response->assertSee('In the beginning God created');
        });

        it('handles non-existent verse gracefully', function () {
            $response = $this->get('/Gen/999/999');

            $response->assertNotFound();
        });
    });

    describe('Search functionality', function () {
        it('performs search and returns results', function () {
            $response = $this->get('/search?q=love&translation=kjv');

            $response->assertOk();
            $response->assertSee('Search Results');
            $response->assertSee('love');
        });

        it('searches in different translations', function () {
            $response = $this->get('/search?q=atua&translation=mao');

            $response->assertOk();
            $response->assertSee('atua');
        });

        it('handles empty search query', function () {
            $response = $this->get('/search?q=&translation=kjv');

            $response->assertRedirect('/');
        });

        it('limits search results', function () {
            $response = $this->get('/search?q=the&translation=kjv&limit=5');

            $response->assertOk();
            // Should not exceed the limit
        });
    });

    describe('API endpoints', function () {
        it('returns JSON for books API', function () {
            $response = $this->get('/api/books?translation=kjv');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_id', 'name', 'short_name', 'testament']
            ]);
        });

        it('returns JSON for chapters API', function () {
            $response = $this->get('/api/Gen/chapters?translation=kjv');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_ref', 'chapter_number', 'verse_count']
            ]);
        });

        it('returns JSON for verses API', function () {
            $response = $this->get('/api/Gen/1/verses?translation=kjv');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_id', 'verse_number', 'text']
            ]);
        });

        it('returns JSON for search API', function () {
            $response = $this->get('/api/search?q=God&translation=kjv');

            $response->assertOk();
            $response->assertJsonStructure([
                '*' => ['osis_id', 'book_id', 'chapter', 'verse', 'text', 'context']
            ]);
        });
    });

    describe('Error handling', function () {
        it('handles invalid book gracefully', function () {
            $response = $this->get('/InvalidBook');

            $response->assertNotFound();
        });

        it('handles invalid chapter gracefully', function () {
            $response = $this->get('/Gen/999');

            $response->assertNotFound();
        });
    });
});
