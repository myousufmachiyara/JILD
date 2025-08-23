@extends('layouts.app')

@section('title', 'Party Ledger')

@section('content')
<div class="card">
    <div class="card-header">
        <h3>Party Ledger</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.party_ledger') }}" class="mb-4 row g-3">
            <div class="col-md-4">
                <label>Account</label>
                <select name="vendor_id" class="form-control" required>
                    <option value="">Select Account</option>
                    @foreach($account as $coa)
                        <option value="{{ $coa->id }}" {{ $vendorId == $coa->id ? 'selected' : '' }}>
                            {{ $coa->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label>From Date</label>
                <input type="date" name="from_date" value="{{ $from }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label>To Date</label>
                <input type="date" name="to_date" value="{{ $to }}" class="form-control" required>
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
                        <th>Details</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @php $balance = 0; @endphp
                    @foreach($ledger as $row)
                        @php
                            $balance += ($row['credit'] - $row['debit']);
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
                            <td>
                                <a href="{{ route($row['module'] . '.print', $row['id']) }}" target="_blank">
                                    Show Details
                                </a>
                            </td>                            
                            <td>{{ $row['type'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="text-end">{{ number_format($row['debit'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['credit'], 2) }}</td>
                            <td class="text-end">{{ number_format($balance, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif(request()->has('vendor_id'))
            <div class="alert alert-warning">No records found for the selected filters.</div>
        @endif
    </div>
</div>
@endsection
