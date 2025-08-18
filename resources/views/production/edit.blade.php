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
    <div class="row">
      <div class="col-12 col-md-12 mb-3">
        <section class="card">
          <header class="card-header">
            <div style="display: flex;justify-content: space-between;">
              <h2 class="card-title">Edit Production</h2>
            </div>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-12 col-md-1 mb-3">
                <label>Prod #</label>
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
                      <option value="{{ $item->id }}">{{ $item->name }}</option>
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
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Order Date</label>
                <input type="date" name="order_date" id="order_date" class="form-control" value="{{ \Carbon\Carbon::parse($production->order_date)->toDateString() }}" required />
              </div>

              <div class="col-12 col-md-3 mb-3">
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

      <div class="col-12 col-md-12 mb-3">
        <section class="card">
          <header class="card-header" style="display: flex;justify-content: space-between;">
            <h2 class="card-title">Raw Material Details</h2>
          </header>
          <div class="card-body">
            <table class="table table-bordered" id="rawMaterialTable">
              <thead class="table-light">
                <tr>
                  <th>Item</th>
                  <th>Invoice</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Rate</th>
                  <th>Amount</th>
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
                  <td><button type="button" class="btn btn-sm btn-danger removeRow"><i class="fas fa-trash"></i></button></td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div class="col-12 col-md-5 mb-3">
        <section class="card">
          <header class="card-header d-flex justify-content-between">
            <h2 class="card-title">Voucher (Challan #)</h2>
            <div>
              <a class="btn btn-danger text-end" onclick="generateVoucher()">Generate Challan</a>
            </div>
          </header>
          <div class="card-body">
            <div class="row pb-4">
              <div class="col-12 mt-3" id="voucher-container"></div>
            </div>
          </div>
        </section>
      </div>

      <div class="col-12 col-md-7">
        <section class="card">
          <header class="card-header d-flex justify-content-between">
            <h2 class="card-title">Summary</h2>
          </header>
          <div class="card-body">
            <div class="row pb-4">
              <div class="col-12 col-md-3">
                <label>Total Raw Quantity</label>
                <input type="number" class="form-control" id="total_fab" placeholder="Total Qty" disabled/>
              </div>

              <div class="col-12 col-md-3">
                <label>Total Raw Amount</label>
                <input type="number" class="form-control" id="total_fab_amt" placeholder="Total Amount" disabled />
              </div>
              
              <div class="col-12 col-md-5">
                <label>Attachment</label>
                <input type="file" class="form-control" name="attachments[]" multiple accept="image/png, image/jpeg, image/jpg, image/webp">
              </div>

              <div class="col-12 text-end">
                <h3 class="font-weight-bold mb-0 text-5 text-primary">Net Amount</h3>
                <span><strong class="text-4 text-primary">PKR <span id="netTotal" class="text-4 text-danger">0.00</span></strong></span>
                <input type="hidden" name="total_amount" id="net_amount">
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <a class="btn btn-danger" href="{{ route('production.index') }}">Discard</a>
            <button type="submit" class="btn btn-primary">Update</button>
          </footer>
        </section>
      </div>
    </div>
  </form>
</div>

<script>
  $(document).ready(function () {
    let rowIdx = {{ count($production->details ?? []) }};
    $('.select2').select2();

    // ---- AUTOFILL SUMMARY FUNCTION ----
    function calculateTotal() {
      let totalQty = 0;
      let totalAmt = 0;

      $('#rawMaterialTable tbody tr').each(function () {
        const qty = parseFloat($(this).find('.qty').val()) || 0;
        const rate = parseFloat($(this).find('.rate').val()) || 0;
        const amount = qty * rate;

        $(this).find('.amount').val(amount.toFixed(2));

        totalQty += qty;
        totalAmt += amount;
      });

      $('#total_fab').val(totalQty.toFixed(2));
      $('#total_fab_amt').val(totalAmt.toFixed(2));
      $('#netTotal').text(totalAmt.toFixed(2));
      $('#net_amount').val(totalAmt.toFixed(2));
    }

    // ---- CHALLAN GENERATION ----
    window.generateVoucher = function () {
      const voucherContainer = document.getElementById("voucher-container");
      voucherContainer.innerHTML = "";

      const vendorName = document.querySelector("#vendor_name option:checked")?.textContent ?? "-";
      const orderDate = $('#order_date').val();

      let itemsHTML = "";
      let grandTotal = 0;

      document.querySelectorAll("#rawMaterialTable tbody tr").forEach((row) => {
        const productName = row.querySelector('.item-select option:checked')?.textContent ?? "-";
        const qty = parseFloat(row.querySelector('.qty')?.value || 0);
        const unit = row.querySelector('select[name*="[item_unit]"] option:checked')?.textContent ?? "-";
        const rate = parseFloat(row.querySelector('.rate')?.value || 0);
        const total = qty * rate;
        grandTotal += total;

        itemsHTML += `
          <tr>
            <td>${productName}</td>
            <td>${qty} ${unit}</td>
            <td>${rate.toFixed(2)}</td>
            <td>${total.toFixed(2)}</td>
          </tr>
        `;
      });

      const html = `
        <div class="border p-3 mt-3">
          <h3 class="text-center text-dark">Production Challan</h3>
          <hr>
          <div class="d-flex justify-content-between text-dark">
            <p><strong>Vendor:</strong> ${vendorName}</p>
            <p><strong>Date:</strong> ${orderDate}</p>
          </div>
          <table class="table table-bordered mt-3">
            <thead class="bg-light">
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              ${itemsHTML}
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Grand Total</th>
                <th>${grandTotal.toFixed(2)}</th>
              </tr>
            </tfoot>
          </table>
          <input type="hidden" name="voucher_amount" value="${grandTotal}">
          <div class="d-flex justify-content-between mt-4">
            <div>
              <p class="text-dark"><strong>Authorized By:</strong></p>
              <p>________________________</p>
            </div>
          </div>
        </div>
      `;

      voucherContainer.innerHTML = html;

      // Ensure hidden inputs exist in form
      const form = document.querySelector('#productionForm');
      const ensureHiddenInput = (name, value) => {
        let input = form.querySelector(`input[name="${name}"]`);
        if (!input) {
          input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          form.appendChild(input);
        }
        input.value = value;
      };
      ensureHiddenInput("voucher_amount", grandTotal.toFixed(2));
    }

    // ---- AUTO-FILL IF CMT ----
    if ($('#production_type').val() === 'sale_leather') {
      generateVoucher();
    }

    // On type change → toggle challan
    $('#production_type').on('change', function () {
      if ($(this).val() === 'sale_leather') {
        generateVoucher();
      } else {
        $('#voucher-container').empty();
      }
    });

    // Trigger summary once
    calculateTotal();

    // Recalculate summary whenever qty or rate changes
    $(document).on('input', '.qty, .rate', function () {
      calculateTotal();
    });

    // Recalculate & regenerate challan whenever items table changes
    $(document).on('input change', '#rawMaterialTable input, #rawMaterialTable select', function () {
      calculateTotal();
      if ($('#production_type').val() === 'cmt') {
        generateVoucher();
      }
    });

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
        <td><input type="number" name="items[${rowIdx}][qty]" class="form-control qty" step="any" required></td>
        <td>
          <select name="items[${rowIdx}][item_unit]" class="form-control" required>
            <option value="">Select Unit</option>
            @foreach($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
          </select>
        </td>
        <td><input type="number" name="items[${rowIdx}][rate]" class="form-control rate" step="any" required></td>
        <td><input type="number" name="items[${rowIdx}][amount]" class="form-control amount" readonly></td>        
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
