@extends('layouts.app')
@section('title', 'Select Products to Print Barcode')

@section('content')
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
                    <td><input type="checkbox" name="variations[{{ $loop->index }}][id]" value="{{ $variation->id }}"></td>
                    <td>{{ $variation->product->name }}</td>
                    <td>{{ $variation->sku }}</td>
                    <td>{{ $variation->product->selling_price }}</td>
                    <td><input type="number" name="variations[{{ $loop->index }}][quantity]" min="1" value="1" class="form-control"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="submit" class="btn btn-primary">Generate Barcodes</button>
</form>

@endsection
