@extends('statamic::layout')
@section('title', 'Events')

@section('content')
    <events-dashboard :events="{{ json_encode($events) }}"></events-dashboard>
@endsection
