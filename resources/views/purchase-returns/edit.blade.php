@extends('layouts.app')

@section('title', 'Edit Purchase Return')

@section('content')
<div class="row">
  <div class="col-12">
    <div class="card shadow rounded-3 p-3">
      <form action="{{ route('purchase_return.update', $return->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <section class="card">
          <header class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title">Edit Purchase Return</h2>          
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
            <div class="row mb-3">
              <div class="col-md-4">
                <label>Vendor</label>
                <select name="vendor_id" class="form-control" required>
                  <option value="">Select Vendor</option>
                  @foreach($vendors as $vendor)
                    <option value="{{ $vendor->id }}" {{ $return->vendor_id == $vendor->id ? 'selected' : '' }}>
                      {{ $vendor->name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label>Return Date</label>
                <input type="date" name="return_date" class="form-control" value="{{ $return->return_date }}" required>
              </div>
              <div class="col-md-4">
                <label>Reference No</label>
                <input type="text" name="reference_no" class="form-control" value="{{ $return->reference_no }}">
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-sm" id="items-table">
                <thead class="table-light">
                  <tr>
                    <th>Item</th>
                    <th>Invoice</th>
                    <th>Name</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Amount</th>
                    <th>Remarks</th>
                    <th><button type="button" class="btn btn-sm btn-success" onclick="addRow()">+</button></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($return->items as $i => $item)
                  <tr>
                    <td>
                      <select name="item_id[]" class="form-control form-control-sm" onchange="onItemChange(this)" required>
                        <option value="">Select Item</option>
                        @foreach($items as $product)
                          <option value="{{ $product->id }}"
                            data-name="{{ $product->name }}"
                            data-unit="{{ $product->unit_id }}"
                            {{ $item->item_id == $product->id ? 'selected' : '' }}>
                            {{ $product->code }} - {{ $product->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <select name="invoice_id[]" class="form-control form-control-sm invoice-dropdown" required>
                        @foreach($invoices as $inv)
                          <option value="{{ $inv->id }}" {{ $item->purchase_invoice_id == $inv->id ? 'selected' : '' }}>
                            #{{ $inv->id }} - {{ optional($inv->vendor)->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="{{ $item->item->name }}" readonly></td>
                    <td><input type="number" name="quantity[]" class="form-control form-control-sm qty" value="{{ $item->quantity }}" step="any" required></td>
                    <td>
                      <select name="unit_id[]" class="form-control form-control-sm" required>
                        @foreach($units as $unit)
                          <option value="{{ $unit->id }}" {{ $item->unit_id == $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td><input type="number" name="price[]" class="form-control form-control-sm price" value="{{ $item->price }}" step="any" required></td>
                    <td><input type="number" name="amount[]" class="form-control form-control-sm amount" value="{{ $item->amount }}" readonly></td>
                    <td><input type="text" name="remarks[]" class="form-control form-control-sm" value="{{ $item->remarks }}"></td>
                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button></td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div class="row">
              <div class="col-md-4 offset-md-8">
                <label>Total Amount</label>
                <input type="number" name="total_amount" id="totalAmount" class="form-control" value="{{ $return->total_amount }}" readonly>
              </div>
              <div class="col-md-4 offset-md-8 mt-2">
                <label>Net Amount</label>
                <input type="number" name="net_amount" id="netAmount" class="form-control" value="{{ $return->net_amount }}" readonly>
              </div>
            </div>

            <div class="mt-4 text-end">
              <button type="submit" class="btn btn-primary px-4">Update</button>
              <a href="{{ route('purchase_return.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
          </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
function onItemChange(select) {
  const row = select.closest('tr');
  const option = select.selectedOptions[0];
  const name = option.getAttribute('data-name');
  const unit = option.getAttribute('data-unit');

  if (name) row.querySelector('input[name="item_name[]"]').value = name;
  if (unit) row.querySelector('select[name="unit_id[]"]').value = unit;

  const itemId = select.value;
  if (itemId) {
    fetch(`/api/invoices-for-item/${itemId}`)
      .then(res => res.json())
      .then(data => {
        const invoiceSelect = row.querySelector('select[name="invoice_id[]"]');
        invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
        data.forEach(inv => {
          invoiceSelect.innerHTML += `<option value="${inv.id}">#${inv.id} - ${inv.vendor}</option>`;
        });
      });
  }
}

function addRow() {
  const table = document.querySelector('#items-table tbody');
  const row = table.rows[0].cloneNode(true);

  row.querySelectorAll('input, select').forEach(el => {
    el.value = '';
  });

  table.appendChild(row);
}

function removeRow(button) {
  const table = document.querySelector('#items-table tbody');
  if (table.rows.length > 1) button.closest('tr').remove();
}

document.querySelectorAll('.qty, .price').forEach(input => {
  input.addEventListener('input', calculateAmounts);
});

function calculateAmounts() {
  let total = 0;
  document.querySelectorAll('#items-table tbody tr').forEach(row => {
    const qty = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
    const price = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;
    const amount = qty * price;
    row.querySelector('input[name="amount[]"]').value = amount.toFixed(2);
    total += amount;
  });
  document.getElementById('totalAmount').value = total.toFixed(2);
  document.getElementById('netAmount').value = total.toFixed(2);
}

document.querySelector('#items-table').addEventListener('input', function (e) {
  if (e.target.matches('.qty') || e.target.matches('.price')) calculateAmounts();
});
</script>
@endsection
