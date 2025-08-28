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
                <td>
                  <input type="text" class="form-control product-code" placeholder="Scan/Enter Code"
                         data-product-id="{{ $item->product_id }}" data-variation-id="{{ $item->variation_id }}">
                </td>
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
                <td>
                  <button type="button" class="btn btn-sm btn-danger removeRowBtn">X</button>
                  <input type="hidden" name="items[{{ $i }}][delete]" value="0" class="delete-flag">
                </td>
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

    // ---------- Helper: calculate row total ----------
    function calcRowTotal(row) {
        const qty = parseFloat(row.querySelector(".qty-input").value) || 0;
        const price = parseFloat(row.querySelector(".price-input").value) || 0;
        row.querySelector(".total-input").value = (qty * price).toFixed(2);
    }

    // ---------- Helper: update grand total ----------
    function updateGrandTotal() {
        let total = 0;
        document.querySelectorAll("#itemsTable tbody tr").forEach(row => {
            if(row.style.display !== "none"){
                total += parseFloat(row.querySelector(".total-input").value) || 0;
            }
        });
        document.getElementById("net_amount").value = total.toFixed(2);
    }

    // ---------- Helper: set product reliably (like Sale edit) ----------
    function setProductSelectValue(productSelect, productId, row) {
        productSelect.value = productId;
        productSelect.dispatchEvent(new Event('change'));

        // Apply wanted variation after product fetch
        if(row.dataset.wantedVariationId){
            setTimeout(() => {
                const variationSelect = row.querySelector(".variation-select");
                if(variationSelect.querySelector(`option[value="${row.dataset.wantedVariationId}"]`)){
                    variationSelect.value = row.dataset.wantedVariationId;
                }
                delete row.dataset.wantedVariationId;
            }, 200); // wait for variations to be fetched
        }

        // Apply preferred price if set
        if(row.dataset.preferPrice){
            row.querySelector(".price-input").value = parseFloat(row.dataset.preferPrice).toFixed(2);
            delete row.dataset.preferPrice;
        }
    }

    // ---------- Bind events to a row ----------
    function bindRowEvents(row) {
        // Remove row
        row.querySelector(".removeRowBtn").addEventListener("click", function() {
            const deleteFlag = row.querySelector(".delete-flag");
            if(deleteFlag){
                deleteFlag.value = "1";
                row.style.display = "none";
            } else {
                row.remove();
            }
            updateGrandTotal();
        });

        // Qty/Price input change
        row.querySelectorAll(".qty-input, .price-input").forEach(input => {
            input.addEventListener("input", () => {
                calcRowTotal(row);
                updateGrandTotal();
            });
        });

        // Product select change
        row.querySelector(".product-select").addEventListener("change", async function () {
            const productSelect = this;
            const productId = productSelect.value;
            const variationSelect = row.querySelector(".variation-select");

            // Set price from product option
            const price = parseFloat(productSelect.options[productSelect.selectedIndex].dataset.price) || 0;
            row.querySelector(".price-input").value = price.toFixed(2);

            // Fetch variations
            variationSelect.innerHTML = `<option value="">Loading...</option>`;
            if(productId){
                try {
                    const res = await fetch(`/product/${productId}/variations`);
                    const data = await res.json();

                    variationSelect.innerHTML = `<option value="">-- Select Variation --</option>`;
                    data.forEach(v => {
                        variationSelect.innerHTML += `<option value="${v.id}">${v.sku}</option>`;
                    });

                    // Apply wanted variation if set
                    if(row.dataset.wantedVariationId){
                        const wantedId = row.dataset.wantedVariationId;
                        if(variationSelect.querySelector(`option[value="${wantedId}"]`)){
                            variationSelect.value = wantedId;
                        }
                        delete row.dataset.wantedVariationId;
                    }

                } catch(err) {
                    variationSelect.innerHTML = `<option value="">-- Select Variation --</option>`;
                    console.error(err);
                }
            } else {
                variationSelect.innerHTML = `<option value="">-- Select Variation --</option>`;
            }

            calcRowTotal(row);
            updateGrandTotal();
        });

        // Barcode input
        row.querySelector(".product-code").addEventListener("blur", function() {
            const code = this.value.trim();
            if(!code) return;

            fetch(`/get-variation-by-code/${encodeURIComponent(code)}`)
            .then(res => res.json())
            .then(data => {
                if(data.success && data.variation){
                    const variation = data.variation;
                    const productSelect = row.querySelector(".product-select");

                    // Store wanted variation and preferred price
                    row.dataset.wantedVariationId = variation.id;
                    if(variation.price !== undefined && variation.price !== null){
                        row.dataset.preferPrice = variation.price;
                    }

                    // Set product -> triggers variation fetch
                    productSelect.value = variation.product_id;

                    // Trigger change manually
                    const event = new Event('change');
                    productSelect.dispatchEvent(event);

                    // Wait for variations to be populated, then select wanted variation
                    const variationSelect = row.querySelector(".variation-select");
                    const checkOptions = setInterval(() => {
                        if(variationSelect.querySelector(`option[value="${variation.id}"]`)){
                            variationSelect.value = variation.id;
                            clearInterval(checkOptions);
                        }
                    }, 50); // check every 50ms

                } else {
                    alert(data.message || "No variation found for this barcode");
                    this.value = '';
                    this.focus();
                }
            })
            .catch(() => alert('Error fetching variation by barcode.'));
        });

    }

    // ---------- Bind existing rows ----------
    document.querySelectorAll("#itemsTable tbody tr").forEach(row => bindRowEvents(row));

    // ---------- Add new row ----------
    document.getElementById("addRowBtn").addEventListener("click", function () {
        const tableBody = document.querySelector("#itemsTable tbody");
        const newRow = document.createElement("tr");
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
        bindRowEvents(newRow);
        rowIdx++;
    });

    // ---------- Initial grand total ----------
    updateGrandTotal();
});
</script>



@endsection
