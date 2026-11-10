<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('probe5: what a chapter-1 scoped reprojection deletes', function () {
    $f = base_path('tests/Fixtures/mini-kjv.osis.xml');
    $this->seed();
    Artisan::call('osis:import', ['file' => $f]);
    $before = DB::table('titles')->pluck('title_text', 'id')->all();
    $this->app[Kernel::class]->setArtisan(null);
    Artisan::call('osis:import', ['file' => $f, '--chapter' => 'Gen.1']);
    $after = DB::table('titles')->pluck('title_text')->all();
    fwrite(STDERR, "\nLOST: ".json_encode(array_values(array_diff($before, $after)))."\n");
    $this->app[Kernel::class]->setArtisan(null);
    Artisan::call('osis:import', ['file' => $f, '--verse' => 'Gen.1.1']);
    fwrite(STDERR, 'after --verse Gen.1.1: '.DB::table('titles')->count()."\n");
    $this->app[Kernel::class]->setArtisan(null);
    Artisan::call('osis:import', ['file' => $f, '--book' => 'Gen']);
    fwrite(STDERR, 'after --book Gen: '.DB::table('titles')->count()."\n");
    expect(true)->toBeTrue();
});
