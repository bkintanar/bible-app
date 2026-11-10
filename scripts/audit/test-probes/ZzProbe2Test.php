<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('probe2: titles across imports', function () {
    $fixture = base_path('tests/Fixtures/mini-kjv.osis.xml');
    $this->seed();
    $rows = fn () => DB::table('titles')->select('title_type', 'title_text', 'verse_id', 'chapter_id', 'book_id')->orderBy('id')->get()->map(fn ($r) => implode('|', (array) $r))->all();
    Artisan::call('osis:import', ['file' => $fixture]);
    $a = DB::table('titles')->count();
    $ra = $rows();
    $v1 = Artisan::call('osis:verify', ['file' => $fixture]);
    $o1 = Artisan::output();
    Artisan::call('osis:import', ['file' => $fixture]);
    $b = DB::table('titles')->count();
    $rb = $rows();
    $v2 = Artisan::call('osis:verify', ['file' => $fixture]);
    Artisan::call('osis:import', ['file' => $fixture, '--chapter' => 'Gen.1']);
    $c = DB::table('titles')->count();
    fwrite(STDERR, "\nafter1=$a verify1=$v1 after2=$b verify2=$v2 afterChapterScoped=$c\n");
    preg_match('/titles\s*\|\s*(\d+)\s*\|\s*(\d+)/', $o1, $m);
    fwrite(STDERR, 'verify1 titles row: '.json_encode($m)."\n");
    fwrite(STDERR, 'ONLY-AFTER-2: '.json_encode(array_values(array_diff($rb, $ra)))."\n");
    fwrite(STDERR, 'ALL-1: '.json_encode($ra)."\n");
    expect(true)->toBeTrue();
});
