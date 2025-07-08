@extends('layouts.app')

@section('title', 'Production | Edit Order')

@section('content')
<div class="row">
  <form action="{{ route('production.update', $production->id) }}" method="POST" enctype="multipart/form-data">
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
              <h2 class="card-title">Edit Production Order</h2>
            </div>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-12 col-md-2 mb-3">
                <label>Order #</label>
                <input type="text" class="form-control" value="{{ $production->id }}" disabled/>
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Category<span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="category_id" required>
                  <option disabled selected>Select Category</option>
                  @foreach($categories as $item)
                    <option value="{{ $item->id }}" {{ $production->category_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Vendor Name</label>
                <select class="form-control select2-js" name="vendor_id" id="vendor_name" required>
                  <option disabled selected>Select Vendor</option>
                  @foreach($vendors as $item)
                    <option value="{{ $item->id }}" {{ $production->vendor_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Production Type</label>
                <select class="form-control select2-js" name="production_type" id="production_type" required>
                  <option value="" disabled>Select Production Type</option>
                  <option value="cmt" {{ $production->production_type == 'cmt' ? 'selected' : '' }}>CMT</option>
                  <option value="sale_raw" {{ $production->production_type == 'sale_raw' ? 'selected' : '' }}>Sale Leather</option>
                </select>
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Order Date</label>
                <input type="date" name="order_date" class="form-control" id="order_date" value="{{ $production->order_date }}" required/>
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Challan #</label>
                <input type="text" class="form-control" value="{{ $production->challan_no ?? 'Auto' }}" disabled/>
              </div>
            </div>
          </div>
        </section>
      </div>

      <div class="col-12 col-md-12 mb-3">
        <section class="card">
          <header class="card-header d-flex justify-content-between">
            <h2 class="card-title">Raw Material Details</h2>
          </header>
          <div class="card-body">
            <table class="table table-bordered" id="myTable">
              <thead>
                <tr>
                  <th>Raw</th>
                  <th>Rate</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Total</th>
                  <th width="10%"></th>
                </tr>
              </thead>
              <tbody id="PurPOTbleBody">
                @foreach($production->details as $key => $item)
                <tr>
                  <td>
                    <select class="form-control select2-js" name="item_details[{{ $key }}][product_id]" id="productSelect{{ $key }}" onchange="getData({{ $key }})" required>
                      <option value="" disabled>Select Fabric</option>
                      @foreach($products as $product)
                        <option value="{{ $product->id }}" data-unit="{{ $product->unit }}" {{ $product->id == $item->product_id ? 'selected' : '' }}>{{ $product->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="item_details[{{ $key }}][item_rate]" id="item_rate_{{ $key }}" value="{{ $item->rate }}" step="any" onchange="rowTotal({{ $key }})" class="form-control" required/></td>
                  <td><input type="number" name="item_details[{{ $key }}][qty]" id="item_qty_{{ $key }}" value="{{ $item->qty }}" step="any" onchange="rowTotal({{ $key }})" class="form-control" required/></td>
                  <td>
                    <select class="form-control" name="item_details[{{ $key }}][item_unit]" id="item_unit_{{ $key }}" required>
                      <option value="" disabled>Select Unit</option>
                      <option value="mtr" {{ $item->unit == 'mtr' ? 'selected' : '' }}>Meter</option>
                      <option value="sq_ft" {{ $item->unit == 'sq_ft' ? 'selected' : '' }}>Sq.ft</option>
                    </select>
                  </td>
                  <td><input type="number" id="item_total_{{ $key }}" value="{{ $item->qty * $item->rate }}" class="form-control" disabled/></td>
                  <td>
                    <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
                    <button type="button" class="btn btn-primary btn-xs" onclick="addNewRow()"><i class="fa fa-plus"></i></button>
                  </td>
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
  const allProducts = @json($allProducts ?? []);
  let index = {{ count($production->details ?? []) }};

  function removeRow(button) {
    const tableRows = $("#PurPOTbleBody tr").length;
    if (tableRows > 1) {
      const row = button.closest('tr');
      row.remove();
      index--;
      tableTotal();
    }
  }

  function addNewRow() {
    const table = document.getElementById('myTable').getElementsByTagName('tbody')[0];

    const options = allProducts.map(p =>
      `<option value="${p.id}" data-unit="${p.unit}">${p.name}</option>`
    ).join('');

    const newRow = table.insertRow();
    newRow.innerHTML = `
      <td>
        <select class="form-control select2-js" name="item_details[${index}][product_id]" id="productSelect${index}" onchange="getData(${index})" required>
          <option value="" disabled selected>Select Fabric</option>
          ${options}
        </select>
      </td>
      <td><input type="number" name="item_details[${index}][item_rate]" id="item_rate_${index}" step="any" value="0" onchange="rowTotal(${index})" class="form-control" required/></td>
      <td><input type="number" name="item_details[${index}][qty]" id="item_qty_${index}" step="any" value="0" onchange="rowTotal(${index})" class="form-control" required/></td>
      <td>
        <select class="form-control" name="item_details[${index}][item_unit]" id="item_unit_${index}" required>
          <option value="" disabled selected>Select Unit</option>
          <option value="mtr">Meter</option>
          <option value="sq_ft">Sq.ft</option>
        </select>
      </td>
      <td><input type="number" id="item_total_${index}" class="form-control" placeholder="Total" disabled/></td>
      <td>
        <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
        <button type="button" onclick="addNewRow()" class="btn btn-primary btn-xs"><i class="fa fa-plus"></i></button>
      </td>
    `;

    $('.select2-js').each(function () {
      if ($(this).hasClass("select2-hidden-accessible")) {
        $(this).select2('destroy');
      }
    });
    $('.select2-js').select2();
    index++;
  }

  function getData(row) {
    const unit = $(`#productSelect${row} option:selected`).data('unit');
    const unitSelect = $(`#item_unit_${row}`);
    unitSelect.find('option').prop('selected', false);
    if (unit) {
      if (!unitSelect.find(`option[value="${unit}"]`).length) {
        unitSelect.append(`<option value="${unit}">${unit}</option>`);
      }
      unitSelect.val(unit);
    }
  }

  function rowTotal(i) {
    const rate = parseFloat($(`#item_rate_${i}`).val()) || 0;
    const qty = parseFloat($(`#item_qty_${i}`).val()) || 0;
    const total = rate * qty;
    $(`#item_total_${i}`).val(total.toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let totalQty = 0;
    let totalAmt = 0;
    $('#PurPOTbleBody tr').each(function () {
      const rate = parseFloat($(this).find('input[id^="item_rate_"]').val()) || 0;
      const qty = parseFloat($(this).find('input[id^="item_qty_"]').val()) || 0;
      totalQty += qty;
      totalAmt += rate * qty;
    });
    $('#total_fab').val(totalQty);
    $('#total_fab_amt').val(totalAmt.toFixed(2));
    updateNetTotal(totalAmt);
  }

  function updateNetTotal(total) {
    $('#netTotal').text(formatNumberWithCommas(total.toFixed(0)));
    $('#net_amount').val(total.toFixed(2));
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function generateVoucher() {
  const container = document.getElementById("voucher-container");
  container.innerHTML = "";

  const vendorName = document.querySelector("#vendor_name option:checked")?.textContent?.trim() || "-";
  const date = document.getElementById("order_date").value || "-";
  const challanNo = "{{ $production->challan_no ?? 'FGPO-' . rand(100000, 999999) }}";

  const rows = document.querySelectorAll("#PurPOTbleBody tr");
  const items = [];

  rows.forEach((row) => {
    const fabric = row.querySelector('select[name*="[product_id]"]')?.selectedOptions[0]?.textContent?.trim() || "-";
    const rate = parseFloat(row.querySelector('input[name*="[item_rate]"]')?.value) || 0;
    const qty = parseFloat(row.querySelector('input[name*="[qty]"]')?.value) || 0;
    const total = parseFloat(row.querySelector('input[id^="item_total_"]')?.value) || 0;
    const unit = row.querySelector('select[name*="[item_unit]"] option:checked')?.textContent?.trim() || "PCS";

    if (fabric !== "-" && qty > 0 && rate > 0) {
      items.push({ fabric, description: "-", rate, qty, unit, total });
    }
  });

  const totalAmount = items.reduce((sum, item) => sum + item.total, 0);

  let html = `
    <div class="border p-3 mt-3">
      <h3 class="text-center text-dark">Challan / Delivery Note</h3>
      <hr>
      <div class="d-flex justify-content-between text-dark">
        <p><strong>Vendor:</strong> ${vendorName}</p>
        <p><strong>Challan #:</strong> ${challanNo}</p>
        <p><strong>Date:</strong> ${date}</p>
      </div>

      <table class="table table-bordered mt-3">
        <thead>
          <tr>
            <th>Fabric</th>
            <th>Description</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Rate</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          ${items.map(item => `
            <tr>
              <td>${item.fabric}</td>
              <td>${item.description}</td>
              <td>${item.qty}</td>
              <td>${item.unit}</td>
              <td>${item.rate}</td>
              <td>${item.total}</td>
            </tr>`).join("")}
        </tbody>
      </table>

      <h4 class="text-end text-dark"><strong>Total Amount:</strong> PKR ${totalAmount.toFixed(0)}</h4>
      <input type="hidden" name="voucher_amount" value="${totalAmount.toFixed(0)}">
    </div>
  `;

  container.innerHTML = html;
}

  $(document).ready(function () {
    $('.select2-js').select2();
    tableTotal();
  });
</script>
@endsection
