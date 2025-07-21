@extends('layouts.app')

@section('title', 'Product | All Product')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @elseif (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <header class="card-header">
        <div style="display: flex;justify-content: space-between;">
          <h2 class="card-title">All Products</h2>
          <div>
            <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Products</a>
          </div>
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="cust-datatable-default">
            <thead>
              <tr>
                <th><input type="checkbox" id="select-all-tasks"></th>
                <th>S.No</th>
                <th>Image</th>
                <th>Item Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>M. Cost</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($products as $index => $product)
              <tr>
                <td><input type="checkbox" class="task-checkbox" value="{{ $product->id }}"></td>
                <td>{{ $index + 1 }}</td>
                <td>
                  @if($product->images->first())
                    <img src="{{ asset('storage/' . $product->images->first()->image_path) }}" width="60" height="60" style="object-fit:cover;border-radius:5px;">
                  @else
                    <span class="text-muted">No Image</span>
                  @endif
                </td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->category->name ?? '-' }}</td>
                <td>{{ number_format($product->manufacturing_cost, 2) }}</td>
                <td>
                  <a href="{{ route('products.edit', $product->id) }}" class="text-primary"><i class="fa fa-pen"></i></a>
                  <form method="POST" action="{{ route('products.destroy', $product->id) }}" style="display:inline-block">
                    @csrf
                    @method('DELETE')
                    <button class="text-danger" style="border:none" onclick="return confirm('Delete this product?')"><i class="fa fa-times"></i></button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('#cust-datatable-default').DataTable({
      "pageLength": 100
    });

    $('#select-all-tasks').on('click', function () {
      $('.task-checkbox').prop('checked', this.checked);
    });
  });
</script>
@endsection
