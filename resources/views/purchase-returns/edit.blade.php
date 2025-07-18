@extends('layouts.app')

@section('title', 'Purchase Return | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_returns.update', $return->id) }}" method="POST">
      @csrf
      @method('PUT')

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Return</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Purchase Invoice</label>
              <select name="purchase_invoice_id" class="form-control select2-js" required>
                <option value="">Select Invoice</option>
                @foreach ($invoices as $inv)
                  <option value="{{ $inv->id }}" {{ $return->purchase_invoice_id == $inv->id ? 'selected' : '' }}>
                    {{ $inv->id }} - {{ $inv->vendor->name ?? '' }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $return->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ $return->return_date }}" required>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemTable">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Remarks</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($return->items as $i => $item)
                  <tr>
                    <td>
                      <select name="item_id[]" class="form-control select2-js" onchange="onItemChange(this)">
                        @foreach ($products as $product)
                          <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-unit="{{ $product->measurement_unit }}"
                            {{ $product->id == $item->item_id ? 'selected' : '' }}>
                            {{ $product->name }}
                          </option>
                        @endforeach
                      </select>
                      <input type="hidden" name="item_name[]" value="{{ $item->item_name }}">
                    </td>
                    <td><input type="number" name="quantity[]" class="form-control" value="{{ $item->quantity }}" step="any" oninput="recalc(this)"></td>
                    <td>
                      <select name="unit[]" class="form-control">
                        @foreach ($units as $unit)
                          <option value="{{ $unit->id }}" {{ $unit->id == $item->unit ? 'selected' : '' }}>{{ $unit->name }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td><input type="number" name="price[]" class="form-control" value="{{ $item->price }}" step="any" oninput="recalc(this)"></td>
                    <td><input type="number" name="amount[]" class="form-control" value="{{ $item->amount }}" readonly></td>
                    <td><input type="text" name="remarks[]" class="form-control" value="{{ $item->remarks }}"></td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove(); updateTotal()">
                        <i class="fas fa-trash"></i>
                      </button>
                      <button type="button" class="btn btn-primary btn-sm mt-1" onclick="addItemRow()">
                        <i class="fas fa-plus"></i>
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control">{{ $return->remarks }}</textarea>
            </div>
            <div class="col-md-3">
              <label>Total Amount</label>
              <input type="number" name="total_amount" id="total_amount" class="form-control" step="any" value="{{ $return->total_amount }}" readonly>
            </div>
            <div class="col-md-3">
              <label>Net Amount</label>
              <input type="number" name="net_amount" id="net_amount" class="form-control" step="any" value="{{ $return->net_amount }}">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Update Return
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  function addItemRow() {
    const row = `
      <tr>
        <td>
          <select name="item_id[]" class="form-control select2-js" onchange="onItemChange(this)">
            @foreach ($products as $product)
              <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-unit="{{ $product->measurement_unit }}">{{ $product->name }}</option>
            @endforeach
          </select>
          <input type="hidden" name="item_name[]">
        </td>
        <td><input type="number" name="quantity[]" class="form-control" step="any" oninput="recalc(this)"></td>
        <td>
          <select name="unit[]" class="form-control">
            @foreach ($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
          </select>
        </td>
        <td><input type="number" name="price[]" class="form-control" step="any" oninput="recalc(this)"></td>
        <td><input type="number" name="amount[]" class="form-control" step="any" readonly></td>
        <td><input type="text" name="remarks[]" class="form-control"></td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove(); updateTotal()">
            <i class="fas fa-trash"></i>
          </button>
          <button type="button" class="btn btn-primary btn-sm mt-1" onclick="addItemRow()">
            <i class="fas fa-plus"></i>
          </button>
        </td>
      </tr>`;
    $('#itemTable tbody').append(row);
    $('.select2-js').select2();
  }

  function onItemChange(select) {
    const row = select.closest('tr');
    const unitId = select.selectedOptions[0].getAttribute('data-unit');
    const name = select.selectedOptions[0].getAttribute('data-name');
    if (unitId) row.querySelector('select[name="unit[]"]').value = unitId;
    if (name) row.querySelector('input[name="item_name[]"]').value = name;
  }

  function recalc(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
    const price = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;
    const amt = qty * price;
    row.querySelector('input[name="amount[]"]').value = amt.toFixed(2);
    updateTotal();
  }

  function updateTotal() {
    let total = 0;
    document.querySelectorAll('input[name="amount[]"]').forEach(input => {
      total += parseFloat(input.value) || 0;
    });
    document.getElementById('total_amount').value = total.toFixed(2);
    document.getElementById('net_amount').value = total.toFixed(2);
  }

  $(document).ready(function () {
    $('.select2-js').select2();
    updateTotal();
  });
</script>
@endsection
