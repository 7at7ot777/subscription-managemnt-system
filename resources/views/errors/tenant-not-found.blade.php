@extends('layouts.status')
@section('code', '404')
@section('title', 'Workspace not found')
@section('message', 'There is no workspace at this address. Check the link and try again.')
@section('action')
    <a class="button" href="{{ config('app.url') }}">Go to the home page</a>
@endsection
