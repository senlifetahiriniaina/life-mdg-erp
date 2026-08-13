@extends('projects::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {{ config('projects.name') }}</p>
@endsection
