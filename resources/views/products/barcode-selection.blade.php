@extends('layouts.app')
@section('title', 'Select Products to Print Barcode')

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>There were some problems with your input:</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

<form action="{{ route('products.generateBarcodes') }}" method="POST">
    @csrf
    <table class="table">
        <thead>
            <tr>
                <th>Select</th>
                <th>Product</th>
                <th>Variation</th>
                <th>Selling Price</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($variations as $variation)
                <tr>
                    <td>
                        <input type="checkbox" name="selected_variations[]" value="{{ $variation->id }}">
                    </td>
                    <td>{{ $variation->product->name }}</td>
                    <td>{{ $variation->sku }}</td>
                    <td>{{ $variation->product->selling_price }}</td>
                    <td>
                        <input type="number" name="quantity[{{ $variation->id }}]" value="0" class="form-control">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="submit" class="btn btn-primary">Generate Barcodes</button>
</form>

@endsection
