<?php

use App\Jobs\PublishChapterProjection;
use App\Livewire\VerseTitleManager;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * The editor writes the OSIS source of truth. Every test points bible.osis_directory at a TEMPORARY
 * copy of the fixture (OsisSourcePath), so nothing here can touch assets/kjv.osis.xml.
 */

beforeEach(function () {
    projectFixture(); // a projected KJV, so the translation is editable
    $this->dir = sys_get_temp_dir().'/osis-editor-'.uniqid();
    mkdir($this->dir);
    copy(base_path('tests/Fixtures/mini-kjv.osis.xml'), $this->dir.'/kjv.osis.xml');
    config(['bible.osis_directory' => $this->dir, 'bible.editor_enabled' => true]);
    Queue::fake();
    $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'correct-horse-battery']));
});

afterEach(function () {
    array_map('unlink', glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

function editorOn(string $book, int $chapter, int $verse): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(VerseTitleManager::class)
        ->set('selectedBook', $book)
        ->set('selectedChapter', $chapter)
        ->set('selectedVerse', $verse);
}

it('saves a title with $ and & verbatim and queues the chapter for publishing', function () {
    editorOn('Gen', 1, 3)
        ->set('verseTitle', 'Costs $10 & more')
        ->call('saveTitle')
        ->assertSet('messageType', 'success');

    $xml = file_get_contents($this->dir.'/kjv.osis.xml');
    expect($xml)->toContain('<title type="verse" canonical="false">Costs $10 &amp; more</title><verse osisID="Gen.1.3"');
    expect(substr_count($xml, ' sID="Gen.1.3"'))->toBe(1);
    Queue::assertPushed(PublishChapterProjection::class, fn ($job) => $job->chapterRef === 'Gen.1' && $job->translationKey === 'kjv');
});

it('loads the existing title decoded and re-saving it changes nothing', function () {
    editorOn('Gen', 1, 3)->set('verseTitle', 'Faith & Works')->call('saveTitle');
    Queue::fake(); // reset

    editorOn('Gen', 1, 3)
        ->assertSet('verseTitle', 'Faith & Works')
        ->call('saveTitle')
        ->assertSet('messageType', 'info')
        ->assertSee('Nothing to change');

    Queue::assertNothingPushed();
});

it('removes a title and queues a publish', function () {
    editorOn('Gen', 1, 3)->set('verseTitle', 'Temporary')->call('saveTitle');

    editorOn('Gen', 1, 3)->call('removeTitle')->assertSet('verseTitle', '')->assertSet('messageType', 'success');

    expect(file_get_contents($this->dir.'/kjv.osis.xml'))->not->toContain('Temporary');
});

it('rejects control characters in a title', function () {
    editorOn('Gen', 1, 3)->set('verseTitle', "Line\nbreak")->call('saveTitle')->assertHasErrors(['verseTitle' => 'regex']);
});

it('does not let the client change server-owned state', function () {
    expect(fn () => Livewire::test(VerseTitleManager::class)->set('currentTranslation', ['key' => '../x']))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('refuses to switch to a translation that is not projected', function () {
    Livewire::test(VerseTitleManager::class)
        ->call('switchTranslation', 'asv')
        ->assertSet('messageType', 'error')
        ->assertSet('currentTranslation.key', 'kjv');
});

it('writes an audit line per change', function () {
    $file = storage_path('logs/audit-'.now()->format('Y-m-d').'.log');
    $before = file_exists($file) ? count(file($file)) : 0;

    editorOn('Gen', 1, 3)->set('verseTitle', 'Audited')->call('saveTitle');

    expect(file($file))->toHaveCount($before + 1);
    expect(last(file($file)))->toContain('"osis_id":"Gen.1.3"')->toContain('"new":"Audited"');
});
