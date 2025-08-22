@extends('layouts.app')
@section('title', 'Sale Return')

@section('content')
<div class="row">
  <div class="col">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title">New Sale Return</h4>
        <a href="{{ route('sale_return.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
      </div>
      <div class="card-body">
        <form action="{{ route('sale_return.store') }}" method="POST" id="saleReturnForm">
          @csrf
          <div class="row mb-3">
            <div class="col-md-3">
              <label for="customer_id">Customer Name</label>
              <select name="customer_id" class="form-control" required>
                <option value="">Select Customer</option>
                @foreach($customers as $cust)
                  <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label for="return_date">Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2">
              <label for="sale_invoice_no">Sale Inv #</label>
              <input type="text" name="sale_invoice_no" class="form-control">
            </div>
          </div>

          <table class="table table-bordered" id="itemsTable">
            <thead>
              <tr>
                <th width="15%">Item Code</th>
                <th>Product</th>
                <th>Variation</th>
                <th width="8%">Qty</th>
                <th width="10%">Price</th>
                <th width="12%">Total</th>
                <th width="5%"><button type="button" class="btn btn-sm btn-success" id="addRow"><i class="fas fa-plus"></i></button></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
                <td>
                  <select name="items[0][product_id]" class="form-control product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $prod)
                      <option value="{{ $prod->id }}" data-price="{{ $prod->selling_price }}">{{ $prod->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[0][variation_id]" class="form-control variation-select" required>
                    <option value="">Select Variation</option>
                  </select>
                </td>
                <td><input type="number" name="items[0][qty]" class="form-control qty" value="1" min="1"></td>
                <td><input type="number" name="items[0][price]" class="form-control price" step="0.01"></td>
                <td><input type="number" name="items[0][total]" class="form-control total" readonly></td>
                <td><button type="button" class="btn btn-sm btn-danger removeRow"><i class="fas fa-trash"></i></button></td>
              </tr>
            </tbody>
          </table>

          <div class="row mt-3">
            <div class="col-md-6">
              <label for="remarks">Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-4 offset-md-2">
              <label for="net_amount">Net Amount</label>
              <input type="number" name="net_amount" id="net_amount" class="form-control" readonly>
            </div>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Save Return</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    let rowIndex = 1;

    // Add new row
    $("#addRow").click(function () {
        let newRow = `<tr>
            <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
            <td>
              <select name="items[${rowIndex}][product_id]" class="form-control product-select" required>
                <option value="">Select Product</option>
                @foreach($products as $prod)
                  <option value="{{ $prod->id }}" data-price="{{ $prod->selling_price }}">{{ $prod->name }}</option>
                @endforeach
              </select>
            </td>
            <td>
              <select name="items[${rowIndex}][variation_id]" class="form-control variation-select" required>
                <option value="">Select Variation</option>
              </select>
            </td>
            <td><input type="number" name="items[${rowIndex}][qty]" class="form-control qty" value="1" min="1"></td>
            <td><input type="number" name="items[${rowIndex}][price]" class="form-control price" step="0.01"></td>
            <td><input type="number" name="items[${rowIndex}][total]" class="form-control total" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger removeRow"><i class="fas fa-trash"></i></button></td>
          </tr>`;
        $("#itemsTable tbody").append(newRow);
        rowIndex++;
    });

    // Remove row
    $(document).on("click", ".removeRow", function () {
        $(this).closest("tr").remove();
        calculateNetAmount();
    });

    // Price change → recalc total
    $(document).on("input", ".price", function () {
        let row = $(this).closest("tr");
        let price = parseFloat($(this).val()) || 0;
        let qty = parseFloat(row.find(".qty").val()) || 1;
        row.find(".total").val((qty * price).toFixed(2));
        calculateNetAmount();
    });
    
    // Product change → fetch base price + load variations
    $(document).on("change", ".product-select", function () {
        let row = $(this).closest("tr");
        let selectedOption = $(this).find(":selected");
        let productPrice = selectedOption.data("price") || 0;

        row.find(".price").val(productPrice);
        let qty = row.find(".qty").val() || 1;
        row.find(".total").val((qty * productPrice).toFixed(2));

        // Load variations for selected product
        let productId = $(this).val();
        let variationSelect = row.find(".variation-select");
        variationSelect.empty().append('<option value="">Loading...</option>');
        if (productId) {
          $.get(`/product/${productId}/variations`, function (data) {
            variationSelect.empty().append('<option value="">Select Variation</option>');
            data.forEach(function (v) {
              variationSelect.append(`<option value="${v.id}" data-price="${v.price}">${v.sku}</option>`);
            });
          });
        } else {
          variationSelect.empty().append('<option value="">Select Variation</option>');
        }

        calculateNetAmount();
    });

    // Qty change → recalc total
    $(document).on("input", ".qty", function () {
        let row = $(this).closest("tr");
        let qty = $(this).val();
        let price = row.find(".price").val();
        row.find(".total").val((qty * price).toFixed(2));
        calculateNetAmount();
    });

    function calculateNetAmount() {
        let net = 0;
        $(".total").each(function () {
            net += parseFloat($(this).val()) || 0;
        });
        $("#net_amount").val(net.toFixed(2));
    }
});
</script>
@endsection
