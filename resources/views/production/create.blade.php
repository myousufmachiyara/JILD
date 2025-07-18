@extends('layouts.app')

@section('title', 'Production | New Order')

@section('content')
  <div class="row">
    <form action="{{ route('production.store') }}" method="POST" enctype="multipart/form-data">
      @csrf

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
                <h2 class="card-title">New Order</h2>
              </div>
            </header>
            <div class="card-body">
              <div class="row">
                <div class="col-12 col-md-2 mb-3">
                  <label>Order #</label>
                  <input type="text" class="form-control" value="{{ $nextProductionCode ?? '' }}" disabled/>
                </div>

                <div class="col-12 col-md-2">
                  <label>Category<span style="color: red;"><strong>*</strong></span></label>
                  <select class="form-control" name="category_id" required>
                    <option value="" selected disabled>Select Category</option>
                      @foreach($categories as $item)  
                        <option value="{{$item->id}}">{{$item->name}}</option>
                      @endforeach
                  </select>
                </div>

                <div class="col-12 col-md-2 mb-3">
                  <label>Vendor Name</label>
                  <select data-plugin-selecttwo class="form-control select2-js" name="vendor_id" id="vendor_name" required>
                    <option value="" selected disabled>Select Vendor</option>
                      @foreach($vendors as $item)  
                        <option value="{{$item->id}}">{{$item->name}}</option>
                      @endforeach
                  </select>
                </div>

                <div class="col-12 col-md-2 mb-3">
                  <label>Production Type</label>
                  <select class="form-control" name="production_type" id="production_type" required>
                    <option value="" selected disabled>Select Type</option>
                    <option value="cmt">CMT</option>
                    <option value="sale_raw">Sale Leather</option>

                  </select>
                </div>

                <div class="col-12 col-md-2 mb-3">
                  <label>Order Date</label>
                  <input type="date" name="order_date" class="form-control" id="order_date" value="{{ date('Y-m-d') }}" required/>
                </div>

                <div class="col-12 col-md-2 mb-3">
                  <label>Challan #</label>
                  <input type="text" name="challan_no" class="form-control" value="{{ $nextChallanNo ?? '' }}" readonly required/>
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
              <table class="table table-bordered" id="myTable">
                <thead>
                  <tr>
                    <th>Raw</th>
                    <th>Purchase #</th>
                    <th>Rate</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Total</th>
                    <th width="10%"></th>
                  </tr>
                </thead>
                <tbody id="PurPOTbleBody">
                  <tr>
                    <td>
                      <select name="item_details[0][product_id]" id="productSelect0" class="form-control select2-js" onchange="onItemChange(this)" required>
                        <option value="" selected disabled>Select Leather</option>
                        @foreach($allProducts as $product)
                          <option value="{{ $product->id }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <select name="item_details[0][invoice_id]" id="invoiceSelect0" class="form-control" required onchange="onInvoiceChange(this)">
                        <option value="" disabled selected>Select Invoice</option>
                      </select>
                    </td>
                    <td><input type="number" name="item_details[0][item_rate]" id="item_rate_0" onchange="rowTotal(0)" step="any" value="0" class="form-control" placeholder="Rate" required/></td>
                    <td><input type="number" name="item_details[0][qty]" id="item_qty_0" onchange="rowTotal(0)" step="any" value="0" class="form-control" placeholder="Quantity" required/></td>
                    <td>
                      <select id="item_unit_0" class="form-control" name="item_details[0][item_unit]" required>
                        <option value="" disabled selected>Select Unit</option>
                         @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                         @endforeach          
                      </select>
                    </td>
                    <td><input type="number" id="item_total_0" class="form-control" placeholder="Total" disabled/></td>
                    <td width="5%">
                      <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
                      <button type="button" class="btn btn-primary btn-xs" onclick="addNewRow()"><i class="fa fa-plus"></i></button>
                    </td>
                  </tr>
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
              <button type="submit" class="btn btn-primary">Create</button>
            </footer>
          </section>
        </div>
      </div>
    </form>
  </div>
  <script>
    var index = 1;
    const allProducts = @json($allProducts);

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
      const lastRow = $('#PurPOTbleBody tr:last');
      const latestValue = lastRow.find('select').val();

      if (latestValue !== "") {
        const table = document.getElementById('myTable').getElementsByTagName('tbody')[0];
        const newRow = table.insertRow();

        const options = allProducts.map(p =>
          `<option value="${p.id}" data-unit="${p.unit ?? ''}">${p.name}</option>`
        ).join('');

        console.log(options);

        newRow.innerHTML = `
          <td>
            <select data-plugin-selecttwo name="item_details[${index}][product_id]" required id="productSelect${index}" class="form-control select2-js" onchange="onItemChange(this)">
              <option value="" disabled selected>Select Leather</option>
              ${options}
            </select>
          </td>
          <td>
            <select name="item_details[${index}][invoice_id]" id="invoiceSelect${index}" class="form-control" onchange="onInvoiceChange(this)" required>
              <option value="" disabled selected>Select Invoice</option>
            </select>
          </td>
          <td><input type="number" name="item_details[${index}][item_rate]" id="item_rate_${index}" step="any" value="0" onchange="rowTotal(${index})" class="form-control" required/></td>
          <td><input type="number" name="item_details[${index}][qty]" id="item_qty_${index}" step="any" value="0" onchange="rowTotal(${index})" class="form-control" required/></td>
          <td>
            <select id="item_unit_${index}" class="form-control" name="item_details[${index}][item_unit]" required>
              <option value="" disabled selected>Select Unit</option>
              @foreach($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
              @endforeach              
            </select>
          </td>
          <td><input type="number" id="item_total_${index}" class="form-control" placeholder="Total" disabled/></td>
          <td>
            <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
            <button type="button" onclick="addNewRow()" class="btn btn-primary btn-xs"><i class="fa fa-plus"></i></button>
          </td>
        `;

        index++;
        $('#myTable select[data-plugin-selecttwo]').select2();
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
      const net = parseFloat(total) || 0;
      $('#netTotal').text(formatNumberWithCommas(net.toFixed(0)));
    }

    function formatNumberWithCommas(x) {
      return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    function onItemChange(select) {
      const row = select.closest('tr');
      const itemId = select.value;
      const option = select.selectedOptions?.[0];

      if (!row || !itemId || !option) return;

      const unit = option.getAttribute('data-unit');

      // Set the unit field
      const unitSelect = row.querySelector('select[name^="item_details"][name$="[item_unit]"]');
      if (unitSelect && unit) unitSelect.value = unit;

      const invoiceSelect = row.querySelector('select[name^="item_details"][name$="[invoice_id]"]');
      if (!invoiceSelect) return;

      invoiceSelect.innerHTML = '<option value="">Loading...</option>';

      // Clear other fields
      row.querySelector(`input[name^="item_details"][name$="[qty]"]`).value = '';
      row.querySelector(`input[name^="item_details"][name$="[item_rate]"]`).value = '';
      row.querySelector(`input[id^="item_total_"]`).value = '';

      // Load invoice data
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
      const itemSelect = row.querySelector('select[name^="item_details"][name$="[product_id]"]');
      const itemId = itemSelect?.value;

      if (!invoiceId || !itemId) return;

      fetch(`/invoice-item/${invoiceId}/item/${itemId}`)
        .then(res => res.json())
          .then(data => {
            if (!data.error) {
              row.querySelector(`input[name^="item_details"][name$="[qty]"]`).value = data.quantity || 0;
              row.querySelector(`input[name^="item_details"][name$="[item_rate]"]`).value = data.price || 0;

              // Trigger row total
              const rowIndex = row.rowIndex - 1; // Adjust index if header row exists
              rowTotal(rowIndex);
            }
          })
          .catch(() => {
            console.warn("Failed to fetch invoice-item data.");
          });
    }


    function generateVoucher() {
      document.getElementById("voucher-container").innerHTML = "";

      const vendorName = document.querySelector("#vendor_name option:checked")?.textContent ?? "-";
      const date = $('#order_date').val();
      const challanNo = "FGPO-" + Math.floor(100000 + Math.random() * 900000);

      let items = [];
      let rows = document.querySelectorAll("#PurPOTbleBody tr");

      rows.forEach((row, i) => {
        const fabricSelect = row.querySelector(`select[name="item_details[${i}][product_id]"]`);
        const fabric = fabricSelect?.options[fabricSelect.selectedIndex]?.text ?? '-';

        const rate = row.querySelector(`#item_rate_${i}`)?.value ?? 0;
        const qty = row.querySelector(`#item_qty_${i}`)?.value ?? 0;
        const total = row.querySelector(`#item_total_${i}`)?.value ?? 0;

        const unit = "PCS"; // or replace with logic for unit
        const description = "-";

        if (fabric && qty && rate) {
          items.push({ fabric, description, rate, qty, unit, total });
        }
      });

      const totalAmount = items.reduce((sum, item) => sum + parseFloat(item.total || 0), 0);

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
                  </tr>
                `).join('')}
            </tbody>
          </table>

          <h4 class="text-end text-dark"><strong>Total Amount:</strong> PKR ${totalAmount.toFixed(0)}</h4>
          <input type="hidden" name="voucher_amount" value="${totalAmount.toFixed(0)}">
        </div>
      `;

      document.getElementById("voucher-container").innerHTML = html;
    }

    $(document).on("click", ".delete-row", function () {
      $(this).closest("tr").remove();
    });

    $(document).ready(function () {
      $('.select2-js').select2();
    });
  </script>

@endsection
