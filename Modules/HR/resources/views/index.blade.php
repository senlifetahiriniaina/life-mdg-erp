@extends('hr::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {{ config('hr.name') }}</p>
@endsection
