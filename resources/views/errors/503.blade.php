@php($hideSearch = true) {{-- search is down too while in maintenance --}}
@extends('errors.reader')
@section('code', '503')
@section('title', 'Back shortly')
@section('message', 'The Bible is being updated. This usually takes less than a minute - please try again shortly.')
