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
                @foreach($production->items as $key => $item)
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
                    <select class="form-control select2-js" name="item_details[{{ $key }}][item_unit]" id="item_unit_{{ $key }}" required>
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
  var index = {{ count($production->items) }};
  const allProducts = @json($allProducts);

  // (reuse same JS functions from create view: removeRow, addNewRow, rowTotal, tableTotal, updateNetTotal, formatNumberWithCommas, getData, generateVoucher)
</script>

<script>
  $(document).ready(function () {
    $('.select2-js').select2();
    tableTotal();
  });
</script>
@endsection
