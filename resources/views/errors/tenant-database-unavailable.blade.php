{{-- Intentionally shows nothing about the connection. Details go to the log only. --}}
@extends('layouts.status')
@section('code', '503')
@section('title', 'Temporarily unavailable')
@section('message', 'This workspace cannot be reached right now. Our team has been notified. Please try again shortly.')
