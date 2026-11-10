<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('probe4: fresh command instance per import', function () {
    $f = base_path('tests/Fixtures/mini-kjv.osis.xml');
    $this->seed();
    $fresh = fn () => $this->app[Kernel::class]->setArtisan(null);
    Artisan::call('osis:import', ['file' => $f]);
    $a = DB::table('titles')->count();
    $fresh();
    Artisan::call('osis:import', ['file' => $f]);
    $b = DB::table('titles')->count();
    $fresh();
    Artisan::call('osis:import', ['file' => $f, '--chapter' => 'Gen.1']);
    $c = DB::table('titles')->count();
    $fresh();
    $v = Artisan::call('osis:verify', ['file' => $f]);
    // same-instance reuse for comparison
    Artisan::call('osis:import', ['file' => $f]);
    $d = DB::table('titles')->count();
    fwrite(STDERR, "\nfresh: after1=$a after2=$b afterChapter=$c verify=$v | reused-instance after3=$d\n");
    expect(true)->toBeTrue();
});
