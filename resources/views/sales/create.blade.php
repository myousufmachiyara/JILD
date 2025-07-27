@extends('layouts.app')

@section('title', 'Create Sale Invoice')

@section('content')
<div class="row">
  <form action="{{ route('sale_invoices.store') }}" method="POST">
    @csrf

    <div class="col-12 mb-4">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Create Sale Invoice</h2>
        </header>
        <div class="card-body">
          <div class="row mb-4">
            <div class="col-md-2">
              <label>Invoice #</label>
              <input type="text" name="invoice_no" class="form-control" readonly/>
            </div>
            <div class="col-md-2">
              <label>Date</label>
              <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required />
            </div>
            <div class="col-md-4">
              <label>Account (Customer)</label>
              <select name="account_id" class="form-control select2-js" required>
                <option value="">Select Customer</option>
                @foreach($accounts as $account)
                  <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Invoice Type</label>
              <select name="type" class="form-control" required>
                <option value="cash">POS (Cash)</option>
                <option value="credit">Credit (E-commerce)</option>
              </select>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="col-12 mb-4">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Invoice Items</h2>
        </header>
        <div class="card-body">
          <table class="table table-bordered" id="itemTable">
            <thead>
              <tr>
                <th>Item Code</th>
                <th>Product</th>
                <th>Variation</th>
                <th>Production #</th>
                <th>Cost</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
                <td>
                  <select name="items[0][product_id]" class="form-control select2-js product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}">
                        {{ $product->name }}
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[0][variation_id]" class="form-control select2-js variation-select" required>
                    <option value="">Select Variation</option>
                  </select>
                </td>
                <td>
                  <select name="items[0][production_id]" class="form-control select2-js production-select" required>
                    <option value="">Select Production</option>
                  </select>
                </td>
                <td><input type="number" name="items[0][cost_price]" class="form-control cost-price" readonly></td>
                <td><input type="number" name="items[0][sale_price]" class="form-control sale-price" step="0.01" required></td>
                <td><input type="number" name="items[0][quantity]" class="form-control quantity" step="1" required></td>
                <td><input type="number" name="items[0][total]" class="form-control row-total" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
              </tr>
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm mt-2" onclick="addRow()">+ Add Item</button>

          <hr>
          <div class="row">
            <div class="col-md-3">
              <label>Convance Charges</label>
              <input type="number" name="convance_charges" class="form-control" id="convance" value="0" onchange="calcNet()">
            </div>
            <div class="col-md-3">
              <label>Other Expenses</label>
              <input type="number" name="other_expenses" class="form-control" id="expenses" value="0" onchange="calcNet()">
            </div>
            <div class="col-md-6 text-end">
              <label><strong>Net Amount</strong></label>
              <h4 class="text-primary">PKR <span id="netAmountText">0.00</span></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>
        <footer class="card-footer text-end mt-3">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Invoice</button>
        </footer>
      </section>
    </div>
  </form>
</div>

<script>
  let rowIndex = 1;

  $(document).ready(function () {
    $('.select2-js').select2();

    // Bind initial row
    bindRowEvents($('#itemTable tbody tr').first());
    calcNet();
  });

  function addRow() {
    const row = `
      <tr>
        <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
        <td>
          <select name="items[${rowIndex}][product_id]" class="form-control select2-js product-select" required>
            <option value="">Select Product</option>
            @foreach($products as $product)
              <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}">{{ $product->name }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <select name="items[${rowIndex}][variation_id]" class="form-control select2-js variation-select" required>
            <option value="">Select Variation</option>
          </select>
        </td>
        <td>
          <select name="items[${rowIndex}][production_id]" class="form-control select2-js production-select" required>
            <option value="">Select Production</option>
          </select>
        </td>
        <td><input type="number" name="items[${rowIndex}][cost_price]" class="form-control cost-price" readonly></td>
        <td><input type="number" name="items[${rowIndex}][sale_price]" class="form-control sale-price" step="0.01" required></td>
        <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control quantity" step="1" required></td>
        <td><input type="number" name="items[${rowIndex}][total]" class="form-control row-total" readonly></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
      </tr>
    `;
    $('#itemTable tbody').append(row);
    const newRow = $('#itemTable tbody tr').last();
    bindRowEvents(newRow);
    rowIndex++;
  }

  function removeRow(btn) {
    $(btn).closest('tr').remove();
    calcNet();
  }

  function bindRowEvents(row) {
    row.find('.select2-js').select2();

    // Product => Load variations
    row.find('.product-select').on('change', function () {
      const productId = $(this).val();
      const variationSelect = row.find('.variation-select');
      variationSelect.empty().append('<option value="">Loading...</option>');

      if (productId) {
        $.get(`/product/${productId}/variations`, function (data) {
          variationSelect.empty().append('<option value="">Select Variation</option>');
          data.forEach(function (v) {
            variationSelect.append(`<option value="${v.id}">${v.sku}</option>`);
          });
        });
      }
    });

    // Production => Fetch cost
    row.find('.production-select').on('change', function () {
      const productionId = $(this).val();
      const productId = row.find('.product-select').val();
      const variationId = row.find('.variation-select').val();

      if (productionId && productId && variationId) {
        $.get(`/get-cost/${productionId}/${productId}/${variationId}`, function (res) {
          row.find('.cost-price').val(res.cost || 0);
          calcRowTotal(row);
        });
      }
    });

    // Quantity or Price => Calculate row total
    row.find('.sale-price, .quantity').on('input', function () {
      calcRowTotal(row);
    });
  }

  function calcRowTotal(row) {
    const price = parseFloat(row.find('.sale-price').val()) || 0;
    const qty = parseInt(row.find('.quantity').val()) || 0;
    const total = price * qty;
    row.find('.row-total').val(total.toFixed(2));
    calcNet();
  }

  function calcNet() {
    let total = 0;
    $('.row-total').each(function () {
      total += parseFloat($(this).val()) || 0;
    });

    const convance = parseFloat($('#convance').val()) || 0;
    const expenses = parseFloat($('#expenses').val()) || 0;
    const netAmount = total + convance + expenses;

    $('#netAmountText').text(netAmount.toFixed(2));
    $('#net_amount').val(netAmount.toFixed(2));
  }
</script>


@endsection
