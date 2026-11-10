<?php

// AUDIT PROBE - lives only in the scratchpad copy, never in the repo.

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('probe: UserFactory can create a user', function () {
    $u = User::factory()->create();
    expect($u->exists)->toBeTrue();
});

it('probe: guest is redirected away from the editor', function () {
    $this->get('/admin/verse-titles')->assertRedirect('/login');
});

it('probe: valid login authenticates and redirects to editor', function () {
    User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'correct-horse-battery']);
    $this->post('/login', ['email' => 'a@example.com', 'password' => 'correct-horse-battery'])
        ->assertRedirect(route('admin.verse-titles'));
    $this->assertAuthenticated();
});

it('probe: 6th bad attempt is locked out by controller limiter', function () {
    User::create(['name' => 'A', 'email' => 'a@example.com', 'password' => 'correct-horse-battery']);
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors(['email' => 'Those credentials do not match our records.']);
    }
    $r = $this->post('/login', ['email' => 'a@example.com', 'password' => 'correct-horse-battery']);
    $r->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many attempts');
    $this->assertGuest();
});

it('probe: nativephp event route is blocked', function () {
    $this->post('/_native/api/events', ['event' => 'Illuminate\\Support\\Stringable', 'payload' => ['x']])
        ->assertNotFound();
});

it('probe: admin:create validates and creates', function () {
    $this->artisan('admin:create', ['--email' => 'b@example.com', '--name' => 'B', '--password' => 'short'])
        ->assertExitCode(1);
    $this->artisan('admin:create', ['--email' => 'b@example.com', '--name' => 'B', '--password' => 'long-enough-pass'])
        ->assertExitCode(0);
    expect(User::where('email', 'b@example.com')->exists())->toBeTrue();
});

it('probe: real importer projects a tiny fixture into :memory:', function () {
    $fixture = base_path('tests/Fixtures/mini-kjv.osis.xml');
    $this->seed();
    $t = microtime(true);
    $this->artisan('osis:import', ['file' => $fixture])->assertExitCode(0);
    $secs = round(microtime(true) - $t, 2);
    fwrite(STDERR, "\nIMPORT secs={$secs} verses=".DB::table('verses')->count()
        .' books='.DB::table('books')->count()
        .' chapters='.DB::table('chapters')->count()
        .' red_letter='.DB::table('red_letter_text')->count()
        .' translator_changes='.DB::table('translator_changes')->count()
        .' titles='.DB::table('titles')->count()
        .' fts='.DB::selectOne('select count(*) c from verses_fts')->c."\n");

    // idempotency: second run must not duplicate
    $before = DB::table('verses')->count();
    $this->artisan('osis:import', ['file' => $fixture])->assertExitCode(0);
    expect(DB::table('verses')->count())->toBe($before);

    $vc = \Illuminate\Support\Facades\Artisan::call('osis:verify', ['file' => $fixture]);
    fwrite(STDERR, 'VERIFY exit='.$vc."\n".substr(\Illuminate\Support\Facades\Artisan::output(), -1200)."\n");

    $page = $this->get('/books/Gen/chapters/1');
    fwrite(STDERR, 'CHAPTER status='.$page->status().' bytes='.strlen($page->getContent())
        .' hasText='.(str_contains($page->getContent(), 'In the beginning') ? 'y' : 'n')."\n");

    $api = $this->getJson('/api/books');
    fwrite(STDERR, 'API books status='.$api->status().' keys='.implode(',', array_keys($api->json() ?? []))."\n");
    $v = $this->getJson('/api/books/Gen/chapters/1/verses');
    fwrite(STDERR, 'API verses status='.$v->status().' body='.substr($v->getContent(), 0, 300)."\n");
    $s = $this->getJson('/api/search?q=beginning');
    fwrite(STDERR, 'API search status='.$s->status().' body='.substr($s->getContent(), 0, 300)."\n");
    $s2 = $this->getJson('/api/search?q=%22unbalanced');
    fwrite(STDERR, 'API search-quote status='.$s2->status()."\n");
    $s3 = $this->getJson('/api/search?q=NEAR(a b) OR *');
    fwrite(STDERR, 'API search-fts-ops status='.$s3->status()."\n");
});
