@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab=='IL'?'active':'' }}" data-bs-toggle="tab" href="#IL">Item Ledger</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='SR'?'active':'' }}" data-bs-toggle="tab" href="#SR">Stock Inhand</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='STR'?'active':'' }}" data-bs-toggle="tab" href="#STR">Stock Transfer</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='NMI'?'active':'' }}" data-bs-toggle="tab" href="#NMI">Non-Moving Items</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='ROL'?'active':'' }}" data-bs-toggle="tab" href="#ROL">Reorder Level</a></li>
    </ul>

    <div class="tab-content mt-3">
        {{-- ITEM LEDGER --}}
        <div id="IL" class="tab-pane fade {{ $tab=='IL'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.inventory') }}">
                <input type="hidden" name="tab" value="IL">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label>Select Item</label>
                        <select name="item_id" class="form-control" required>
                            <option value="">-- Select Item --</option>
                            @foreach($products as $item)
                                <option value="{{ $item->id }}" {{ request('item_id')==$item->id?'selected':'' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Description</th><th>Qty In</th><th>Qty Out</th><th>Balance</th></tr>
                </thead>
                <tbody>
                    @forelse($itemLedger as $tx)
                        <tr>
                            <td>{{ $tx->date }}</td>
                            <td>{{ $tx->type }}</td>
                            <td>{{ $tx->description }}</td>
                            <td>{{ $tx->qty_in }}</td>
                            <td>{{ $tx->qty_out }}</td>
                            <td>{{ $tx->balance }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No transactions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- STOCK INHAND --}}
        <div id="SR" class="tab-pane fade {{ $tab=='SR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.inventory') }}">
                <input type="hidden" name="tab" value="SR">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label>Select Item</label>
                        <select name="item_id" class="form-control">
                            <option value="">-- All Items --</option>
                            @foreach($products as $item)
                                <option value="{{ $item->id }}" {{ request('item_id')==$item->id?'selected':'' }}>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead><tr><th>Item</th><th>Stock Inhand</th><th>Unit</th></tr></thead>
                <tbody>
                    @forelse($stockInHand as $stock)
                        <tr>
                            <td>{{ $stock->name }}</td>
                            <td>{{ $stock->quantity }}</td>
                            <td>{{ $stock->unit }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No stock data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- STOCK TRANSFER --}}
        <div id="STR" class="tab-pane fade {{ $tab=='STR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.inventory') }}">
                <input type="hidden" name="tab" value="STR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr><th>Date</th><th>From Location</th><th>To Location</th><th>Item</th><th>Quantity</th></tr>
                </thead>
                <tbody>
                    @forelse($stockTransfer as $transfer)
                        <tr>
                            <td>{{ $transfer->date }}</td>
                            <td>{{ $transfer->from_location }}</td>
                            <td>{{ $transfer->to_location }}</td>
                            <td>{{ $transfer->item_name }}</td>
                            <td>{{ $transfer->quantity }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No stock transfer found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- NON-MOVING ITEMS --}}
        <div id="NMI" class="tab-pane fade {{ $tab=='NMI'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.inventory') }}">
                <input type="hidden" name="tab" value="NMI">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr><th>Item</th><th>Last Transaction Date</th><th>Days Since Last Movement</th></tr>
                </thead>
                <tbody>
                    @forelse($nonMovingItems as $nmi)
                        <tr>
                            <td>{{ $nmi->name }}</td>
                            <td>{{ $nmi->last_date }}</td>
                            <td>{{ $nmi->days_inactive }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No non-moving items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- REORDER LEVEL --}}
        <div id="ROL" class="tab-pane fade {{ $tab=='ROL'?'show active':'' }}">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Item</th><th>Stock Inhand</th><th>Reorder Level</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($reorderLevel as $rl)
                        <tr>
                            <td>{{ $rl->name }}</td>
                            <td>{{ $rl->stock_inhand }}</td>
                            <td>{{ $rl->reorder_level }}</td>
                            <td>
                                @if($rl->stock_inhand <= $rl->reorder_level)
                                    <span class="badge bg-danger">Below Reorder</span>
                                @else
                                    <span class="badge bg-success">OK</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
