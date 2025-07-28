@extends('layouts.app')

@section('title', 'Item Ledger')

@section('content')
<div class="card">
    <div class="card-header">
        <h3>Item Ledger</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.item_ledger') }}" class="mb-4 row g-3">
            <div class="col-md-4">
                <label for="item_id">Select Item</label>
                <select class="form-control" id="item_id" name="item_id" required>
                    <option value="">-- Select Item --</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" {{ $itemId == $item->id ? 'selected' : '' }}>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="from_date">From Date</label>
                <input type="date" class="form-control" name="from_date" value="{{ $from }}" required>
            </div>
            <div class="col-md-3">
                <label for="to_date">To Date</label>
                <input type="date" class="form-control" name="to_date" value="{{ $to }}" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>

        @if($ledger && count($ledger))
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @php $balance = 0; @endphp
                    @foreach($ledger as $row)
                        @php
                            $balance += ($row->qty_in - $row->qty_out);
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                            <td>{{ $row->type }}</td>
                            <td>{{ $row->description }}</td>
                            <td class="text-success text-end">{{ $row->qty_in ?: '-' }}</td>
                            <td class="text-danger text-end">{{ $row->qty_out ?: '-' }}</td>
                            <td class="text-end"><strong>{{ $balance }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif(request()->has('item_id'))
            <div class="alert alert-warning">No records found for the selected item and date range.</div>
        @endif
    </div>
</div>
@endsection
