@extends('layouts.app')

@section('title', 'Purchase Return | Edit')

@section('content')
<div class="row">
  <div class="col-12">
    <div class="card shadow rounded-3 p-3">
      <form action="{{ route('purchase_return.update', $purchaseReturn->id) }}" method="POST">
        @csrf
        @method('PUT')

        <section class="card">
          <header class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title">Edit Purchase Return</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-md-3 mb-3">
                <label>Vendor</label>
                <select name="vendor_id" class="form-control select2-js" required>
                  <option value="">Select Vendor</option>
                  @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" {{ $purchaseReturn->vendor_id == $vendor->id ? 'selected' : '' }}>
                      {{ $vendor->name }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-2 mb-3">
                <label>Return Date</label>
                <input type="date" name="return_date" class="form-control" value="{{ $purchaseReturn->return_date }}" required>
              </div>
            </div>

            <div class="table-responsive mb-3">
              <table class="table table-bordered" id="itemTable">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Purchase Invoice</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Amount</th>
                    <th>Remarks</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($purchaseReturn->items as $item)
                    <tr>
                      <td>
                        <select name="item_id[]" class="form-control select2-js" onchange="onItemChange(this)">
                          <option value="">Select Item</option>
                          @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                              data-name="{{ $product->name }}"
                              data-unit="{{ $product->measurement_unit }}"
                              {{ $item->item_id == $product->id ? 'selected' : '' }}>
                              {{ $product->name }}
                            </option>
                          @endforeach
                        </select>
                      </td>
                      <td>
                        <select name="invoice_id[]" class="form-control" required onchange="onInvoiceChange(this)">
                          <option value="">Select Invoice</option>
                          @foreach ($invoices as $inv)
                            <option value="{{ $inv->id }}" {{ $item->purchase_invoice_id == $inv->id ? 'selected' : '' }}>
                              #{{ $inv->id }} - {{ $inv->vendor->name ?? '' }}
                            </option>
                          @endforeach
                        </select>
                      </td>
                      <td><input type="number" name="quantity[]" class="form-control" value="{{ $item->quantity }}" step="any" oninput="recalc(this)"></td>
                      <td>
                        <select name="unit[]" class="form-control">
                          @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" {{ $item->unit_id == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                          @endforeach
                        </select>
                      </td>
                      <td><input type="number" name="price[]" class="form-control" value="{{ $item->price }}" step="any" oninput="recalc(this)"></td>
                      <td><input type="number" name="amount[]" class="form-control" value="{{ $item->amount }}" step="any" readonly></td>
                      <td><input type="text" name="remarks[]" class="form-control" value="{{ $item->remarks }}"></td>
                      <td>
                        <button type="button" class="btn btn-danger btn-sm" onclick="$(this).closest('tr').remove(); updateTotal()">
                          <i class="fas fa-times"></i>
                        </button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              <button type="button" class="btn btn-outline-primary" onclick="addItemRow()"><i class="fas fa-plus"></i> Add Item</button>
            </div>

            <div class="row mt-3">
              <div class="col-md-6">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control">{{ $purchaseReturn->remarks }}</textarea>
              </div>
              <div class="col-md-3">
                <label>Total Amount</label>
                <input type="number" name="total_amount" id="total_amount" class="form-control" step="any" readonly>
              </div>
              <div class="col-md-3">
                <label>Net Amount</label>
                <input type="number" name="net_amount" id="net_amount" class="form-control" value="{{ $purchaseReturn->net_amount }}" step="any">
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update</button>
          </footer>
        </section>
      </form>
    </div>
  </div>
</div>

<script>
  function addItemRow() {
    const row = `
      <tr>
        <td>
          <select name="item_id[]" class="form-control select2-js" onchange="onItemChange(this)">
            <option value="">Select Item</option>
            @foreach ($products as $product)
              <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-unit="{{ $product->measurement_unit }}">
                {{ $product->name }}
              </option>
            @endforeach
          </select>
          <input type="hidden" name="item_name[]">
        </td>
        <td>
          <select name="invoice_id[]" class="form-control" required onchange="onInvoiceChange(this)">
            <option value="">Select Invoice</option>
          </select>
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
            <i class="fas fa-times"></i>
          </button>
        </td>
      </tr>
    `;
    $('#itemTable tbody').append(row);
    initializeSelect2();
  }

  function initializeSelect2() {
    $('.select2-js').each(function () {
      if (!$(this).hasClass("select2-hidden-accessible")) {
        $(this).select2();
      }
    });
  }

  function onItemChange(select) {
    const row = select.closest('tr');
    const itemId = select.value;
    const option = select.selectedOptions?.[0];

    if (!row || !itemId || !option) return;

    const name = option.getAttribute('data-name');
    const unit = option.getAttribute('data-unit');

    row.querySelector('input[name="item_name[]"]').value = name || '';

    const unitSelect = row.querySelector('select[name="unit[]"]');
    if (unitSelect && unit) unitSelect.value = unit;

    const invoiceSelect = row.querySelector('select[name="invoice_id[]"]');
    invoiceSelect.innerHTML = '<option value="">Loading...</option>';
    row.querySelector('input[name="quantity[]"]').value = '';
    row.querySelector('input[name="price[]"]').value = '';
    row.querySelector('input[name="amount[]"]').value = '';

    fetch(`/api/item/${itemId}/invoices`)
      .then(res => res.json())
      .then(data => {
        invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
        data.forEach(inv => {
          invoiceSelect.innerHTML += `<option value="${inv.id}">#${inv.id} - ${inv.vendor}</option>`;
        });
      })
      .catch(() => {
        invoiceSelect.innerHTML = '<option value="">Error loading invoices</option>';
      });
  }

  function onInvoiceChange(select) {
    const row = select.closest('tr');
    const invoiceId = select.value;
    const itemSelect = row.querySelector('select[name="item_id[]"]');
    const itemId = itemSelect?.value;

    if (!invoiceId || !itemId) return;

    fetch(`/invoice-item/${invoiceId}/item/${itemId}`)
      .then(res => res.json())
      .then(data => {
        if (!data.error) {
          row.querySelector('input[name="quantity[]"]').value = data.quantity || 0;
          row.querySelector('input[name="price[]"]').value = data.price || 0;
          recalc(row.querySelector('input[name="quantity[]"]'));
        }
      })
      .catch(() => {
        console.warn("Failed to fetch invoice-item data.");
      });
  }

  function recalc(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('input[name="quantity[]"]')?.value) || 0;
    const price = parseFloat(row.querySelector('input[name="price[]"]')?.value) || 0;
    const amt = (qty * price).toFixed(2);
    row.querySelector('input[name="amount[]"]').value = amt;
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
    initializeSelect2();
    updateTotal();
  });
</script>

@endsection
