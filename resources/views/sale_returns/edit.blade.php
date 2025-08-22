@extends('layouts.app')
@section('title', 'Edit Sale Return')

@section('content')
<div class="row">
  <div class="col">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title">Edit Sale Return</h4>
        <a href="{{ route('sale_return.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
      </div>
      <div class="card-body">
        <form action="{{ route('sale_return.update', $return->id) }}" method="POST" id="saleReturnForm">
          @csrf
          @method('PUT')
          <div class="row mb-3">
            <div class="col-md-3">
              <label>Customer Name</label>
              <select name="account_id" class="form-control" required>
                <option value="">Select Customer</option>
                @foreach($customers as $cust)
                  <option value="{{ $cust->id }}" {{ $return->account_id == $cust->id ? 'selected' : '' }}>
                    {{ $cust->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ $return->return_date }}" required>
            </div>
            <div class="col-md-2">
              <label>Sale Inv #</label>
              <input type="text" name="sale_invoice_no" class="form-control" value="{{ $return->sale_invoice_no }}">
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
                <th width="5%">
                    <button type="button" class="btn btn-sm btn-success" id="addRowBtn"><i class="fas fa-plus"></i></button>
                </th>
              </tr>
            </thead>
            <tbody>
              @foreach($return->items as $i => $item)
              <tr>
                <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
                <td>
                  <select name="items[{{ $i }}][product_id]" class="form-control product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $prod)
                      <option value="{{ $prod->id }}" data-price="{{ $prod->selling_price }}"
                        {{ $item->product_id == $prod->id ? 'selected' : '' }}>
                        {{ $prod->name }}
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[{{ $i }}][variation_id]" class="form-control variation-select">
                    <option value="">Select Variation</option>
                    @if($item->product && $item->product->variations)
                      @foreach($item->product->variations as $var)
                        <option value="{{ $var->id }}" data-price="{{ $var->price }}"
                          {{ $item->variation_id == $var->id ? 'selected' : '' }}>
                          {{ $var->sku }}
                        </option>
                      @endforeach
                    @endif
                  </select>
                </td>
                <td><input type="number" name="items[{{ $i }}][qty]" class="form-control qty-input" value="{{ $item->qty }}" min="1"></td>
                <td><input type="number" name="items[{{ $i }}][price]" class="form-control price-input" value="{{ $item->price }}" step="0.01"></td>
                <td><input type="number" name="items[{{ $i }}][total]" class="form-control total-input" value="{{ $item->qty * $item->price }}" readonly></td>

                <td><button type="button" class="btn btn-sm btn-danger removeRow"><i class="fas fa-trash"></i></button></td>
              </tr>
              @endforeach
            </tbody>
          </table>

          <div class="row mt-3">
            <div class="col-md-6">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $return->remarks }}</textarea>
            </div>
            <div class="col-md-2 offset-md-4">
              <label>Net Amount</label>
              <input type="number" name="net_amount" id="net_amount" class="form-control" value="{{ $return->items->sum(fn($x) => $x->qty * $x->price) }}" readonly>
            </div>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Update Return</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        let rowIdx = document.querySelectorAll("#itemsTable tbody tr").length;

        // ✅ Add new row
        document.getElementById("addRowBtn").addEventListener("click", function () {
            let tableBody = document.querySelector("#itemsTable tbody");
            let newRow = document.createElement("tr");
            newRow.innerHTML = `
                <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
                <td>
                    <select name="items[${rowIdx}][product_id]" class="form-control product-select" required>
                        <option value="">-- Select Product --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <select name="items[${rowIdx}][variation_id]" class="form-control variation-select">
                        <option value="">-- Select Variation --</option>
                    </select>
                </td>
                <td><input type="number" name="items[${rowIdx}][qty]" class="form-control qty-input" value="1" min="1"></td>
                <td><input type="number" step="0.01" name="items[${rowIdx}][price]" class="form-control price-input" value="0"></td>
                <td><input type="text" class="form-control total-input" readonly value="0"></td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm removeRowBtn">X</button>
                    <input type="hidden" name="items[${rowIdx}][delete]" value="0" class="delete-flag">
                </td>
            `;
            tableBody.appendChild(newRow);
            rowIdx++;
        });

        // ✅ Remove row (soft delete for existing items)
        document.addEventListener("click", function (e) {
            if (e.target.classList.contains("removeRowBtn")) {
                let row = e.target.closest("tr");
                let deleteFlag = row.querySelector(".delete-flag");
                if (deleteFlag) {
                    deleteFlag.value = "1"; // mark for deletion
                    row.style.display = "none"; // hide row
                } else {
                    row.remove(); // new unsaved row
                }
                updateGrandTotal();
            }
        });

        // ✅ Auto-calc row total
        document.addEventListener("input", function (e) {
            if (e.target.classList.contains("qty-input") || e.target.classList.contains("price-input")) {
                let row = e.target.closest("tr");
                let qty = parseFloat(row.querySelector(".qty-input").value) || 0;
                let price = parseFloat(row.querySelector(".price-input").value) || 0;
                row.querySelector(".total-input").value = (qty * price).toFixed(2);
                updateGrandTotal();
            }
        });

        // ✅ Update grand total
        function updateGrandTotal() {
            let grandTotal = 0;
            document.querySelectorAll("#itemsTable tbody tr").forEach(function (row) {
                if (row.style.display !== "none") {
                    let total = parseFloat(row.querySelector(".total-input").value) || 0;
                    grandTotal += total;
                }
            });
            document.getElementById("net_amount").value = grandTotal.toFixed(2);
        }

        // ✅ Product selection handler
        document.addEventListener("change", function (e) {
            if (e.target.classList.contains("product-select")) {
                let row = e.target.closest("tr");
                let selected = e.target.options[e.target.selectedIndex];
                let productId = e.target.value;
                let price = parseFloat(selected.getAttribute("data-price")) || 0;

                // 1️⃣ Set price input
                row.querySelector(".price-input").value = price.toFixed(2);

                // 2️⃣ Reset and fetch variation options
                let variationSelect = row.querySelector(".variation-select");
                variationSelect.innerHTML = `<option value="">Loading...</option>`;
                if (productId) {
                    fetch(`/product/${productId}/variations`)
                        .then(res => res.json())
                        .then(data => {
                            variationSelect.innerHTML = `<option value="">-- Select Variation --</option>`;
                            data.forEach(v => {
                                variationSelect.innerHTML += `<option value="${v.id}" data-price="${v.selling_price}">${v.sku}</option>`;
                            });
                        })
                        .catch(() => {
                            variationSelect.innerHTML = `<option value="">-- Select Variation --</option>`;
                        });
                } else {
                    variationSelect.innerHTML = `<option value="">-- Select Variation --</option>`;
                }

                // 3️⃣ Update row total
                let qty = parseFloat(row.querySelector(".qty-input").value) || 0;
                row.querySelector(".total-input").value = (qty * price).toFixed(2);

                // 4️⃣ Update grand total
                updateGrandTotal();
            }
        });


        // ✅ Init totals for existing rows
        updateGrandTotal();
    });
</script>


@endsection
