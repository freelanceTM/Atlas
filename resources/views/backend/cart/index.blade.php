@extends('backend.pos-layout')
@section('content')
<script>
    window.__POS_CATEGORIES__ = @json(\App\Models\Category::where('status', 1)->select('id','name')->orderBy('name')->get());
    window.__POS_USER__ = @json(auth()->user()->name ?? 'Кассир');
</script>
<div id="cart"></div>
@endsection
