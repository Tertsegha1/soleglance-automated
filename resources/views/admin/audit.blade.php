<x-app-layout title="Audit log">
    @include('admin._nav')
    <x-card>@include('admin._actions', ['actions' => $actions])</x-card>
    <div class="mt-4">{{ $actions->links() }}</div>
</x-app-layout>
