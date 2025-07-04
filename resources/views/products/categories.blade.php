@extends('layouts.app')

@section('title', 'Products | Categories')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header">
        <div style="display: flex; justify-content: space-between;">
          <h2 class="card-title">All Categories</h2>
          <div>
            <button type="button" class="modal-with-form btn btn-primary" href="#addCategoryModal">
              <i class="fas fa-plus"></i> Add Category
            </button>
          </div>
        </div>
        @if ($errors->has('error'))
        <strong class="text-danger">{{ $errors->first('error') }}</strong>
        @endif
      </header>
      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-categories">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($categories as $category)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $category->name }}</td>
                <td>
                  <button class="btn btn-warning btn-sm modal-with-form" href="#editCategoryModal{{ $category->id }}">Edit</button>
                  <form action="{{ route('product-categories.destroy', $category->id) }}" method="POST" style="display:inline-block;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                  </form>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div id="editCategoryModal{{ $category->id }}" class="modal-block modal-block-warning mfp-hide">
                <section class="card">
                  <form method="post" action="{{ route('product-categories.update', $category->id) }}">
                    @csrf
                    @method('PUT')
                    <header class="card-header">
                      <h2 class="card-title">Edit Category</h2>
                    </header>
                    <div class="card-body">
                      <div class="form-group mb-3">
                        <label>Name</label>
                        <input type="text" class="form-control" name="name" value="{{ $category->name }}" required>
                      </div>
                    </div>
                    <footer class="card-footer">
                      <div class="row">
                        <div class="col-md-12 text-end">
                          <button type="submit" class="btn btn-warning">Update</button>
                          <button class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                      </div>
                    </footer>
                  </form>
                </section>
              </div>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Add Modal -->
    <div id="addCategoryModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="post" action="{{ route('product-categories.store') }}">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Category</h2>
          </header>
          <div class="card-body">
            <div class="form-group mb-3">
              <label>Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" required>
            </div>
          </div>
          <footer class="card-footer">
            <div class="row">
              <div class="col-md-12 text-end">
                <button type="submit" class="btn btn-primary">Create</button>
                <button class="btn btn-default modal-dismiss">Cancel</button>
              </div>
            </div>
          </footer>
        </form>
      </section>
    </div>
  </div>
</div>
@endsection