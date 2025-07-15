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
              <input type="text" name="grn_no" class="form-control" value="{{ $nextGrnNo ?? '' }}" readonly required />
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
                <td><input type="text" class="form-control" name="items[0][item_code]" required></td>
                <td>
                  <select name="items[0][product_id]" class="form-control select2-js product-select" data-row="0" required>
                    <option value="">Select Item</option>
                    @foreach($products as $item)
                      <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[0][variation_id]" class="form-control select2-js variation-select" data-row="0" required>
                    <option value="">Select Variation</option>
                  </select>
                </td>
                <td><input type="number" class="form-control m-cost" name="items[0][mcost]" step="0.0001" value="0" readonly></td>
                <td><input type="number" class="form-control received-qty" name="items[0][received_qty]" step="0.01" value="0" required></td>
                <td><input type="text" class="form-control" name="items[0][remarks]"></td>
                <td><input type="number" class="form-control row-total" name="items[0][total]" step="0.01" value="0" readonly></td>
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
              <input type="text" class="form-control" name="conveyance" id="conveyance" onchange="calcNet()">
            </div>
            <div class="col-md-2">
              <label>Discount</label>
              <input type="text" class="form-control" name="discount" id="discount" onchange="calcNet()">
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

  function addRow() {
    const table = document.querySelector("#itemTable tbody");
    const newRow = table.rows[0].cloneNode(true);
    const inputs = newRow.querySelectorAll("input, select");

    inputs.forEach(input => {
      const name = input.name;
      const newName = name.replace(/\[\d+\]/, `[${rowIndex}]`);
      input.name = newName;
      input.setAttribute('data-row', rowIndex);

      if (input.tagName === 'SELECT') {
        input.innerHTML = '<option value="">Select</option>';
        input.selectedIndex = 0;
      } else {
        input.value = (input.classList.contains('received-qty') || input.classList.contains('m-cost')) ? '0' : '';
      }

      if (input.classList.contains('variation-select')) {
        input.innerHTML = '<option value="">Select Variation</option>';
      }
    });

    table.appendChild(newRow);

    // Re-initialize select2
    $('.select2-js').select2({
      width: '100%',
      dropdownAutoWidth: true
    });

    rowIndex++;
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
      const cost = parseFloat($(this).find('.m-cost').val()) || 0;
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
    const conveyance = parseFloat($('#conveyance').val()) || 0;
    const discount = parseFloat($('#discount').val()) || 0;
    const net = total + conveyance - discount;
    $('#netAmountText').text(net.toFixed(2));
    $('#net_amount').val(net.toFixed(2));
  }

  // Initialize Select2 on load
  $(document).ready(function () {
    $('.select2-js').select2({
      width: '100%',
      dropdownAutoWidth: true
    });
  });

  // Fetch variations when product is selected
  $(document).on('change', '.product-select', function () {
    const row = $(this).data('row');
    const productId = $(this).val();
    const rowElement = $(this).closest('tr');
    const variationSelect = rowElement.find('.variation-select');
    const mcostInput = rowElement.find('.m-cost');

    variationSelect.html('<option value="">Loading...</option>');
    mcostInput.val(0);

    $.get("{{ url('/api/product') }}/" + productId + "/variations", function (data) {
      variationSelect.empty().append(`<option value="">Select Variation</option>`);
      data.forEach(variation => {
        variationSelect.append(`<option value="${variation.id}" data-cost="${variation.manufacturing_cost}">${variation.name}</option>`);
      });
    });
  });

  // Set manufacturing cost when variation is selected
  $(document).on('change', '.variation-select', function () {
    const selected = $(this).find(':selected');
    const cost = selected.data('cost') || 0;
    $(this).closest('tr').find('.m-cost').val(cost);
    calculateTotals();
  });

  // Recalculate totals on qty change
  $(document).on('input', '.received-qty', function () {
    calculateTotals();
  });
</script>

@endsection
