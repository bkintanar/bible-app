<?php

use App\Livewire\VerseTitleManager;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;

/*
 * A session opened before a password change must not keep writing the source of truth - including
 * through Livewire's /livewire/update endpoint, which only re-applies "persistent" middleware.
 */

beforeEach(function () {
    $this->withoutVite();
    projectFixture();
    $this->dir = sys_get_temp_dir() . '/osis-session-' . uniqid();
    mkdir($this->dir);
    copy(base_path('tests/Fixtures/mini-kjv.osis.xml'), $this->dir . '/kjv.osis.xml');
    config(['bible.osis_directory' => $this->dir, 'bible.editor_enabled' => true]);
    Queue::fake();
    $this->user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
});

afterEach(function () {
    array_map('unlink', glob($this->dir . '/*') ?: []);
    @rmdir($this->dir);
});

/**
 * Drive the editor over HTTP the way the browser does: render the page, then one /livewire/update
 * round-trip per wire:model.live change (each carrying the previous response's snapshot), then
 * the saveTitle call. Returns the first non-200 response, or the final one.
 */
function saveTitleOverHttp(User $user, string $sessionPasswordHash): \Illuminate\Testing\TestResponse
{
    $page = test()->actingAs($user)->get('/admin/verse-titles')->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"[^>]*wire:effects/', $page, $m);
    $snapshot = html_entity_decode($m[1]);
    expect(json_decode($snapshot, true)['memo']['name'])->toBe('verse-title-manager');

    $steps = [
        [['selectedBook' => 'Gen'], []],
        [['selectedChapter' => 1], []],
        [['selectedVerse' => 3], []],
        [['verseTitle' => 'Replayed'], [['path' => '', 'method' => 'saveTitle', 'params' => []]]],
    ];

    foreach ($steps as [$updates, $calls]) {
        $response = test()->actingAs($user)
            ->withSession(['password_hash_web' => $sessionPasswordHash])
            ->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => $calls]]], ['X-Livewire' => '1']);

        if ($response->status() !== 200) {
            return $response;
        }
        $snapshot = $response->json('components.0.snapshot');
    }

    return $response;
}

it('saves through /livewire/update for a current session', function () {
    $current = Auth::guard('web')->hashPasswordForCookie($this->user->getAuthPassword());

    saveTitleOverHttp($this->user, $current)->assertOk();

    expect(file_get_contents($this->dir . '/kjv.osis.xml'))->toContain('>Replayed</title>');
});

it('rejects a /livewire/update save from a session opened before the password changed', function () {
    $old = Auth::guard('web')->hashPasswordForCookie($this->user->getAuthPassword());

    $this->user->forceFill(['password' => 'a-brand-new-password'])->save();

    saveTitleOverHttp($this->user->fresh(), $old)->assertUnauthorized();

    expect(file_get_contents($this->dir . '/kjv.osis.xml'))->not->toContain('Replayed');
});

it('admin:create rotates the remember token when it changes a password', function () {
    $this->user->setRememberToken('old-token');
    $this->user->save();

    $this->artisan('admin:create', ['--email' => 'admin@example.com', '--name' => 'Admin', '--password' => 'another-long-password'])
        ->assertSuccessful();

    expect($this->user->fresh()->getRememberToken())->not->toBe('old-token')->toHaveLength(60);
});
