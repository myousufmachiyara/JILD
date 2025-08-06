@extends('layouts.app')

@section('title', 'Production | Edit Order')

@section('content')
<div class="row">
  <form id="productionForm" action="{{ route('production.update', $production->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="col-12 col-md-12 mb-3">
      <section class="card">
        <header class="card-header">
          <div style="display: flex;justify-content: space-between;">
            <h2 class="card-title">Edit Production</h2>
          </div>
        </header>
        <div class="card-body">
          <div class="row">
            <div class="col-12 col-md-2 mb-3">
              <label>Production #</label>
              <input type="text" class="form-control" value="{{ $production->id ?? '' }}" disabled />
            </div>

            <div class="col-12 col-md-2 mb-3">
              <label>Category<span style="color: red;"><strong>*</strong></span></label>
              <select class="form-control" name="category_id" required>
                <option value="" disabled>Select Category</option>
                @foreach($categories as $item)
                  <option value="{{ $item->id }}" {{ $production->category_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-12 col-md-2 mb-3">
              <label>Vendor Name</label>
              <select class="form-control select2-js" name="vendor_id" id="vendor_name" required>
                <option value="" disabled>Select Vendor</option>
                  @foreach($vendors as $item)
                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->id }})</option>
                  @endforeach
              </select>
            </div>

            <div class="col-12 col-md-2 mb-3">
              <label>Production Type</label>
              <select class="form-control" name="production_type" id="production_type" required>
                <option value="" disabled>Select Type</option>
                <option value="cmt" {{ $production->production_type == 'cmt' ? 'selected' : '' }}>CMT</option>
                <option value="sale_leather" {{ $production->production_type == 'sale_leather' ? 'selected' : '' }}>Sale Leather</option>
              </select>
              <input type="hidden" name="challan_generated" value="{{ $production->challan_generated ?? 0 }}">
            </div>

            <div class="col-12 col-md-2 mb-3">
              <label>Order Date</label>
              <input type="date" name="order_date" class="form-control" value="{{ \Carbon\Carbon::parse($production->order_date)->toDateString() }}" required />
            </div>


            <div class="col-12 col-md-4 mb-3">
              <label>Attachment (optional)</label>
              <input type="file" name="attachment" class="form-control">
              @if ($production->attachment)
                <a href="{{ asset('storage/' . $production->attachment) }}" target="_blank" class="d-block mt-2">View current file</a>
              @endif
            </div>
          </div>
        </div>
      </section>
    </div>

        <div class="table-responsive">
          <table class="table table-bordered" id="rawMaterialTable">
            <thead class="table-light">
              <tr>
                <th>Item</th>
                <th>Invoice</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Rate</th>
                <th>Amount</th>
                <th>Remarks</th>
                <th><button type="button" class="btn btn-sm btn-success" id="addRow"><i class="fas fa-plus"></i></button></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($production->details ?? [] as $index => $item)
              <tr>
                <td>

                  <select name="items[{{ $index }}][item_id]" class="form-control select2 item-select" data-index="{{ $index }}" required>
                    <option value="">Select Item</option>
                    @foreach ($allProducts as $product)
                      <option value="{{ $product->id }}" {{ $product->id == $item->product_id ? 'selected' : '' }} data-unit="{{ $product->unit }}" >{{ $product->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  @php
                    $invoices = \App\Models\PurchaseInvoiceItem::where('item_id', $item->product_id)
                      ->with('invoice')
                      ->get()
                      ->groupBy('invoice_id');
                  @endphp

                  <select name="items[{{ $index }}][invoice]" class="form-control invoice-dropdown" data-index="{{ $index }}" data-selected="{{ $item->invoice_id }}" required>
                    <option value="">Select Invoice</option>
                    @foreach ($invoices as $invoiceId => $group)
                      <option value="{{ $invoiceId }}" {{ $item->invoice_id == $invoiceId ? 'selected' : '' }}>
                        #{{ $invoiceId }}
                      </option>
                    @endforeach
                  </select>
                </td>
                <td><input type="number" name="items[{{ $index }}][qty]" class="form-control qty" value="{{ $item->qty }}" step="any" required></td>
                <td>
                  <select name="items[{{ $index }}][item_unit]" class="form-control" required>
                    <option value="" disabled>Select Unit</option>
                    @foreach($units as $unit)
                      <option value="{{ $unit->id }}" {{ $item->unit == $unit->id ? 'selected' : '' }}>
                        {{ $unit->name }}
                      </option>
                    @endforeach
                  </select>
                </td>
                <td><input type="number" name="items[{{ $index }}][rate]" class="form-control rate" value="{{ $item->rate }}" step="any" required></td>
                <td><input type="number" name="items[{{ $index }}][amount]" class="form-control amount" value="{{ $item->qty * $item->rate }}" readonly></td>
                <td><input type="text" name="items[{{ $index }}][remarks]" class="form-control" value="{{ $item->remarks }}"></td>
                <td><button type="button" class="btn btn-sm btn-danger removeRow"><i class="fas fa-trash"></i></button></td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="row">
          <div class="text-end">
            <h5>Total: <span id="net_total">{{ number_format($production->total_amount, 2) }}</span></h5>
            <input type="hidden" name="total_amount" id="total_amount" value="{{ $production->total_amount }}">
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-12 text-end">
            <button type="submit" class="btn btn-primary">Update</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  $(document).ready(function () {
    let rowIdx = {{ count($production->details ?? []) }};
    $('.select2').select2();

    // Load invoices for existing rows on page load
    $('#rawMaterialTable tbody tr').each(function () {
      const row = $(this);
      const itemSelect = row.find('.item-select');
      const invoiceSelect = row.find('.invoice-dropdown');
      const itemId = itemSelect.val();
      const selectedInvoice = invoiceSelect.attr('data-selected');

      if (itemId) {
        invoiceSelect.html('<option value="">Loading...</option>');

        fetch(`/item/${itemId}/invoices`)
          .then(res => res.json())
          .then(data => {
            invoiceSelect.html('<option value="">Select Invoice</option>');
            data.forEach(inv => {
              const selected = selectedInvoice == inv.id ? 'selected' : '';
              invoiceSelect.append(`<option value="${inv.id}" ${selected}>#${inv.id} - ${inv.vendor}</option>`);
            });

            if (selectedInvoice) {
              onInvoiceChange(invoiceSelect[0]);
            }
          });
      }
    });

    // Calculate per row amount and overall total
    function calculateTotal() {
      let total = 0;
      $('.amount').each(function () {
        total += parseFloat($(this).val()) || 0;
      });
      $('#net_total').text(total.toFixed(2));
      $('#total_amount').val(total.toFixed(2));
    }

    // Add new row
    $('#addRow').click(function () {
      const row = `<tr>
        <td>
          <select name="items[${rowIdx}][item_id]" class="form-control select2 item-select" data-index="${rowIdx}" required>
            <option value="">Select Item</option>
            @foreach ($allProducts as $product)
              <option value="{{ $product->id }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <select name="items[${rowIdx}][invoice]" class="form-control invoice-dropdown" required>
            <option value="">Select Invoice</option>
          </select>
        </td>
        <td><input type="number" name="items[${rowIdx}][qty]" class="form-control qty" step="0.01" required></td>
        <td>
          <select name="items[${rowIdx}][item_unit]" class="form-control" required>
            <option value="">Select Unit</option>
            @foreach($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
          </select>
        </td>
        <td><input type="number" name="items[${rowIdx}][rate]" class="form-control rate" step="0.01" required></td>
        <td><input type="number" name="items[${rowIdx}][amount]" class="form-control amount" readonly></td>
        
        <td><input type="text" name="items[${rowIdx}][remarks]" class="form-control"></td>
        <td><button type="button" class="btn btn-sm btn-danger removeRow"><i class="fas fa-trash"></i></button></td>
      </tr>`;
      $('#rawMaterialTable tbody').append(row);
      $('.select2').select2();
      
      rowIdx++;
    });

    // Remove row
    $(document).on('click', '.removeRow', function () {
      $(this).closest('tr').remove();
      calculateTotal();
    });

    // Item changed → Fetch invoices
    $(document).on('change', '.item-select', function () {
      const itemId = $(this).val();
      const row = $(this).closest('tr');
      const selectedOption = $(this).find('option:selected');

      const unit = selectedOption.data('unit') || '';
      row.find('select[name^="items"][name$="[item_unit]"]').val(unit);

      const invoiceSelect = row.find('.invoice-dropdown');
      row.find('.qty, .rate, .amount').val('');

      if (!itemId) return;

      invoiceSelect.html('<option value="">Loading...</option>');
      fetch(`/item/${itemId}/invoices`)
        .then(res => res.json())
        .then(data => {
          invoiceSelect.html('<option value="">Select Invoice</option>');
          data.forEach(inv => {
            invoiceSelect.append(`<option value="${inv.id}">#${inv.id} - ${inv.vendor}</option>`);
          });
        });
    });

    // Invoice changed → Fetch qty, rate, amount
    $(document).on('change', '.invoice-dropdown', function () {
      const select = this;
      const row = select.closest('tr');
      const invoiceId = select.value;
      const itemSelect = row.querySelector('.item-select');
      const itemId = itemSelect?.value;

      if (!invoiceId || !itemId) return;

      fetch(`/invoice-item/${invoiceId}/item/${itemId}`)
        .then(res => res.json())
        .then(data => {
          if (!data.error) {
            row.querySelector('.qty').value = data.quantity || 0;
            row.querySelector('.rate').value = data.price || 0;
            const amount = (data.quantity * data.price).toFixed(2);
            row.querySelector('.amount').value = amount;
            calculateTotal();
          }
        })
        .catch(() => console.warn("Failed to fetch invoice-item data."));
    });

    // Qty or rate manual change → update amount and total
    $(document).on('input', '.qty, .rate', function () {
      const row = $(this).closest('tr');
      const qty = parseFloat(row.find('.qty').val()) || 0;
      const rate = parseFloat(row.find('.rate').val()) || 0;
      const amount = qty * rate;
      row.find('.amount').val(amount.toFixed(2));
      calculateTotal();
    });

    calculateTotal();
  });
</script>

@endsection
