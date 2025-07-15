@extends('layouts.app')

@section('title', 'Edit | Production Receiving')

@section('content')
<div class="row">
  <form action="{{ route('production.receiving.update', $receiving->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    @include('production-receiving._form', [
      'formTitle' => 'Edit Production Receiving',
      'btnText' => 'Update',
      'receiving' => $receiving,
      'productions' => $productions,
      'products' => $products
    ])
  </form>
</div>
@endsection
