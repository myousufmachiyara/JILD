@extends('layouts.app')

@section('title', 'Purchases | New Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Purchase Invoice</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <input type="hidden" id="itemCount" name="items" value="1">

            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Payment Terms</label>
              <input type="text" name="payment_terms" class="form-control">
            </div>

            <div class="col-md-1 mb-3">
              <label>Bill #</label>
              <input type="text" name="bill_no" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref #</label>
              <input type="text" name="ref_no" class="form-control">
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3"></textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="purchaseTable">
              <thead>
                <tr>
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Bundle</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="Purchase1Table">
                <tr>
                  <td><input type="text" name="item_cod[]" id="item_cod1" class="form-control" onblur="fetchByCode(1)"></td>
                  <td>
                    <select name="item_name[]" id="item_name1" class="form-control select2-js" onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}" data-unit-id="{{ $product->measurement_unit }}">{{ $product->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="text" name="bundle[]" id="pur_qty2_1" class="form-control" value="0" onchange="rowTotal(1)"></td>
                  <td><input type="number" name="quantity[]" id="pur_qty1" class="form-control" value="0" step="any" onchange="rowTotal(1)"></td>
                  <td>
                    <select name="unit[]" id="unit1" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="price[]" id="pur_price1" class="form-control" value="0" step="any" onchange="rowTotal(1)"></td>
                  <td><input type="number" id="amount1" class="form-control" value="0" step="any" disabled></td>
                  <td>
                    <button type="button" class="btn btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button>
                    <button type="button" class="btn btn-primary mt-1" onclick="addNewRow_btn()"><i class="fas fa-plus"></i></button>
                    <input type="hidden" name="barcode[]" id="barcode1">
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>
            <div class="col-md-2">
              <label>Total Bundle</label>
              <input type="text" id="total_weight" class="form-control" disabled>
              <input type="hidden" name="total_weight" id="total_weight_show">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>
            <div class="col-md-2">
              <label>Convance Charges</label>
              <input type="number" name="convance_charges" id="convance_charges" class="form-control" value="0" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Labour Charges</label>
              <input type="number" name="labour_charges" id="labour_charges" class="form-control" value="0" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Bill Discount</label>
              <input type="number" name="bill_discount" id="bill_discount" class="form-control" value="0" onchange="netTotal()">
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"> <i class="fas fa-save"></i> Save Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var index = 2;

  $(document).ready(function () {
    $('.select2-js').select2();
    $(window).keydown(function (event) {
      if (event.keyCode == 13) {
        event.preventDefault();
        return false;
      }
    });
  });

  function fetchByCode(index) {
    const codeInput = document.getElementById(`item_cod${index}`);
    const itemSelect = document.getElementById(`item_name${index}`);
    const unitSelect = document.getElementById(`unit${index}`);
    const barcodeInput = document.getElementById(`barcode${index}`);
    const enteredCode = codeInput.value.trim();

    if (!enteredCode) return;

    // Find option by product ID (enteredCode)
    let matched = false;
    for (let option of itemSelect.options) {
      if (option.value === enteredCode) {
        option.selected = true;
        const unitId = option.getAttribute('data-unit-id');
        const barcode = option.getAttribute('data-barcode');
        unitSelect.value = unitId;
        barcodeInput.value = barcode;
        $(itemSelect).trigger('change');
        $(unitSelect).trigger('change');

        matched = true;
        break;
      }
    }

    if (!matched) {
      alert('No matching product found for this Item Code.');
    }
  }

  function onItemNameChange(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];

    const itemId = selectedOption.value;
    const unitId = selectedOption.getAttribute('data-unit-id');

    const barcode = selectedOption.getAttribute('data-barcode');

    // Extract index from the select element ID
    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;

    const index = idMatch[0];

    // Update item code and barcode
    document.getElementById(`item_cod${index}`).value = itemId;
    document.getElementById(`barcode${index}`).value = barcode;

    // ⚠️ Convert unitId to string before setting it to dropdown
    const unitSelector = $(`#unit${index}`);
    unitSelector.val(String(unitId)).trigger('change.select2');
  }


  function removeRow(button) {
    let rows = $('#Purchase1Table tr').length;
    if (rows > 1) {
      $(button).closest('tr').remove();
      $('#itemCount').val(--rows);
      tableTotal();
    }
  }

  function addNewRow_btn() {
    addNewRow();
    $('#item_cod' + (index - 1)).focus();
  }

  function addNewRow() {
    let table = $('#Purchase1Table');
    let newRow = `
      <tr>
        <td><input type="text" name="item_cod[]" id="item_cod${index}" class="form-control" onblur="fetchByCode(${index})"></td>
        <td>
          <select name="item_name[]" id="item_name${index}" class="form-control select2-js" onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(product => 
              `<option value="${product.id}" data-barcode="${product.barcode}" data-unit-id="${product.measurement_unit}">
                ${product.name}
              </option>`).join('')}
          </select>
        </td>
        <td><input type="text" name="bundle[]" id="pur_qty2_${index}" class="form-control" value="0" onchange="rowTotal(${index})"></td>
        <td><input type="number" name="quantity[]" id="pur_qty${index}" class="form-control" value="0" step="any" onchange="rowTotal(${index})"></td>
        <td>
          <select name="unit[]" id="unit${index}" class="form-control" required>
            <option value="">-- Select --</option>
            @foreach ($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
            @endforeach
          </select>
        </td>
        <td><input type="number" name="price[]" id="pur_price${index}" class="form-control" value="0" step="any" onchange="rowTotal(${index})"></td>
        <td><input type="number" id="amount${index}" class="form-control" value="0" step="any" disabled></td>
        <td>
          <button type="button" class="btn btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button>
          <button type="button" class="btn btn-primary mt-1" onclick="addNewRow_btn()"><i class="fas fa-plus"></i></button>
          <input type="hidden" name="barcode[]" id="barcode${index}">
        </td>
      </tr>
    `;
    table.append(newRow);
    $('#itemCount').val(index);
    $(`#item_name${index}`).select2();
    $(`#unit${index}`).select2();
    index++;
  }

  function rowTotal(row) {
    let quantity = parseFloat($('#pur_qty' + row).val()) || 0;
    let price = parseFloat($('#pur_price' + row).val()) || 0;
    $('#amount' + row).val((quantity * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0, bundle = 0, qty = 0;
    $('#Purchase1Table tr').each(function () {
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
      bundle += parseFloat($(this).find('input[name="bundle[]"]').val()) || 0;
      qty += parseFloat($(this).find('input[name="quantity[]"]').val()) || 0;
    });
    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_weight').val(bundle.toFixed(2));
    $('#total_weight_show').val(bundle.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));
    netTotal();
  }

  function netTotal() {
    let total = parseFloat($('#totalAmount').val()) || 0;
    let conv = parseFloat($('#convance_charges').val()) || 0;
    let labour = parseFloat($('#labour_charges').val()) || 0;
    let discount = parseFloat($('#bill_discount').val()) || 0;
    let net = (total + conv + labour - discount).toFixed(2);
    $('#netTotal').text(formatNumberWithCommas(net));
    $('#net_amount').val(net);
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }
</script>
@endsection
