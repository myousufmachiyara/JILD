@extends('layouts.pos-header')

@section('title', 'POS System')

@section('content')
<div class="container-fluid bg-light py-3" style="min-height:100vh;">
  <div class="row g-3">
    
    <!-- LEFT SIDE : PRODUCTS -->
    <div class="col-lg-6">
      


      <!-- Product Grid -->
      <div class="row" id="productGrid">
        @foreach($items as $item)
        <div class="col-md-3 col-6 mb-4 product-card-wrapper" data-name="{{ strtolower($item->item_name) }}">
          <div class="card h-100 product-card text-center">
            <img src="{{ $item->image ?? '/assets/img/empty-300x240.jpg' }}" 
                 class="card-img-top p-3" alt="{{ $item->item_name }}" 
                 style="height:120px;object-fit:contain;">
            <div class="card-body p-2">
              <h6 class="mb-1 text-truncate" style="font-size:14px;">{{ $item->item_name }}</h6>
              <p class="text-primary fw-bold mb-2" style="font-size:14px;">${{ number_format($item->sales_price, 2) }}</p>
              <button class="btn btn-sm btn-primary w-100 fw-bold" onclick="addToCart('{{ $item->id }}')">Add</button>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    
    <!-- RIGHT SIDE : CART & SUMMARY -->
    <div class="col-lg-6">
      <!-- Search Bars -->
      <div class="card shadow-sm checkout-card">

        <!-- Cart List -->
        <div class="card-body p-0" style="max-height:300px;overflow-y:auto;">
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="searchByName" class="form-control" placeholder="Search products by name...">
                        <span class="input-group-text bg-primary text-white"><i class="fas fa-search"></i></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="searchByBarcode" class="form-control" placeholder="Scan / Enter barcode...">
                        <span class="input-group-text bg-success text-white"><i class="fas fa-barcode"></i></span>
                    </div>
                </div>
            </div>
            <table class="table mb-0">
                <thead class="bg-light">
                <tr>
                    <th>Item</th>
                    <th width="80">Qty</th>
                    <th width="80">Price</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="cartTable">
                <!-- Items will be dynamically added -->
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="card-footer">
          <div class="d-flex justify-content-between">
            <span>Sub Total</span>
            <span id="subtotal">$0.00</span>
          </div>
          <div class="d-flex justify-content-between">
            <span>Tax (1.5%)</span>
            <span id="tax">$0.00</span>
          </div>
          <div class="d-flex justify-content-between">
            <span>Discount (%)</span>
            <input type="number" id="discount" value="0" class="form-control w-25 text-end">
          </div>
          <hr>
          <div class="d-flex justify-content-between fw-bold fs-5">
            <span>Total</span>
            <span id="total">$0.00</span>
          </div>
    
          <!-- Action Buttons -->
          <div class="mt-3 d-flex justify-content-between flex-wrap gap-2">
            <button class="btn btn-outline-danger">Cancel</button>
            <button class="btn btn-outline-warning">Hold</button>
            <button class="btn btn-outline-secondary">Refresh</button>
            <button class="btn btn-primary fw-bold">Pay</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
  .product-card { border-radius:12px; transition:0.2s; }
  .product-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); transform:scale(1.02); }
  .checkout-card { border-radius:12px; }
  .table th, .table td { font-size:14px; vertical-align:middle; }
</style>

<script>
  // Add product to cart (demo)
  function addToCart(id) {
    let row = `
      <tr id="row-${id}">
        <td>Item ${id}</td>
        <td>
          <input type="number" value="1" min="1" class="form-control form-control-sm qty-input" style="width:60px;">
        </td>
        <td>$10.00</td>
        <td><button class="btn btn-sm btn-danger" onclick="removeItem('${id}')">&times;</button></td>
      </tr>`;
    document.querySelector("#cartTable").insertAdjacentHTML("beforeend", row);
  }

  // Remove item
  function removeItem(id) {
    document.getElementById("row-" + id).remove();
  }

  // Search by name
  document.getElementById("searchByName").addEventListener("keyup", function() {
    let q = this.value.toLowerCase();
    document.querySelectorAll(".product-card-wrapper").forEach(c => {
      let n = c.getAttribute("data-name");
      c.style.display = n.includes(q) ? "block" : "none";
    });
  });
</script>
@endsection
