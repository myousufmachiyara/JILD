@extends('layouts.app')

@section('title', 'Production | Order Receiving')

@section('content')
<div class="row">
  <form action="{{ route('production.receiving.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if ($errors->has('error'))
      <strong class="text-danger">{{ $errors->first('error') }}</strong>
    @endif

    <div class="col-12 mb-4">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Order Receiving</h2>
        </header>
        <div class="card-body">
          <div class="row mb-4">
            <div class="col-md-2">
              <label>GRN #</label>
              <input type="text" name="grn_no" class="form-control" readonly />
            </div>
            <div class="col-md-2">
              <label>Receiving Date</label>
              <input type="date" name="rec_date" class="form-control" value="{{ date('Y-m-d') }}" required />
            </div>
            <div class="col-md-2">
              <label>Production Order</label>
              <select name="production_id" class="form-control select2-js" required>
                <option value="" disabled selected>Select Production</option>
                @foreach($productions as $prod)
                  <option value="{{ $prod->id }}">{{ $prod->id }} - {{ $prod->vendor->name ?? '-' }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="col-12 mb-4">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Product Details</h2>
        </header>
        <div class="card-body">
          <table class="table table-bordered" id="itemTable">
            <thead>
              <tr>
                <th width="15%">Item Code</th>
                <th>Item</th>
                <th>Variation</th>
                <th width="10%" >M. Cost</th>
                <th width="10%">Received</th>
                <th>Remarks</th>
                <th width="10%">Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <input type="text" class="form-control product-code" placeholder="Enter Product Code" onblur="fetchByCode($(this).closest('tr').index())">
                </td>
                <td>
                    <select name="item_details[0][product_id]" class="form-control select2-js product-select" required>
                        <option value="">Select Item</option>
                        @foreach($products as $item)
                            <option value="{{ $item->id }}" 
                                    data-mfg-cost="{{ $item->manufacturing_cost }}"
                                    data-unit-id="{{ $item->unit_id }}"
                                    data-barcode="{{ $item->barcode }}">
                                {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="item_details[0][variation_id]" class="form-control select2-js variation-select">
                      <option value="">Select Variation</option>
                    </select>
                </td>
                <td><input type="number" class="form-control manufacturing_cost" name="item_details[0][manufacturing_cost]" step="any" value="0" readonly></td>
                <td><input type="number" class="form-control received-qty" name="item_details[0][received_qty]" step="any" value="0" required></td>
                <td><input type="text" class="form-control" name="item_details[0][remarks]"></td>
                <td><input type="number" class="form-control row-total" name="item_details[0][total]" step="any" value="0" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
              </tr>
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm mt-2" onclick="addRow()">+ Add Row</button>

          <hr>
          <div class="row">
            <div class="col-md-2">
              <label>Total Pcs</label>
              <input type="text" class="form-control" id="total_pcs" disabled>
            </div>
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" class="form-control" id="total_amt" disabled>
            </div>
            <div class="col-md-2">
              <label>Conveyance</label>
              <input type="text" class="form-control" name="convance_charges" id="convance_charges" onchange="calcNet()" value="0">
            </div>
            <div class="col-md-2">
              <label>Discount</label>
              <input type="text" class="form-control" name="bill_discount" id="bill_discount" onchange="calcNet()" value="0">
            </div>
            <div class="col-md-4 text-end">
              <label><strong>Net Amount</strong></label>
              <h4 class="text-primary">PKR <span id="netAmountText">0.00</span></h4>
            </div>
          </div>
        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('production.receiving.index') }}" class="btn btn-danger">Discard</a>
          <button type="submit" class="btn btn-primary">Receive</button>
        </footer>
      </section>
    </div>
  </form>
</div>

