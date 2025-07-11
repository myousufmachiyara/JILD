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
          <h2 class="card-title">Edit Product</h2>
        </header>
        <div class="card-body">
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

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
              <label>Measurement Unit *</label>
              <select name="measurement_unit" class="form-control" required>
                <option value="">-- Select Unit --</option>
                @foreach($units as $unit)
                  <option value="{{ $unit->id }}" {{ $product->measurement_unit == $unit->id ? 'selected' : '' }}>
                    {{ $unit->name }} ({{ $unit->shortcode }})
                  </option>
                @endforeach
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
              <input type="number" step="any" name="manufacturing_cost" class="form-control" value="{{ old('manufacturing_cost', $product->manufacturing_cost) }}">
            </div>

            <div class="col-md-4 mt-3">
              <label>Opening Stock</label>
              <input type="number" step="any" name="opening_stock" class="form-control" value="{{ old('opening_stock', $product->opening_stock) }}">
            </div>

            <div class="col-md-8 mt-3">
              <label>Description</label>
              <textarea name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
            </div>

            <div class="col-md-12 mt-3">
              <label>Product Images</label>
              <input type="file" name="prod_att[]" multiple class="form-control">
              <small class="text-muted">Leave empty if you don't want to update images.</small>
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
                        <input type="number" step="any" name="variations[{{ $i }}][price]" class="form-control" value="{{ $variation->price }}">
                      </div>
                      <div class="col-md-2">
                        <label>Stock</label>
                        <input type="number" step="any" name="variations[{{ $i }}][stock]" class="form-control" value="{{ $variation->stock }}">
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
                        <input type="text" name="variations[${variationIndex}][sku]" class="form-control sku-field" required>
                    </div>
                    <div class="col-md-2">
                        <label>Price</label>
                        <input type="number" step="any" name="variations[${variationIndex}][price]" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Stock</label>
                        <input type="number" step="any" name="variations[${variationIndex}][stock]" class="form-control">
                    </div>
                    <div class="col-md-5">
                        <label>Attributes</label>
                        <select name="variations[${variationIndex}][attributes][]" multiple class="form-control select2-js variation-attributes" required>
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

    // Auto-generate SKU on attribute change
    $(document).on('change', '.variation-attributes', function () {
        const block = $(this).closest('.variation-block');
        const selectedOptions = $(this).find('option:selected');
        const attrTexts = [];

        selectedOptions.each(function () {
            attrTexts.push($(this).text().split(' - ')[1]); // only value name
        });

        const variationName = attrTexts.join(' - ');
        const mainSku = $('input[name="sku"]').val();
        block.find('.sku-field').val(mainSku + ' - ' + variationName);
    });
});
</script>
@endsection
