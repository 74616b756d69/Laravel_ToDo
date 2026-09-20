@extends('layouts.app')

@section('title', 'タスク作成')

@section('content')
    <x-page-heading title="タスクを作成" :back="route('tasks.index')" back-label="一覧に戻る" />

    @include('tasks.form', [
        'action' => route('tasks.store'),
        'method' => 'POST',
        'submitLabel' => '追加する',
        'cancelUrl' => route('tasks.index'),
    ])
@endsection
