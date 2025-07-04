@extends('layouts.app')

@section('title', 'Product | Edit Product')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header">
          <div class="d-flex justify-content-between">
            <h2 class="card-title">Edit Product</h2>
          </div>
        </header>
        <div class="card-body">
          <div class="row pb-3">
            <div class="col-md-4">
              <label>Product Name *</label>
              <input type="text" name="name" class="form-control" required value="{{ old('name', $product->name) }}">
            </div>
            <div class="col-md-4">
              <label>Category *</label>
              <select name="category_id" class="form-control select2-js" required>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label>SKU</label>
              <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
            </div>
            <div class="col-md-4 mt-3">
              <label>Unit</label>
              <select name="measurement_unit" class="form-control select2-js">
                <option value="mtr" {{ $product->measurement_unit == 'mtr' ? 'selected' : '' }}>Meter</option>
                <option value="pcs" {{ $product->measurement_unit == 'pcs' ? 'selected' : '' }}>Pieces</option>
                <option value="yrd" {{ $product->measurement_unit == 'yrd' ? 'selected' : '' }}>Yards</option>
                <option value="rd" {{ $product->measurement_unit == 'rd' ? 'selected' : '' }}>Round</option>
              </select>
            </div>
            <div class="col-md-4 mt-3">
              <label>Item Type</label>
              <select name="item_type" class="form-control select2-js">
                <option value="fg" {{ $product->item_type == 'fg' ? 'selected' : '' }}>F.G</option>
                <option value="raw" {{ $product->item_type == 'raw' ? 'selected' : '' }}>Raw</option>
              </select>
            </div>
            <div class="col-md-4 mt-3">
              <label>Manufacturing Cost</label>
              <input type="number" step=".01" name="price" class="form-control" value="{{ old('price', $product->price) }}">
            </div>
            <div class="col-md-4 mt-3">
              <label>Opening Stock</label>
              <input type="number" name="opening_stock" class="form-control" value="{{ old('opening_stock', $product->opening_stock) }}">
            </div>
            <div class="col-md-8 mt-3">
              <label>Description</label>
              <textarea name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
            </div>
            <div class="col-md-12 mt-3">
              <label>Product Images</label>
              <input type="file" name="prod_att[]" multiple class="form-control">
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-md-12">
              <h5>Product Variations</h5>
              <div id="variation-section">
                @foreach($product->variations as $i => $variation)
                  <div class="variation-block border p-2 mb-3">
                    <input type="hidden" name="variations[{{ $i }}][id]" value="{{ $variation->id }}">
                    <div class="row">
                      <div class="col-md-3">
                        <label>SKU</label>
                        <input type="text" name="variations[{{ $i }}][sku]" class="form-control" value="{{ $variation->sku }}">
                      </div>
                      <div class="col-md-2">
                        <label>Price</label>
                        <input type="number" step=".01" name="variations[{{ $i }}][price]" class="form-control" value="{{ $variation->price }}">
                      </div>
                      <div class="col-md-2">
                        <label>Stock</label>
                        <input type="number" name="variations[{{ $i }}][stock]" class="form-control" value="{{ $variation->stock }}">
                      </div>
                      <div class="col-md-5">
                        <label>Attributes</label>
                        <select name="variations[{{ $i }}][attributes][]" multiple class="form-control select2-js">
                          @foreach($attributes as $attribute)
                            @foreach($attribute->values as $value)
                              <option value="{{ $value->id }}" {{ $variation->attributeValues->pluck('id')->contains($value->id) ? 'selected' : '' }}>
                                {{ $attribute->name }} - {{ $value->value }}
                              </option>
                            @endforeach
                          @endforeach
                        </select>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addVariationRow()">Add Variation</button>
            </div>
          </div>

        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('products.index') }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">Update Product</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {
    $('.select2-js').select2();

    let variationIndex = $('#variation-section .variation-block').length;

    window.addVariationRow = function () {
        variationIndex++;
        const variationHtml = `
            <div class="variation-block border p-2 mb-3">
                <div class="row">
                    <div class="col-md-3">
                        <label>SKU</label>
                        <input type="text" name="variations[${variationIndex}][sku]" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Price</label>
                        <input type="number" step=".01" name="variations[${variationIndex}][price]" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Stock</label>
                        <input type="number" name="variations[${variationIndex}][stock]" class="form-control">
                    </div>
                    <div class="col-md-5">
                        <label>Attributes</label>
                        <select name="variations[${variationIndex}][attributes][]" multiple class="form-control select2-js" required>
                            @foreach ($attributes as $attribute)
                                @foreach ($attribute->values as $value)
                                    <option value="{{ $value->id }}">{{ $attribute->name }} - {{ $value->value }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        `;

        $('#variation-section').append(variationHtml);
        $('.select2-js').select2();
    }
});
</script>
@endsection
