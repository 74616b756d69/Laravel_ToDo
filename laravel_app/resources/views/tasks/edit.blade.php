@extends('layouts.app')

@section('title', 'タスク編集')

@section('content')
    <x-page-heading title="タスクを編集" :back="route('tasks.show', $task)" back-label="詳細に戻る" />

    @include('tasks.form', [
        'action' => route('tasks.update', $task),
        'method' => 'PUT',
        'submitLabel' => '更新する',
        'cancelUrl' => route('tasks.show', $task),
    ])
@endsection
