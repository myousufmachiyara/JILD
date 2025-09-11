@extends('layouts.app')

@section('title', 'Accounts Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab==='GL'?'active':'' }}" data-bs-toggle="tab" data-bs-target="#GL">General Ledger</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='TB'?'active':'' }}" data-bs-toggle="tab" data-bs-target="#TB">Trial Balance</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='PL'?'active':'' }}" data-bs-toggle="tab" data-bs-target="#PL">Profit & Loss</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab==='BS'?'active':'' }}" data-bs-toggle="tab" data-bs-target="#BS">Balance Sheet</a></li>
    </ul>

    <div class="tab-content mt-3">
        {{-- GENERAL LEDGER --}}
        <div id="GL" class="tab-pane fade {{ $tab==='GL'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.accounts') }}" class="row g-3 mb-3">
                <input type="hidden" name="tab" value="GL">
                <div class="col-md-4">
                    <label>Account</label>
                    <select name="account_id" class="form-control" required>
                        <option value="">-- Select Account --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ request('account_id')==$acc->id?'selected':'' }}>{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Filter</button></div>
            </form>

            <table class="table table-bordered">
                <thead><tr><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
                <tbody>
                    @forelse($generalLedger as $tx)
                        <tr>
                            <td>{{ $tx->date }}</td>
                            <td>{{ $tx->description }}</td>
                            <td>{{ number_format($tx->debit,2) }}</td>
                            <td>{{ number_format($tx->credit,2) }}</td>
                            <td>{{ number_format($tx->balance,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No transactions</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- TRIAL BALANCE --}}
        <div id="TB" class="tab-pane fade {{ $tab==='TB'?'show active':'' }}">
            <table class="table table-bordered">
                <thead><tr><th>Account</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
                <tbody>
                    @forelse($trialBalance as $row)
                        <tr>
                            <td>{{ $row->account }}</td>
                            <td>{{ number_format($row->debit,2) }}</td>
                            <td>{{ number_format($row->credit,2) }}</td>
                            <td>{{ number_format($row->balance,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PROFIT & LOSS --}}
        <div id="PL" class="tab-pane fade {{ $tab==='PL'?'show active':'' }}">
            <h5>Profit & Loss ({{ $from }} - {{ $to }})</h5>
            <table class="table table-bordered w-50">
                <tr><th>Revenue</th><td>{{ number_format($plAccounts['revenue'] ?? 0,2) }}</td></tr>
                <tr><th>Expenses</th><td>{{ number_format($plAccounts['expenses'] ?? 0,2) }}</td></tr>
                <tr><th>Net Profit / (Loss)</th><td><strong>{{ number_format($plAccounts['net'] ?? 0,2) }}</strong></td></tr>
            </table>
        </div>

        {{-- BALANCE SHEET --}}
        <div id="BS" class="tab-pane fade {{ $tab==='BS'?'show active':'' }}">
            <h5>Balance Sheet ({{ $from }} - {{ $to }})</h5>
            <div class="row">
                <div class="col-md-4">
                    <h6>Assets</h6>
                    <ul class="list-group">
                        @foreach($balanceSheet['assets'] ?? [] as $acc)
                            <li class="list-group-item d-flex justify-content-between">
                                {{ $acc->name }}
                                <span>{{ number_format($acc->transactions->sum('debit') - $acc->transactions->sum('credit'),2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6>Liabilities</h6>
                    <ul class="list-group">
                        @foreach($balanceSheet['liabilities'] ?? [] as $acc)
                            <li class="list-group-item d-flex justify-content-between">
                                {{ $acc->name }}
                                <span>{{ number_format($acc->transactions->sum('credit') - $acc->transactions->sum('debit'),2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6>Equity</h6>
                    <ul class="list-group">
                        @foreach($balanceSheet['equity'] ?? [] as $acc)
                            <li class="list-group-item d-flex justify-content-between">
                                {{ $acc->name }}
                                <span>{{ number_format($acc->transactions->sum('credit') - $acc->transactions->sum('debit'),2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
