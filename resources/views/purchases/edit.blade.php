@extends('layouts.app')

@section('title', 'Purchases | Edit Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.update', $invoice->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Invoice</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <input type="hidden" id="itemCount" name="items" value="{{ count($invoice->items) }}">

            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ $invoice->invoice_date }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $vendor->id == $invoice->vendor_id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Payment Terms</label>
              <input type="text" name="payment_terms" class="form-control" value="{{ $invoice->payment_terms }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Bill #</label>
              <input type="text" name="bill_no" class="form-control" value="{{ $invoice->bill_no }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref #</label>
              <input type="text" name="ref_no" class="form-control" value="{{ $invoice->ref_no }}">
            </div>

            <div class="col-md-6 mb-3">
              <label>Attachments (Upload New)</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if ($invoice->attachments && count($invoice->attachments))
                <div class="mt-2">
                  <strong>Existing Files:</strong>
                  <ul>
                    @foreach ($invoice->attachments as $att)
                      <li><a href="{{ asset('storage/' . $att->file_path) }}" target="_blank">{{ basename($att->file_path) }}</a></li>
                    @endforeach
                  </ul>
                </div>
              @endif
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $invoice->remarks }}</textarea>
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
                @foreach ($invoice->items as $i => $item)
                <tr>
                  <td><input type="text" name="item_cod[]" value="{{ $item->item_cod }}" class="form-control"></td>
                  <td>
                    <select name="item_name[]" class="form-control select2-js">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ $product->id == $item->item_id ? 'selected' : '' }}>{{ $product->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="text" name="bundle[]" value="{{ $item->bundle }}" class="form-control" onchange="rowTotal({{ $i + 1 }})"></td>
                  <td><input type="number" name="quantity[]" value="{{ $item->quantity }}" class="form-control" onchange="rowTotal({{ $i + 1 }})"></td>
                  <td><input type="text" name="unit[]" value="{{ $item->unit }}" class="form-control"></td>
                  <td><input type="number" name="price[]" value="{{ $item->price }}" class="form-control" onchange="rowTotal({{ $i + 1 }})"></td>
                  <td><input type="number" value="{{ $item->quantity * $item->price }}" class="form-control" step="any" disabled></td>
                  <td>
                    <button type="button" class="btn btn-danger" onclick="removeRow(this)">
                      <i class="fas fa-trash"></i>
                    </button>
                    <button type="button" class="btn btn-primary mt-1" onclick="addNewRow_btn()">
                      <i class="fas fa-plus"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show" value="{{ $invoice->total_amount }}">
            </div>
            <div class="col-md-2">
              <label>Total Bundle</label>
              <input type="text" id="total_weight" class="form-control" disabled>
              <input type="hidden" name="total_weight" id="total_weight_show" value="{{ $invoice->total_weight }}">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show" value="{{ $invoice->total_quantity }}">
            </div>
            <div class="col-md-2">
              <label>Convance Charges</label>
              <input type="number" name="convance_charges" id="convance_charges" class="form-control" value="{{ $invoice->convance_charges }}" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Labour Charges</label>
              <input type="number" name="labour_charges" id="labour_charges" class="form-control" value="{{ $invoice->labour_charges }}" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Bill Discount</label>
              <input type="number" name="bill_discount" id="bill_discount" class="form-control" value="{{ $invoice->bill_discount }}" onchange="netTotal()">
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">{{ number_format($invoice->net_amount, 2) }}</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount" value="{{ $invoice->net_amount }}">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"> <i class="fas fa-save"></i> Update Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
    let rowIndex = {{ count($invoice->items) + 1 }};

    $(document).ready(function () {
        $('.select2-js').select2();
    });

    function addNewRow_btn() {
        addNewRow();
        $('#item_cod' + (rowIndex - 1)).focus();
    }

    function addNewRow() {
        let table = $("#Purchase1Table");
        let newRow = `
            <tr>
                <td><input type="text" name="item_cod[]" id="item_cod${rowIndex}" class="form-control"></td>
                <td>
                    <select name="item_name[]" id="item_name${rowIndex}" class="form-control select2-js">
                        <option value="">Select Item</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td><input type="text" name="bundle[]" id="pur_qty2_${rowIndex}" class="form-control" value="0" onchange="rowTotal(${rowIndex})"></td>
                <td><input type="number" name="quantity[]" id="pur_qty${rowIndex}" class="form-control" value="0" step="any" onchange="rowTotal(${rowIndex})"></td>
                <td><input type="text" name="unit[]" id="remarks${rowIndex}" class="form-control"></td>
                <td><input type="number" name="price[]" id="pur_price${rowIndex}" class="form-control" value="0" step="any" onchange="rowTotal(${rowIndex})"></td>
                <td><input type="number" id="amount${rowIndex}" class="form-control" value="0" step="any" disabled></td>
                <td>
                    <button type="button" class="btn btn-danger" onclick="removeRow(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button type="button" class="btn btn-primary mt-1" onclick="addNewRow_btn()">
                        <i class="fas fa-plus"></i>
                    </button>
                </td>
            </tr>
        `;
        table.append(newRow);
        $('#itemCount').val(rowIndex);
        $('#item_name' + rowIndex).select2();
        rowIndex++;
    }

    function removeRow(button) {
        let tableRows = $("#Purchase1Table tr").length;
        if (tableRows > 1) {
            $(button).closest('tr').remove();
            $('#itemCount').val(--tableRows);
            tableTotal();
        }
    }

    function rowTotal(row_no) {
        let quantity = parseFloat($('#pur_qty' + row_no).val()) || 0;
        let price = parseFloat($('#pur_price' + row_no).val()) || 0;
        let amount = (quantity * price).toFixed(2);
        $('#amount' + row_no).val(amount);
        tableTotal();
    }

    function tableTotal() {
        let totalAmount = 0, totalWeight = 0, totalQuantity = 0;
        $("#Purchase1Table tr").each(function () {
            totalAmount += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
            totalWeight += parseFloat($(this).find('input[name="bundle[]"]').val()) || 0;
            totalQuantity += parseFloat($(this).find('input[name="quantity[]"]').val()) || 0;
        });

        $('#totalAmount').val(totalAmount.toFixed(2));
        $('#total_amount_show').val(totalAmount.toFixed(2));
        $('#total_weight').val(totalWeight.toFixed(2));
        $('#total_weight_show').val(totalWeight.toFixed(2));
        $('#total_quantity').val(totalQuantity.toFixed(2));
        $('#total_quantity_show').val(totalQuantity.toFixed(2));

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
