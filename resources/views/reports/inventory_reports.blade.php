@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<div class="tabs">
    {{-- NAV TABS --}}
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
                                {{-- Main Product --}}
                                <option value="{{ $item->id }}" {{ request('item_id')==$item->id?'selected':'' }}>
                                    {{ $item->name }}
                                </option>
                                {{-- Variations --}}
                                @foreach($item->variations as $var)
                                    <option value="{{ $item->id }}-{{ $var->id }}" 
                                        {{ request('item_id') == $item->id.'-'.$var->id ? 'selected':'' }}>
                                        {{ $item->name }} ({{ $var->sku }})
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From</label>
                        <input type="date" name="from_date" class="form-control" 
                               value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To</label>
                        <input type="date" name="to_date" class="form-control" 
                               value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Product</th>
                        <th>Variation</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @php $balance = 0; @endphp
                    @forelse($itemLedger as $tx)
                        @php $balance += $tx['qty_in'] - $tx['qty_out']; @endphp
                        <tr>
                            <td>{{ $tx['date'] }}</td>
                            <td>{{ $tx['type'] }}</td>
                            <td>{{ $tx['description'] }}</td>
                            <td>{{ $tx['product'] }}</td>
                            <td>{{ $tx['variation'] ?? '-' }}</td>
                            <td>{{ $tx['qty_in'] }}</td>
                            <td>{{ $tx['qty_out'] }}</td>
                            <td>{{ $balance }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">No records found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- STOCK INHAND --}}
        <div id="SR" class="tab-pane fade {{ $tab=='SR'?'show active':'' }}">
            @php
                $grandTotal = collect($stockInHand)->sum('total');
                $grandQty   = collect($stockInHand)->sum('quantity');
            @endphp

            <div class="mb-3 text-end">
                <h3 class="card-title">Total Stock Value: <span class="text-danger">{{ number_format($grandTotal, 2) }}</span></h3>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Variation</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockInHand as $stock)
                        <tr>
                            <td>{{ $stock['product'] }}</td>
                            <td>{{ $stock['variation'] ?? '-' }}</td>
                            <td>{{ $stock['quantity'] }}</td>
                            <td>{{ number_format($stock['price'], 2) }}</td>
                            <td>{{ number_format($stock['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No stock data found.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($stockInHand))
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-end">Grand Total</th>
                        <th>{{ $grandQty }}</th>
                        <th>-</th>
                        <th>{{ number_format($grandTotal, 2) }}</th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- STOCK TRANSFER --}}
        <div id="STR" class="tab-pane fade {{ $tab=='STR'?'show active':'' }}">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Product</th>
                        <th>Variation</th>
                        <th>From Location</th>
                        <th>To Location</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockTransfers as $st)
                        <tr>
                            <td>{{ $st['date'] }}</td>
                            <td>{{ $st['reference'] }}</td>
                            <td>{{ $st['product'] }}</td>
                            <td>{{ $st['variation'] ?? '-' }}</td>
                            <td>{{ $st['from'] }}</td>
                            <td>{{ $st['to'] }}</td>
                            <td>{{ $st['quantity'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No stock transfer data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- NON-MOVING --}}
        <div id="NMI" class="tab-pane fade {{ $tab=='NMI'?'show active':'' }}">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr><th>Item</th><th>Last Transaction Date</th><th>Days Since Last Movement</th></tr>
                </thead>
                <tbody>
                    @forelse($nonMovingItems as $nmi)
                        <tr>
                            <td>{{ $nmi['product'] }} {{ $nmi['variation'] ? '('.$nmi['variation'].')':'' }}</td>
                            <td>{{ $nmi['last_date'] }}</td>
                            <td>{{ $nmi['days_inactive'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center">No non-moving items found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- REORDER --}}
        <div id="ROL" class="tab-pane fade {{ $tab=='ROL'?'show active':'' }}">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr><th>Item</th><th>Stock Inhand</th><th>Reorder Level</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($reorderLevel as $rl)
                        <tr>
                            <td>{{ $rl['product'] }} {{ $rl['variation'] ? '('.$rl['variation'].')':'' }}</td>
                            <td>{{ $rl['stock_inhand'] }}</td>
                            <td>{{ $rl['reorder_level'] }}</td>
                            <td>
                                @if($rl['stock_inhand'] <= $rl['reorder_level'])
                                    <span class="badge bg-danger">Below Reorder</span>
                                @else
                                    <span class="badge bg-success">OK</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center">No data found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
