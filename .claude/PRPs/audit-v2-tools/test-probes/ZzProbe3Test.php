<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
it('probe3: which titles duplicate', function () {
    $f = base_path('tests/Fixtures/mini-kjv.osis.xml'); $this->seed();
    Artisan::call('osis:import', ['file' => $f]); Artisan::call('osis:import', ['file' => $f]);
    Artisan::call('osis:import', ['file' => $f, '--chapter' => 'Gen.1']);
    $cols = implode(',', array_column(DB::select("pragma table_info(titles)"), 'name'));
    $d = DB::select("select title_type, title_text, count(*) n from titles group by title_type, title_text, verse_id, chapter_id having n>1");
    fwrite(STDERR, "\ncols=$cols\n".json_encode($d)."\n");
    expect(true)->toBeTrue();
});
