<?php

use App\Services\Osis\OsisSourceWriter;

const SAMPLE = '<?xml version="1.0" encoding="UTF-8"?><osis><osisText><div type="book" osisID="Gen"><chapter osisID="Gen.1">'
    . '<verse osisID="Gen.1.1" sID="Gen.1.1"/>In the beginning God created the heaven and the earth.<verse eID="Gen.1.1"/>'
    . '<verse osisID="Gen.1.2" sID="Gen.1.2"/>And the earth was without form.<verse eID="Gen.1.2"/>'
    . '</chapter></div></osisText></osis>';

function wellFormed(string $xml): bool
{
    $doc = new DOMDocument();

    return @$doc->loadXML($xml) === true;
}

// --- pure title transforms (SEC-4 / PIPE-1 / PIPE-2) -------------------------------------------

it('stores any title text verbatim - no regex backreferences, entities round-trip', function (string $title) {
    $xml = OsisSourceWriter::setVerseTitle(SAMPLE, 'Gen.1.1', $title);

    expect(wellFormed($xml))->toBeTrue();
    expect(substr_count($xml, ' sID="Gen.1.1"'))->toBe(1); // "$1" used to duplicate the verse marker (leading space: "osisID" contains "sID")
    expect(OsisSourceWriter::readVerseTitle($xml, 'Gen.1.1'))->toBe($title);
})->with(['Costs $10', '$1', '$0 and ${1}', 'Path C:\1\x', 'A & B', '"quoted" & <angled>', "Jacob's Ladder"]);

it('re-saving the title it just read is a no-op (no double encoding)', function () {
    $xml = OsisSourceWriter::setVerseTitle(SAMPLE, 'Gen.1.1', 'Faith & Works');
    $again = OsisSourceWriter::setVerseTitle($xml, 'Gen.1.1', OsisSourceWriter::readVerseTitle($xml, 'Gen.1.1'));

    expect($again)->toBe($xml);
});

it('replaces an existing title and marks it non-canonical', function () {
    $xml = OsisSourceWriter::setVerseTitle(SAMPLE, 'Gen.1.2', 'First');
    $xml = OsisSourceWriter::setVerseTitle($xml, 'Gen.1.2', 'Second');

    expect(substr_count($xml, '<title '))->toBe(1);
    expect($xml)->toContain('<title type="verse" canonical="false">Second</title><verse osisID="Gen.1.2"');
});

it('removes a title and leaves an untitled verse unchanged', function () {
    $xml = OsisSourceWriter::setVerseTitle(SAMPLE, 'Gen.1.1', 'The Creation');

    expect(OsisSourceWriter::removeVerseTitle($xml, 'Gen.1.1'))->toBe(SAMPLE);
    expect(OsisSourceWriter::removeVerseTitle(SAMPLE, 'Gen.1.2'))->toBe(SAMPLE);
});

// --- the locked, atomic, validated write -------------------------------------------------------

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'osis') . '.osis.xml';
    file_put_contents($this->path, SAMPLE);
    chmod($this->path, 0664);
});

afterEach(function () {
    @unlink($this->path);
    @unlink($this->path . '.lock');
});

it('writes atomically and preserves the file mode', function () {
    $status = (new OsisSourceWriter())->update($this->path, fn ($xml) => OsisSourceWriter::setVerseTitle($xml, 'Gen.1.1', 'The Creation'));

    expect($status)->toBe('ok');
    expect(file_get_contents($this->path))->toContain('>The Creation</title>');
    expect(fileperms($this->path) & 0777)->toBe(0664);
    expect(glob($this->path . '.tmp.*'))->toBe([]);
});

it('reports unchanged, refuses invalid XML and failed transforms without touching the file', function () {
    $writer = new OsisSourceWriter();

    expect($writer->update($this->path, fn ($xml) => $xml))->toBe('unchanged');
    expect($writer->update($this->path, fn ($xml) => str_replace('</osis>', '', $xml)))->toBe('invalid_xml');
    expect($writer->update($this->path, fn ($xml) => null))->toBe('transform_failed');
    expect(file_get_contents($this->path))->toBe(SAMPLE);
});

it('answers busy instead of blocking while another writer holds the lock', function () {
    $other = fopen($this->path . '.lock', 'c');
    flock($other, LOCK_EX);

    expect((new OsisSourceWriter())->update($this->path, fn ($xml) => $xml . ' ', 0.3))->toBe('busy');

    flock($other, LOCK_UN);
    fclose($other);
});
