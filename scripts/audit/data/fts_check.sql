-- Run with: sqlite3 -readonly <db> < fts_check.sql   (only temp-schema objects are created)
.headers on
.mode list
.separator ' | '
select 'fts rows (content view)', count(*) from verses_fts;
select 'fts docsize rows', count(*) from verses_fts_docsize;
select 'docsize ids not in verses', count(*) from verses_fts_docsize d left join verses v on v.id=d.id where v.id is null;
select 'verses not in docsize', count(*) from verses v left join verses_fts_docsize d on d.id=v.id where d.id is null;
-- reference index built from the same external-content view, in TEMP only
create virtual table temp.ref using fts5(verse_id UNINDEXED, osis_id UNINDEXED, reference UNINDEXED, book_name UNINDEXED, text, searchable_content);
insert into temp.ref(rowid, verse_id, osis_id, reference, book_name, text, searchable_content)
  select verse_id, verse_id, osis_id, reference, book_name, text, searchable_content from search_results;
create virtual table temp.v_live using fts5vocab(main, 'verses_fts', 'col');
create virtual table temp.v_ref using fts5vocab(temp, 'ref', 'col');
select 'live terms', count(*), sum(cnt) from temp.v_live;
select 'ref  terms', count(*), sum(cnt) from temp.v_ref;
select 'terms differing (term,col,doc,cnt)', count(*) from (
  select term,col,doc,cnt from temp.v_live except select term,col,doc,cnt from temp.v_ref
  union all
  select term,col,doc,cnt from temp.v_ref except select term,col,doc,cnt from temp.v_live);
-- docsize must equal per-doc token counts of a fresh index
create temp table ref_ds as select id, sz from main.verses_fts_docsize;
select 'docsize blobs differing from ref', count(*) from main.verses_fts_docsize a join temp.ref_docsize b on a.id=b.id where a.sz<>b.sz;
-- what fraction of the index is note/title/word text rather than scripture text
select 'tokens in text col', sum(cnt) from temp.v_live where col='text';
select 'tokens in searchable_content col', sum(cnt) from temp.v_live where col='searchable_content';
-- note-only vocabulary: terms that occur in study_notes text but never in any verse text from xml are hard to compute here; sample terms instead
select 'docs matching Heb (note marker)', count(*) from verses_fts where verses_fts match 'text:Heb';
select 'docs matching Gr (note marker)', count(*) from verses_fts where verses_fts match 'text:Gr';
select 'docs matching expansion', group_concat(osis_id) from (select osis_id from verses_fts where verses_fts match 'text:expansion' limit 5);
