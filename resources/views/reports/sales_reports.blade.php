@extends('layouts.app')

@section('title', 'Sales Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab==='SR'?'active':'' }}" data-bs-toggle="tab" data-bs-target="#SR">Sales Register</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab==='SRET'?'active':'' }}" data-bs-toggle="tab" data-bs-target="#SRET">Sales Return</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab==='CW'?'active':'' }}" data-bs-toggle="tab" data-bs-target="#CW">Customer Wise</a>
        </li>
    </ul>

    <div class="tab-content">
        {{-- SALES REGISTER --}}
        <div id="SR" class="tab-pane fade {{ $tab==='SR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mt-3">
                <input type="hidden" name="tab" value="SR">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="from_date" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="to_date" value="{{ $to }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            <div class="mt-3">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales as $row)
                            <tr>
                                <td>{{ $row->date }}</td>
                                <td>{{ $row->invoice }}</td>
                                <td>{{ $row->customer }}</td>
                                <td>{{ number_format($row->total,2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SALES RETURN --}}
        <div id="SRET" class="tab-pane fade {{ $tab==='SRET'?'show active':'' }}">
            <h5 class="mt-3">Sales Return Report</h5>
            <table class="table table-bordered">
                <thead>
                    <tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Total Return</th></tr>
                </thead>
                <tbody>
                    @forelse($returns as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->invoice }}</td>
                            <td>{{ $row->customer }}</td>
                            <td>{{ number_format($row->total,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No Data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- CUSTOMER WISE --}}
        <div id="CW" class="tab-pane fade {{ $tab==='CW'?'show active':'' }}">
            <h5 class="mt-3">Customer Wise Sales</h5>
            <table class="table table-bordered">
                <thead>
                    <tr><th>Customer</th><th>No. of Invoices</th><th>Total Amount</th></tr>
                </thead>
                <tbody>
                    @foreach($customerWise as $row)
                        <tr>
                            <td>{{ $row->customer }}</td>
                            <td>{{ $row->count }}</td>
                            <td>{{ number_format($row->total,2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
