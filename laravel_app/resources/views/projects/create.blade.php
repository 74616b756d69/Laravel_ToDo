@extends('layouts.app')

@section('title', 'プロジェクトを作成')

@section('content')
    <x-page-heading title="プロジェクトを作成" :back="route('projects.index')" back-label="プロジェクト一覧" />

    @include('projects.form', [
        'action' => route('projects.store'),
        'method' => 'POST',
        'submitLabel' => '作成',
        'cancelUrl' => route('projects.index'),
    ])
@endsection
