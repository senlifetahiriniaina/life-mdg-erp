@extends('inventory::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {{ config('inventory.name') }}</p>
@endsection
