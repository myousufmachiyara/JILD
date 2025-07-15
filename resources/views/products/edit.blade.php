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
            <div class="col-md-2">
              <label>Product Name *</label>
              <input type="text" name="name" class="form-control" required value="{{ old('name', $product->name) }}">
            </div>

            <div class="col-md-2">
              <label>Category *</label>
              <select name="category_id" class="form-control select2-js" required>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label>SKU</label>
              <input type="text" name="sku" id="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
            </div>

            <div class="col-md-2">
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

            <div class="col-md-2">
              <label>Item Type</label>
              <select name="item_type" class="form-control select2-js">
                <option value="fg" {{ $product->item_type == 'fg' ? 'selected' : '' }}>F.G</option>
                <option value="raw" {{ $product->item_type == 'raw' ? 'selected' : '' }}>Raw</option>
              </select>
            </div>

            <div class="col-md-2">
              <label>M.Cost</label>
              <input type="number" step="any" name="manufacturing_cost" class="form-control" value="{{ old('manufacturing_cost', $product->manufacturing_cost) }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Opening Stock</label>
              <input type="number" step="any" name="opening_stock" class="form-control" value="{{ old('opening_stock', $product->opening_stock) }}">
            </div>

            <div class="col-md-4 mt-3">
              <label>Description</label>
              <textarea name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
            </div>

            <div class="col-md-4 mt-3">
              <label>Product Images</label>
              <input type="file" name="prod_att[]" multiple class="form-control">
              <small class="text-muted">Leave empty if you don't want to update images.</small>
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-md-12">
              <h5>Existing Variations</h5>
              <div id="variation-section">
                @foreach($product->variations as $i => $variation)
                  <div class="variation-block border p-2 mb-3 existing-variation">
                    <input type="hidden" name="variations[{{ $i }}][id]" value="{{ $variation->id }}">
                    <div class="row">
                      <div class="col-md-3">
                        <label>SKU</label>
                        <input type="text" name="variations[{{ $i }}][sku]" class="form-control sku-field" value="{{ $variation->sku }}">
                      </div>
                      <div class="col-md-2">
                        <label>M.Cost</label>
                        <input type="number" step="any" name="variations[{{ $i }}][manufacturing_cost]" class="form-control" value="{{ $variation->manufacturing_cost }}">
                      </div>
                      <div class="col-md-2">
                        <label>Stock</label>
                        <input type="number" step="any" name="variations[{{ $i }}][stock_quantity]" class="form-control" value="{{ $variation->stock_quantity }}">
                      </div>
                      <div class="col-md-4">
                        <label>Attributes</label>
                        <select name="variations[{{ $i }}][attributes][]" multiple class="form-control select2-js variation-attributes">
                          @foreach($attributes as $attribute)
                            @foreach($attribute->values as $value)
                              <option value="{{ $value->id }}" {{ $variation->attributeValues->pluck('id')->contains($value->id) ? 'selected' : '' }}>
                                {{ $attribute->name }} - {{ $value->value }}
                              </option>
                            @endforeach
                          @endforeach
                        </select>
                      </div>
                      <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-danger remove-existing-variation" data-id="{{ $variation->id }}">X</button>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>

              <div class="col-md-12 mt-3">
                <h5>Add New Variations</h5>
                <div id="new-variation-section"></div>
                <button type="button" class="btn btn-sm btn-secondary mt-2" id="addNewVariationBtn">Add Variation</button>
              </div>
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

  $(document).on('change', '.variation-attributes', function () {
    const block = $(this).closest('.variation-block');
    const selectedOptions = $(this).find('option:selected');
    const attrTexts = [];
    selectedOptions.each(function () {
      attrTexts.push($(this).text().split(' - ')[1]);
    });
    const variationName = attrTexts.join(' - ');
    const mainSku = $('#sku').val();
    block.find('.sku-field').val(mainSku + ' - ' + variationName);
  });

  let newVariationIndex = 0;
  $('#addNewVariationBtn').click(function () {
    newVariationIndex++;
    const html = `
      <div class="variation-block border p-2 mb-3">
        <div class="row">
          <div class="col-md-3">
            <label>SKU</label>
            <input type="text" name="new_variations[${newVariationIndex}][sku]" class="form-control sku-field">
          </div>
          <div class="col-md-2">
            <label>M.Cost</label>
            <input type="number" step="any" name="new_variations[${newVariationIndex}][manufacturing_cost]" class="form-control">
          </div>
          <div class="col-md-2">
            <label>Stock</label>
            <input type="number" step="any" name="new_variations[${newVariationIndex}][stock_quantity]" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Attributes</label>
            <select name="new_variations[${newVariationIndex}][attributes][]" multiple class="form-control select2-js variation-attributes">
              @foreach($attributes as $attribute)
                @foreach($attribute->values as $value)
                  <option value="{{ $value->id }}">{{ $attribute->name }} - {{ $value->value }}</option>
                @endforeach
              @endforeach
            </select>
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-danger remove-new-variation">X</button>
          </div>
        </div>
      </div>
    `;    
    $('#new-variation-section').append(html);
    $('.select2-js').select2();
  });

  $(document).on('click', '.remove-new-variation', function () {
    $(this).closest('.variation-block').remove();
  });

  $(document).on('click', '.remove-existing-variation', function () {
    const block = $(this).closest('.variation-block');
    const variationId = $(this).data('id');
    if (confirm('Are you sure you want to remove this variation?')) {
      block.hide();
      block.append(`<input type="hidden" name="removed_variations[]" value="${variationId}" class="removed-variation-flag">`);
      const undoHtml = `<div class="undo-variation-alert alert alert-warning mb-3" data-id="${variationId}">
        Variation removed. <button type="button" class="btn btn-sm btn-link p-0 undo-remove-variation">Undo</button>
      </div>`;
      block.after(undoHtml);
    }
  });

  $(document).on('click', '.undo-remove-variation', function () {
    const alertBox = $(this).closest('.undo-variation-alert');
    const variationId = alertBox.data('id');
    const block = $('.variation-block').has(`input[value="${variationId}"]`);
    block.find('.removed-variation-flag').remove();
    block.show();
    alertBox.remove();
  });
});
</script>
@endsection
