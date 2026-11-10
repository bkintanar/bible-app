<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'correct-horse-battery']);
});

it('sends guests to the login page', function () {
    $this->get('/admin/verse-titles')->assertRedirect(route('login'));
});

it('logs in with the right password and lands on the editor', function () {
    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-battery'])
        ->assertRedirect(route('admin.verse-titles'));

    $this->assertAuthenticatedAs($this->user);
    $this->get('/login')->assertRedirect(route('admin.verse-titles'));
});

it('logs out with a POST and forgets the session', function () {
    $this->actingAs($this->user)->post('/logout')->assertRedirect(route('bible.index'));

    $this->assertGuest();
});

it('never logs out on a GET (no logout CSRF via an <img>)', function () {
    $this->actingAs($this->user);

    expect($this->get('/logout')->status())->toBeIn([404, 405]);
    $this->assertAuthenticatedAs($this->user);
});
