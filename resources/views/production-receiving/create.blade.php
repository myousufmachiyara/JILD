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
            <div class="col-md-3">
              <label>Production Order</label>
              <select name="production_id" class="form-control select2-js" required>
                <option value="" disabled selected>Select Production</option>
                @foreach($productions as $prod)
                  <option value="{{ $prod->id }}">{{ $prod->id }} - {{ $prod->vendor->name ?? '-' }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="" disabled selected>Select Vendor</option>
                @foreach($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
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
                <th>Item Code</th>
                <th>Item</th>
                <th>Variation</th>
                <th>M. Cost</th>
                <th>Received</th>
                <th>Remarks</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                    <input type="text" class="form-control product-code" 
                            placeholder="Enter Product Code"
                            onblur="fetchByCode($(this).closest('tr').index())">
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
                    <select name="item_details[0][variation]" class="form-control select2-js variation-select">
                        <option value="">Select Variation</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control manufacturing_cost" 
                            name="item_details[0][manufacturing_cost]" 
                            step="any" value="0" readonly>
                </td>
                <td><input type="number" class="form-control received-qty" name="item_details[0][received_qty]" step="0.01" value="0" required></td>
                <td><input type="text" class="form-control" name="item_details[0][remarks]"></td>
                <td><input type="number" class="form-control row-total" name="item_details[0][total]" step="0.01" value="0" readonly></td>
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
              <input type="hidden" name="total_pcs" id="total_pcs_val">
            </div>
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" class="form-control" id="total_amt" disabled>
              <input type="hidden" name="total_amt" id="total_amt_val">
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
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>
        <footer class="card-footer text-end mt-3">
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
    $('.select2-js').select2({
      width: '100%',
      dropdownAutoWidth: true
    });

    $(document).on('blur', '.product-code', function() {
      const rowIndex = $(this).closest('tr').index();
      fetchByCode(rowIndex);
    });

    // Fetch variations when product is selected
    $(document).on('change', '.product-select', function() {
        const row = $(this).closest('tr');
        const selectedOption = $(this).find('option:selected');
        const mfgCostInput = row.find('.manufacturing_cost');
        
        // Set manufacturing cost immediately
        const mfgCost = selectedOption.data('mfg-cost') || 0;
        mfgCostInput.val(mfgCost).trigger('change');
        
        // Load variations
        if ($(this).val()) {
            loadVariations(row, $(this).val());
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
      const mfgCostInput = row.find('.manufacturing_cost');
      const enteredCode = codeInput.val().trim();

      if (!enteredCode) {
          codeInput.focus();
          return;
      }

      // Find matching product
      let matchedProduct = null;
      productSelect.find('option').each(function() {
          const option = $(this);
          if (option.val() === enteredCode || 
              option.text().includes(`(${enteredCode})`)) {
              matchedProduct = option;
              return false; // break loop
          }
      });

      if (matchedProduct) {
          // Select the product
          productSelect.val(matchedProduct.val()).trigger('change');
          
          // Set manufacturing cost
          const mfgCost = matchedProduct.data('mfg-cost') || 0;
          mfgCostInput.val(mfgCost).trigger('change');
          
          // Load variations (if needed)
          loadVariations(row, matchedProduct.val());
      } else {
          alert('No product found with code: ' + enteredCode);
          codeInput.val('').focus();
      }
  }

  function loadVariations(row, productId) {
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
      });
  }

  function addRow() {
      const table = $('#itemTable tbody');
      const newIndex = table.find('tr').length;
      
      // Create row from HTML template with all columns
      const newRow = $(`
      <tr>
          <td>
              <input type="text" class="form-control product-code" 
                    placeholder="Enter Product Code"
                    onblur="fetchByCode($(this).closest('tr').index())">
          </td>
          <td>
              <select name="item_details[${newIndex}][product_id]" 
                      class="form-control select2-js product-select" required>
                  <option value="">Select Item</option>
                  ${$('#itemTable').data('product-options')}
              </select>
          </td>
          <td>
              <select name="item_details[${newIndex}][variation]" 
                      class="form-control select2-js variation-select">
                  <option value="">Select Variation</option>
              </select>
          </td>
          <td>
              <input type="number" class="form-control manufacturing_cost" 
                    name="item_details[${newIndex}][manufacturing_cost]" 
                    step="any" value="0" readonly>
          </td>
          <td>
              <input type="number" class="form-control received-qty" 
                    name="item_details[${newIndex}][received_qty]" 
                    step="any" value="0" required>
          </td>
          <td>
              <input type="text" class="form-control" 
                    name="item_details[${newIndex}][remarks]">
          </td>
          <td>
              <input type="number" class="form-control row-total" 
                    name="item_details[${newIndex}][total]" 
                    step="any" value="0" readonly>
          </td>
          <td>
              <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                  <i class="fas fa-times"></i>
              </button>
          </td>
      </tr>`);
      
      // Add to table
      table.append(newRow);
      
      // Initialize Select2 on both select elements
      newRow.find('.select2-js').select2({
          width: '100%',
          dropdownAutoWidth: true
      });
          
      // Re-bind any necessary events
      bindRowEvents(newRow);
  }

  // Optional: Function to bind events to new row
  function bindRowEvents(row) {
      row.find('.received-qty').on('input', calculateTotals);
      row.find('.product-select').on('change', function() {
          const selected = $(this).find(':selected');
          const mfgCost = selected.data('mfg-cost') || 0;
          $(this).closest('tr').find('.manufacturing_cost').val(mfgCost);
          calculateTotals();
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
    $('#total_amt_val').val(totalAmt.toFixed(2));
    calcNet();
  }

  function calcNet() {
    const total = parseFloat($('#total_amt_val').val()) || 0;
    const conveyance = parseFloat($('#convance_charges').val()) || 0;
    const discount = parseFloat($('#bill_discount').val()) || 0;
    const net = total + conveyance - discount;
    $('#netAmountText').text(net.toFixed(2));
    $('#net_amount').val(net.toFixed(2));
  }

  // Initialize Select2 on load
  
</script>

@endsection