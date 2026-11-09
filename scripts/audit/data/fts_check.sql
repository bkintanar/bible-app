-- Run with: sqlite3 -readonly <db> < fts_check.sql   (only temp-schema objects are created)
-- Index layout since migration 2026_11_10_000001: one searchable column (text = scripture only).
.headers on
.mode list
.separator ' | '
select 'fts rows (content view)', count(*) from verses_fts;
select 'fts docsize rows', count(*) from verses_fts_docsize;
select 'docsize ids not in verses', count(*) from verses_fts_docsize d left join verses v on v.id=d.id where v.id is null;
select 'verses not in docsize', count(*) from verses v left join verses_fts_docsize d on d.id=v.id where d.id is null;
-- reference index built from the same external-content view, in TEMP only
create virtual table temp.ref using fts5(verse_id UNINDEXED, osis_id UNINDEXED, reference UNINDEXED, book_name UNINDEXED, text);
insert into temp.ref(rowid, verse_id, osis_id, reference, book_name, text)
  select verse_id, verse_id, osis_id, reference, book_name, text from search_results;
create virtual table temp.v_live using fts5vocab(main, 'verses_fts', 'col');
create virtual table temp.v_ref using fts5vocab(temp, 'ref', 'col');
select 'live terms', count(*), sum(cnt) from temp.v_live;
select 'ref  terms', count(*), sum(cnt) from temp.v_ref;
select 'terms differing (term,col,doc,cnt)', count(*) from (
  select term,col,doc,cnt from temp.v_live except select term,col,doc,cnt from temp.v_ref
  union all
  select term,col,doc,cnt from temp.v_ref except select term,col,doc,cnt from temp.v_live);
select 'tokens in text col', sum(cnt) from temp.v_live where col='text';
-- words that occur only in study notes / title markup must not be searchable
select 'docs matching Heb (note marker)', count(*) from verses_fts where verses_fts match 'Heb';
select 'docs matching midriff (note-only word)', count(*) from verses_fts where verses_fts match 'midriff';
select 'docs matching uppercase (title CSS)', count(*) from verses_fts where verses_fts match 'uppercase';
select 'phrase "all the disciples forsook him"', count(*) from verses_fts where verses_fts match '"all the disciples forsook him"';