<script>
  let rowIndex = 1;

  $(document).ready(function () {

    // Initialize Select2 on existing elements
    $('.select2-js').select2({
        width: '100%',
        dropdownAutoWidth: true
    });
    
    // Bind events to first row
    bindRowEvents($('#itemTable tbody tr:first'));

    $('#itemTable').on('blur', '.product-code', function() {
      const rowIndex = $(this).closest('tr').index();
      fetchByCode(rowIndex);
    });

    // Fetch variations when product is selected
    $(document).on('change', '.product-select', function() {
        const row = $(this).closest('tr');
        const selectedOption = $(this).find('option:selected');
        const mfgCostInput = row.find('.manufacturing_cost');

        const mfgCost = selectedOption.data('mfg-cost') || 0;
        mfgCostInput.val(mfgCost).trigger('change');

        if ($(this).val()) {
            const preselect = $(this).data('preselectVariationId') || null;
            $(this).removeData('preselectVariationId'); // clear after use
            loadVariations(row, $(this).val(), preselect);
        } else {
            row.find('.variation-select').html('<option value="">Select Variation</option>');
        }
    });

    // Set manufacturing cost when variation is selected
    $(document).on('change', '.variation-select', function () {
      const row = $(this).closest('tr');
      calculateTotals();
    });

    // Recalculate totals on qty change
    $(document).on('input', '.received-qty', function () {
      calculateTotals();
    });
  });

  function fetchByCode(rowIndex) {
      const row = $('#itemTable tbody tr').eq(rowIndex);
      const codeInput = row.find('.product-code');
      const productSelect = row.find('.product-select');
      const variationSelect = row.find('.variation-select');
      const mfgCostInput = row.find('.manufacturing_cost');
      const enteredCode = codeInput.val().trim();

      if (!enteredCode) {
          return;
      }

      $.ajax({
        url: '/get-variation-by-code/' + encodeURIComponent(enteredCode),
        method: 'GET',
        success: function(res) {
          if (res.success) {
              console.log("Found variation:", res.variation);

              // Save preselect ID BEFORE triggering change
              productSelect.data('preselectVariationId', res.variation.id);

              // Trigger product change (this will call loadVariations with preselect)
              productSelect.val(res.variation.product_id).trigger('change');
          } else {
              alert(res.message || 'No variation found with code: ' + enteredCode);
              codeInput.val('').focus();
          }
        }
      });
  }

  function loadVariations(row, productId, preselectVariationId = null) {
      const variationSelect = row.find('.variation-select');

      variationSelect.html('<option value="">Loading...</option>');

      $.get(`/product/${productId}/variations`, function(data) {
          let options = '<option value="">Select Variation</option>';
          data.forEach(variation => {
              options += `<option value="${variation.id}">${variation.sku}</option>`;
          });
          variationSelect.html(options);

          // Reinitialize Select2
          if (variationSelect.hasClass('select2-hidden-accessible')) {
              variationSelect.select2('destroy');
          }
          variationSelect.select2({
              width: '100%',
              dropdownAutoWidth: true
          });

          // Step 3: auto-select the variation if provided
          if (preselectVariationId) {
              variationSelect.val(preselectVariationId).trigger('change');
          }
      });
  }


  function addRow() {
      const table = $('#itemTable tbody');
      const newIndex = table.find('tr').length;
      
      // Create fresh row HTML (don't clone)
      const newRow = $(`
      <tr>
          <td><input type="text" class="form-control product-code" placeholder="Enter Product Code"></td>
          <td>
              <select name="item_details[${newIndex}][product_id]" 
                      class="form-control select2-js product-select" required>
                  <option value="">Select Item</option>
                  @foreach($products as $item)
                      <option value="{{ $item->id }}" 
                              data-mfg-cost="{{ $item->manufacturing_cost }}"
                              data-unit-id="{{ $item->unit_id }}"
                              data-barcode="{{ $item->barcode }}">
                          {{ $item->name }}
                      </option>
                  @endforeach
              </select>
          </td>
          <td>
            <select name="item_details[${newIndex}][variation_id]" class="form-control select2-js variation-select">
              <option value="">Select Variation</option>
            </select>
          </td>
          <td><input type="number" class="form-control manufacturing_cost" name="item_details[${newIndex}][manufacturing_cost]" step="0.0001" value="0" readonly></td>
          <td><input type="number" class="form-control received-qty" name="item_details[${newIndex}][received_qty]" step="any" value="0" required></td>
          <td><input type="text" class="form-control" name="item_details[${newIndex}][remarks]"></td>
          <td><input type="number" class="form-control row-total" name="item_details[${newIndex}][total]" step="any" value="0" readonly>
          </td>
          <td><button type="button" class="btn btn-danger btn-sm remove-row-btn"><i class="fas fa-times"></i></button></td>
      </tr>`);

      // Add to table
      table.append(newRow);
      
      // Initialize Select2 on new selects
      newRow.find('.select2-js').select2({
          width: '100%',
          dropdownAutoWidth: true
      });
      
      // Focus on product code field
      newRow.find('.product-code').focus();
      
      // Bind events to new row
      bindRowEvents(newRow);
  }

  // Bind events to new row
  function bindRowEvents(row) {
      console.log("🔗 Binding events for row:", row.index());

      // Quantity input event
      row.find('.received-qty').on('input', calculateTotals);

      // Remove row button
      row.find('.remove-row-btn').on('click', function () {
          if ($('#itemTable tbody tr').length > 1) {
              $(this).closest('tr').remove();
              calculateTotals();
          }
      });
  }

  function removeRow(button) {
    const table = document.querySelector("#itemTable tbody");
    if (table.rows.length > 1) {
      button.closest("tr").remove();
      calculateTotals();
    }
  }

  function calculateTotals() {
    let totalQty = 0, totalAmt = 0;

    $('#itemTable tbody tr').each(function() {
      const qty = parseFloat($(this).find('.received-qty').val()) || 0;
      const cost = parseFloat($(this).find('.manufacturing_cost').val()) || 0;
      const rowTotal = qty * cost;
      $(this).find('.row-total').val(rowTotal.toFixed(2));

      totalQty += qty;
      totalAmt += rowTotal;
    });

    $('#total_pcs').val(totalQty);
    $('#total_pcs_val').val(totalQty);
    $('#total_amt').val(totalAmt.toFixed(2));
    calcNet();
  }

  function calcNet() {
    const total = parseFloat($('#total_amt').val()) || 0;
    const conveyance = parseFloat($('#convance_charges').val()) || 0;
    const discount = parseFloat($('#bill_discount').val()) || 0;
    const net = total + conveyance - discount;
    $('#netAmountText').text(net.toFixed(2));
  }
  
</script>

@endsection