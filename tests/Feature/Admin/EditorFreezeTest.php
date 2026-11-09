<?php

use App\Livewire\VerseTitleManager;
use App\Models\User;
use Livewire\Livewire;

/*
 * The verse-title editor writes the OSIS source of truth. While the importer is being changed it
 * must be frozen - for the page AND for Livewire actions replayed from a page that was already open.
 */

function editorAdmin(): User
{
    return User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
}

it('serves 503 for the editor page when frozen', function () {
    config(['bible.editor_enabled' => false]);

    $this->actingAs(editorAdmin())->get('/admin/verse-titles')->assertStatus(503);
});

it('refuses Livewire round-trips when frozen', function () {
    config(['bible.editor_enabled' => false]);
    $this->actingAs(editorAdmin());

    // Livewire's test harness turns abort() into the response status.
    Livewire::test(VerseTitleManager::class)->assertStatus(503);
});

it('serves the editor when enabled', function () {
    config(['bible.editor_enabled' => true]);

    $this->withoutVite()->actingAs(editorAdmin())->get('/admin/verse-titles')->assertOk();
});

it('still requires login regardless of the freeze', function () {
    config(['bible.editor_enabled' => false]);

    $this->get('/admin/verse-titles')->assertRedirect('/login');
});
