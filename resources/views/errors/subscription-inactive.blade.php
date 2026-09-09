@extends('layouts.status')
@section('code', '402')
@section('title')
    {{ ($status ?? null) === \App\Enums\SubscriptionStatus::NotStarted
        ? 'Subscription has not started'
        : 'Subscription expired' }}
@endsection
@section('message')
    {{ ($status ?? null) === \App\Enums\SubscriptionStatus::NotStarted
        ? 'This workspace becomes available when its subscription period begins.'
        : 'This workspace\'s subscription has ended. Renew it to restore access.' }}
@endsection
