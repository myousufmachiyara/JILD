@extends('layouts.app')

@section('title', 'Products | New Product')

@section('content')
  <div class="row">
    <div class="col">
      <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <section class="card">
          <header class="card-header">
            <div class="d-flex justify-content-between">
              <h2 class="card-title">New Product</h2>
            </div>
            @if ($errors->has('error'))
              <strong class="text-danger">{{ $errors->first('error') }}</strong>
            @endif
          </header>
          <div class="card-body">
            <div class="row pb-3">
              <div class="col-12 col-md-2 mb-2">
                <label class="text-lg-end mb-0">Product Name <span style="color: red;"><strong>*</strong></span></label>
                <input type="text" class="form-control" placeholder="Product Name" name="name" value="{{ old('name') }}" required>
                @error('name')<div class="text-danger">{{ $message }}</div>@enderror
              </div>

              <div class="col-12 col-md-2 mb-2">
                <label>Category <span style="color: red;"><strong>*</strong></span></label>
                <select data-plugin-selecttwo class="form-control select2-js" name="category_id" required>
                  <option value="" selected disabled>Select Category</option>
                  
                </select>
                @error('category_id')<div class="text-danger">{{ $message }}</div>@enderror
              </div>

              <div class="col-12 col-md-2 mb-2">
                <label class="text-lg-end mb-0">SKU <span style="color: red;"><strong>(system generated)</strong></span></label>
                <input type="text" class="form-control" id="base-sku" placeholder="Product SKU" value="{{ old('sku') }}" disabled />
                <!-- <input type="hidden" class="form-control" id="show-sku" placeholder="Product SKU" name="sku" value="{{ old('sku') }}" required /> -->
                @error('sku')<div class="text-danger">{{ $message }}</div>@enderror
              </div>

              <div class="col-12 col-md-2 mb-2">
                <label>Unit</label>
                <select data-plugin-selecttwo class="form-control select2-js" name="measurement_unit" required>
                  <option value="" selected disabled>Select Unit</option>
                  <option value="mtr" {{ old('measurement_unit') == 'mtr' ? 'selected' : '' }}>meter</option>
                  <option value="pcs" {{ old('measurement_unit') == 'pcs' ? 'selected' : '' }}>pieces</option> 
                  <option value="yrd" {{ old('measurement_unit') == 'yrd' ? 'selected' : '' }}>yards</option> 
                  <option value="rd" {{ old('measurement_unit') == 'rd' ? 'selected' : '' }}>round</option> 
                </select>
                @error('measurement_unit')<div class="text-danger">{{ $message }}</div>@enderror
              </div>

              <div class="col-12 col-md-2 mb-2">
                <label>Item Type</label>
                <select data-plugin-selecttwo class="form-control select2-js" name="item_type" id="item_type" required>
                  <option value="" selected disabled>Item Type</option>
                  <option value="fg" {{ old('item_type') == 'fg' ? 'selected' : '' }}>F.G</option>
                  <option value="raw" {{ old('item_type') == 'raw' ? 'selected' : '' }}>Raw</option> 
                </select>
                @error('item_type')<div class="text-danger">{{ $message }}</div>@enderror
              </div>

                <div class="col-12 col-md-2 mb-2">
                    <label>Manufacuring Cost</label>
                    <input type="number" step=".00" class="form-control" value="{{ old('price', '0.00') }}" name="price" required />
                    @error('price')<div class="text-danger">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-2 mb-2">
                    <label>Opening</label>
                    <input type="number" step=".00" class="form-control" value="{{ old('opening_stock', '0') }}" name="opening_stock" required />
                    @error('opening_stock')<div class="text-danger">{{ $message }}</div>@enderror
                </div>
            	
                <div class="col-12 col-md-3 mb-2">
                    <label>Images</label>
                    <input type="file" class="form-control" name="prod_att[]" id="imageUpload" multiple accept="image/png, image/jpeg, image/jpg, image/webp">  
                    @error('prod_att')<div class="text-danger">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-4 mb-2">
                    <label>Description</label>
                    <textarea class="form-control" rows="3" placeholder="Description" name="description">{{ old('description') }}</textarea>
                    @error('description')<div class="text-danger">{{ $message }}</div>@enderror
                </div>
            </div>
          </div>

          <!-- Product variation card body here -->
          <div class="card-body">
            <div class="row">
              <div class="col-6">
                <div class="card-title mb-3" style="display:inline-block">
                  Product Has Variations
                  <input type="hidden" id="hasVariationsHidden" name="has_variations" value="0">
                  <input type="checkbox" id="toggleTableSwitch" value="1" onchange="toggleVariationFields()">
                </div>

                <div id="prodVariationsDiv" style="display:none">
                  <div class="row">
                    <div class="col-12 col-md-4">
                      <label>Variation</label>
                      <select data-plugin-selecttwo class="form-control select2-js" id="attributeSelect" name="variations[0][attribute_id]">
                        <option value="" selected disabled>Select Variation</option>
                        
                      </select>
                      @error('variations.0.attribute_id')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6 mb-2">
                      <label>Values</label>
                      <select data-plugin-selecttwo multiple class="form-control select2-js" id="valueSelect">
                        <option value="" disabled>Values</option>
                      </select>
                      @error('variations.0.value_id')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-2 d-flex align-items-center">
                      <button type="button" class="btn btn-success" id="generate-variations-btn">Generate</button>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-12 col-md-12 mt-3">
                      <table class="table table-bordered" id="variationsTable">
                        <thead>
                          <tr>
                            <th>Variation</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>SKU <span style="color: red;"><strong>(system generated)</strong></span></th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-6">
                <div id="previewContainer" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;margin-bottom:20px;"></div>
              </div>
            </div>
            <footer class="card-footer text-end">
              <a class="btn btn-danger" href="{{ route('products.index') }}">Discard</a>
              <button type="submit" class="btn btn-primary">Create</button>
            </footer>
          </div>
        </section>
      </form>
    </div>
  </div>

  <script>
    // Toggle variation fields
    function toggleVariationFields() {
      const isChecked = document.getElementById('toggleTableSwitch').checked;
      const variationsDiv = document.getElementById('prodVariationsDiv');
      const hasVariationsHidden = document.getElementById('hasVariationsHidden'); // Hidden input field

      variationsDiv.style.display = isChecked ? 'block' : 'none';

      // Update the hidden input value
      hasVariationsHidden.value = isChecked ? "1" : "0";

      // Get variation fields
      const attributeSelect = document.getElementById('attributeSelect');
      const valueSelect = document.getElementById('valueSelect');

      if (isChecked) {
        attributeSelect.setAttribute("required", "required");
        
        // Custom required check for multiple select
        valueSelect.onchange = function() {
          if (valueSelect.selectedOptions.length === 0) {
            valueSelect.setCustomValidity("Please select at least one variation value.");
          } else {
            valueSelect.setCustomValidity("");
          }
        };
      } else {
        attributeSelect.removeAttribute("required");
        valueSelect.onchange = null;
        valueSelect.setCustomValidity(""); // Reset custom validity

        $("#variationsTable tbody").empty(); // Clear variation table
      }
    }

    $(document).ready(function () {
      // Initialize Select2
      $('.select2-js').select2();

      // Define attribute-values from Laravel Blade
      let attributeValues = "";

      // Populate values dropdown when attribute changes
      $("#attributeSelect").change(function () {
        let selectedAttributeId = $(this).val();
        let valuesDropdown = $("#valueSelect");

        valuesDropdown.empty(); // Clear existing options

        if (selectedAttributeId) {
          let selectedAttribute = attributeValues.find(attr => attr.id == selectedAttributeId);
          if (selectedAttribute && selectedAttribute.values.length > 0) {
            selectedAttribute.values.forEach(function (value) {
              valuesDropdown.append('<option value="' + value.id + '">' + value.value + '</option>');
            });
          }
        }
        valuesDropdown.trigger("change"); // Refresh Select2 UI
      });

      // Add selected variations to the table
      $("#generate-variations-btn").click(function () {

        let selectedAttributeText = $("#attributeSelect option:selected").text();
        let selectedAttributeId = $("#attributeSelect option:selected").val();
        let selectedValues = $("#valueSelect").val(); // Get selected values
        let tableBody = $("#variationsTable tbody");

        tableBody.empty();
        
        if (!selectedAttributeText || selectedValues.length === 0) {
          alert("Please select an attribute and at least one value.");
          return;
        }

        selectedValues.forEach((valueId, index) => { // Added index here
          let valueText = $("#valueSelect option[value='" + valueId + "']").text();
          // let sku = $('#base-sku').val();
          // let value= ${sku}${valueText}; 

          // Prevent duplicate entries
          if ($("#variationsTable tbody tr[data-value='" + valueId + "']").length === 0) {
              let row = `
              <tr data-value="${valueId}"> 
                <td><strong>${selectedAttributeText}:</strong> ${valueText}
                  <input type="hidden" class="form-control" name="variations[${index}][attribute_id]" value="1" required>
                  <input type="hidden" class="form-control" name="variations[${index}][attribute_value_id]" value="${valueId}" required>
                </td>
                <td><input type="number" class="form-control" name="variations[${index}][stock]" value="0" required></td>
                <td><input type="number" class="form-control" name="variations[${index}][price]" value="0" required></td>
                <td><input type="text" class="form-control" name="variations[${index}][sku]" disabled></td>
                <td><button class="btn btn-danger remove-btn btn-xs"><i class="fa fa-times"></i></button></td>
              </tr>`;
              tableBody.append(row);
          }
        });
      });

      // Remove row from table
      $(document).on("click", ".remove-btn", function () {
        $(this).closest("tr").remove();
      });

      // $('select[name="category_id"]').on('change', function() {
      //   var selectedOption = $(this).find('option:selected'); 
      //   var dataDisplay = selectedOption.data('display'); 
      //   let sku = dataDisplay + "-";
      //   document.getElementById("base-sku").value = sku;
      //   document.getElementById("show-sku").value = sku;
      // });


      // Trigger on change
      $('#item_type').change(toggleDivs);

      // Trigger on page load in case of old value
      toggleDivs();
    });

    document.getElementById("imageUpload").addEventListener("change", function(event) {
        const files = event.target.files;
        const previewContainer = document.getElementById("previewContainer");
        previewContainer.innerHTML = ""; // Clear previous previews

        for (let file of files) {
            if (file && file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement("img");
                    img.src = e.target.result;
                    img.style.maxWidth = "200px"; // Adjust preview size
                    img.style.maxHeight = "200px";
                    img.style.border = "1px solid #ddd";
                    img.style.borderRadius = "5px";
                    img.style.padding = "5px";
                    previewContainer.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        }
    });

  </script>
@endsection