@extends('layouts.app')

@section('title', 'Purchases | All Purchases')

@section('content')
  <div class="row">
    <div class="col">
      <section class="card">
        @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @elseif (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif
        <header class="card-header">
            <div style="display: flex;justify-content: space-between;">
                <h2 class="card-title">All Purchases</h2>
                <div>
                  <a href="{{ route('production.receiving') }}" class="btn btn-success"><i class="fas fa-download"></i> Receiving </a>
                 <a href="{{ route('production.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Purchases </a>
                </div>
            </div>
        </header>

        <div class="card-body">
            <!-- <div class="col-md-12 text-end mb-3">
                <button class="btn btn-success">Bulk Action</button>
            </div> -->
            <!-- <form method="GET" action="" class="mb-3">
                <div class="row">
                    <div class="col-md-3">
                        <label for="date">Filter by Date:</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ request('date', date('Y-m-d')) }}">
                    </div>

                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form> -->
            <div class="modal-wrapper table-scroll" style="overflow-x: auto;">
                <table class="table table-bordered table-striped mb-0" id="cust-datatable-default">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Att.</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td width="10%">10056</td>
                            <td>12-12-2025</td>
                            <td width="10%">Bags</td>
                            <td width="10%">Ahmed</td>
                            <td></td>

                           
                            <td>
                            <a href="" class="text-success">
                                <i class="fa fa-print"></i>
                            </a>
                            <a href="{{ route('production.receiving') }}" class="text-primary">
                                <i class="fa fa-download"></i>
                            </a>
                            <a href="" class="text-warning">
                                <i class="fa fa-edit"></i>
                            </a>
                            <!-- Delete Link (with Confirmation) -->
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this purchase order?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger" style="background:none;border:none">
                                <i class="fa fa-trash"></i>
                                </button>
                            </form>
                            </td>
                        </tr>
                      
                    </tbody>
                </table>
            </div>
        </div>
      </section>

    </div>
  </div>
  <script>
    $(document).ready(function(){
        var table = $('#cust-datatable-default').DataTable(
            {
                "pageLength": 100,  // Show all rows
            }
        );

        // $('#bulk-complete').on('click', function () {
        //     const ids = $('.task-checkbox:checked').map(function () {
        //         return this.value;
        //     }).get();

        //     if (!ids.length) {
        //         alert('Select tasks first.');
        //         return;
        //     }

        //     $.ajax({
        //         url: "",
        //         type: 'POST',
        //         data: {
        //             _token: '{{ csrf_token() }}',
        //             task_ids: ids
        //         },
        //         success: function () {
        //             location.reload();
        //         }
        //     });
        // });

        // $('#bulk-delete-tasks').on('click', function () {
        //     const ids = $('.task-checkbox:checked').map(function () {
        //         return this.value;
        //     }).get();

        //     if (!ids.length || !confirm('Are you sure you want to delete selected tasks?')) {
        //         return;
        //     }

        //     $.ajax({
        //         url: "",
        //         type: 'POST',
        //         data: {
        //             _token: '{{ csrf_token() }}',
        //             task_ids: ids
        //         },
        //         success: function () {
        //             location.reload();
        //         }
        //     });
        // });
    });

    $('#select-all-tasks').on('click', function () {
        $('.task-checkbox').prop('checked', this.checked);
    });

  </script>
@endsection