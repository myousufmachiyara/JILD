@extends('layouts.app')

@section('title', 'Create Sale Invoice')

@section('content')
<div class="row">
  <form action="{{ route('sale_invoices.store') }}" method="POST">
    @csrf

    <div class="col-12 mb-2">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Create Sale Invoice</h2>
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </header>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-2">
              <label>Invoice #</label>
              <input type="text" name="invoice_no" class="form-control" readonly/>
            </div>
            <div class="col-md-2">
              <label>Date</label>
              <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required />
            </div>
            <div class="col-md-3">
              <label>Customer Name</label>
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

    <div class="col-12">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Invoice Items</h2>
        </header>
        <div class="card-body">
          <table class="table table-bordered" id="itemTable">
            <thead>
              <tr>
                <th width="15%">Item Code</th>
                <th>Product</th>
                <th>Variation</th>
                <th width="12%">Price</th>
                <th width="10%">Discount(%)</th>
                <th width="12%">Qty</th>
                <th width="12%">Total</th>
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
                      <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">{{ $product->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[0][variation_id]" class="form-control select2-js variation-select" required>
                    <option value="">Select Variation</option>
                  </select>
                </td>
                <td><input type="number" name="items[0][sale_price]" class="form-control sale-price" step="any" required></td>
                <td><input type="number" name="items[0][disc_price]" class="form-control disc-price" step="any" value="0"></td>
                <td><input type="number" name="items[0][quantity]" class="form-control quantity" step="any" required></td>
                <td><input type="number" name="items[0][total]" class="form-control row-total" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
              </tr>
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm" onclick="addRow()">+ Add Item</button>

          <hr>
          <div class="row mb-2">
            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-2">
              <label><strong>Total Discount (PKR)</strong></label>
              <input type="number" name="discount" id="discountInput" class="form-control" step="any" value="0">
            </div>
            <div class="col-md-6 text-end">
              <label style="font-size:14px"><strong>Total Bill</strong></label>
              <h4 class="text-primary mt-0 mb-1">PKR <span id="netAmountText">0.00</span></h4>
              <input type="hidden" name="net_amount" id="netAmountInput">
            </div>
          </div>
        </div>
        <footer class="card-footer text-end">
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
    calcTotal();
  });

  function addRow() {
    const row = `
      <tr>
        <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
        <td>
          <select name="items[${rowIndex}][product_id]" class="form-control select2-js product-select" required>
            <option value="">Select Product</option>
            @foreach($products as $product)
              <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}" >{{ $product->name }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <select name="items[${rowIndex}][variation_id]" class="form-control select2-js variation-select" required>
            <option value="">Select Variation</option>
          </select>
        </td>
        <td><input type="number" name="items[${rowIndex}][sale_price]" class="form-control sale-price" step="any" required></td>
        <td><input type="number" name="items[${rowIndex}][disc_price]" class="form-control disc-price" step="any"></td>
        <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control quantity" step="any" required></td>
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
    calcTotal();
  }

  function bindRowEvents(row) {
    row.find('.select2-js').select2();

    // Product => Load variations + set price
    row.find('.product-select').on('change', function () {
      const productId = $(this).val();
      const variationSelect = row.find('.variation-select');
      variationSelect.empty().append('<option value="">Loading...</option>');

      // ✅ Auto-fill price
      const selectedOption = $(this).find(':selected');
      const productPrice = selectedOption.data('price') || 0;
      row.find('.sale-price').val(productPrice);

      if (productId) {
        $.get(`/product/${productId}/variations`, function (data) {
          variationSelect.empty().append('<option value="">Select Variation</option>');
          data.forEach(function (v) {
            variationSelect.append(`<option value="${v.id}">${v.sku}</option>`);
          });
        });
      }

      calcRowTotal(row);
    });

    // Price, Qty, Discount => Recalculate
    row.find('.sale-price, .quantity, .disc-price').on('input', function () {
      calcRowTotal(row);
    });
  }

  function calcRowTotal(row) {
    const price = parseFloat(row.find('.sale-price').val()) || 0;
    const qty = parseFloat(row.find('.quantity').val()) || 0;
    const discPercent = parseFloat(row.find('.disc-price').val()) || 0;

    // ✅ Apply discount %
    const discountedPrice = price - (price * discPercent / 100);
    const total = discountedPrice * qty;

    row.find('.row-total').val(total.toFixed(2));
    calcTotal();
  }

  function calcTotal() {
    let total = 0;
    $('.row-total').each(function () {
      total += parseFloat($(this).val()) || 0;
    });

    // Invoice level discount
    const invoiceDiscount = parseFloat($('#discountInput').val()) || 0;
    const netAmount = total - invoiceDiscount;

    $('#netAmountText').text(netAmount.toFixed(2));
    $('#netAmountInput').val(netAmount.toFixed(2));
  }

  // Recalculate when invoice discount changes
  $(document).on('input', '#discountInput', function () {
    calcTotal();
  });

</script>


@endsection
