@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<div class="tabs">

  <ul class="nav nav-tabs flex-wrap">
    <li class="nav-item">
      <a class="nav-link {{ $tab=='IL'?'active':'' }}"
         href="{{ route('reports.inventory') }}?tab=IL&from_date={{ $from }}&to_date={{ $to }}">
        <i class="fas fa-list me-1"></i> Item Ledger
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='SR'?'active':'' }}"
         href="{{ route('reports.inventory') }}?tab=SR">
        <i class="fas fa-boxes me-1"></i> Stock In Hand
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='STR'?'active':'' }}"
         href="{{ route('reports.inventory') }}?tab=STR&from_date={{ $from }}&to_date={{ $to }}">
        <i class="fas fa-exchange-alt me-1"></i> Stock Transfer
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='NMI'?'active':'' }}"
         href="{{ route('reports.inventory') }}?tab=NMI">
        <i class="fas fa-clock me-1"></i> Non-Moving Items
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='ROL'?'active':'' }}"
         href="{{ route('reports.inventory') }}?tab=ROL">
        <i class="fas fa-exclamation-triangle me-1"></i> Reorder Level
      </a>
    </li>
  </ul>

  <div class="tab-content mt-3">

    {{-- ── 1. ITEM LEDGER ───────────────────────────────────────── --}}
    @if($tab === 'IL')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="IL">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label>Product / Variation</label>
            <select name="item_id" class="form-control select2-js">
              <option value="">-- Select a product --</option>
              @foreach($products as $product)
                <option value="{{ $product->id }}"
                  {{ request('item_id') == $product->id ? 'selected' : '' }}>
                  {{ $product->name }}
                </option>
                @foreach($product->variations as $var)
                  <option value="{{ $product->id }}-{{ $var->id }}"
                    {{ request('item_id') == $product->id.'-'.$var->id ? 'selected' : '' }}>
                    &nbsp;&nbsp;↳ {{ $product->name }} ({{ $var->sku }})
                  </option>
                @endforeach
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label>From</label>
            <input type="date" name="from_date" value="{{ request('from_date', $from) }}" class="form-control">
          </div>
          <div class="col-md-2">
            <label>To</label>
            <input type="date" name="to_date" value="{{ request('to_date', $to) }}" class="form-control">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      @if($itemLedger->count())
        @php
          $totalIn  = $itemLedger->sum('qty_in');
          $totalOut = $itemLedger->sum('qty_out');
          $balance  = $totalIn - $totalOut;
        @endphp

        <div class="row mb-3">
          <div class="col">
            {{-- Movement legend --}}
            <div class="d-flex flex-wrap gap-2">
              <span class="badge bg-success">Purchase → IN</span>
              <span class="badge bg-warning text-dark">Purchase Return → OUT</span>
              <span class="badge bg-danger">Sale → OUT</span>
              <span class="badge bg-info text-dark">Sale Return → IN</span>
              <span class="badge bg-secondary">Production Order → raw OUT</span>
              <span class="badge bg-primary">Production Receiving → FG IN</span>
              <span class="badge" style="background:#dc3545">Production Return → FG OUT</span>
              <span class="badge" style="background:#198754">Wastage Return → raw IN</span>
            </div>
          </div>
          <div class="col-auto text-end">
            <span class="me-3">Total In: <strong class="text-success">{{ number_format($totalIn, 2) }}</strong></span>
            <span class="me-3">Total Out: <strong class="text-danger">{{ number_format($totalOut, 2) }}</strong></span>
            <span>Balance: <strong class="text-primary">{{ number_format($balance, 2) }}</strong></span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm" id="ilTable">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th>Product</th>
                <th>Variation</th>
                <th class="text-end">Qty In</th>
                <th class="text-end">Qty Out</th>
                <th class="text-end">Rate</th>
              </tr>
            </thead>
            <tbody>
              @foreach($itemLedger as $row)
                @php
                  $typeMap = [
                    'Purchase'             => ['bg-success',          'IN'],
                    'Purchase Return'      => ['bg-warning text-dark','OUT'],
                    'Sale'                 => ['bg-danger',           'OUT'],
                    'Sale Return'          => ['bg-info text-dark',   'IN'],
                    'Production Order'     => ['bg-secondary',        'raw OUT'],
                    'Production Receiving' => ['bg-primary',          'FG IN'],
                    'Production Return'    => ['bg-danger',           'FG OUT'],
                    'Wastage Return'       => ['bg-success',          'raw IN'],
                  ];
                  $badge   = $typeMap[$row['type']] ?? ['bg-secondary', ''];
                  $rowClass = in_array($row['type'], ['Production Order', 'Sale', 'Purchase Return', 'Production Return'])
                    ? 'table-danger' : '';
                  $rowClass = in_array($row['type'], ['Purchase', 'Sale Return', 'Production Receiving', 'Wastage Return'])
                    ? '' : $rowClass;
                @endphp
                <tr>
                  <td>{{ \Carbon\Carbon::parse($row['date'])->format('d-M-Y') }}</td>
                  <td>
                    <span class="badge {{ $badge[0] }}">{{ $row['type'] }}</span>
                    <small class="text-muted">({{ $badge[1] }})</small>
                  </td>
                  <td><small>{{ $row['description'] }}</small></td>
                  <td>{{ $row['product'] }}</td>
                  <td>{{ $row['variation'] ?? '—' }}</td>
                  <td class="text-end text-success fw-bold">
                    {{ $row['qty_in'] > 0 ? number_format($row['qty_in'], 2) : '—' }}
                  </td>
                  <td class="text-end text-danger fw-bold">
                    {{ $row['qty_out'] > 0 ? number_format($row['qty_out'], 2) : '—' }}
                  </td>
                  <td class="text-end">{{ $row['rate'] > 0 ? number_format($row['rate'], 2) : '—' }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="5" class="text-end">Totals</td>
                <td class="text-end text-success">{{ number_format($totalIn, 2) }}</td>
                <td class="text-end text-danger">{{ number_format($totalOut, 2) }}</td>
                <td></td>
              </tr>
              <tr>
                <td colspan="5" class="text-end">Closing Balance</td>
                <td colspan="3" class="text-primary">{{ number_format($balance, 2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      @else
        <div class="alert alert-info">
          <i class="fas fa-info-circle me-1"></i>
          Select a product above and click Filter to view the item ledger.
        </div>
      @endif
    @endif

    {{-- ── 2. STOCK IN HAND ────────────────────────────────────── --}}
    @if($tab === 'SR')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="SR">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label>Product / Variation</label>
            <select name="item_id" class="form-control select2-js">
              <option value="">-- All Products --</option>
              @foreach($products as $product)
                <option value="{{ $product->id }}"
                  {{ request('item_id') == $product->id ? 'selected' : '' }}>
                  {{ $product->name }}
                </option>
                @foreach($product->variations as $var)
                  <option value="{{ $product->id }}-{{ $var->id }}"
                    {{ request('item_id') == $product->id.'-'.$var->id ? 'selected' : '' }}>
                    &nbsp;&nbsp;↳ {{ $product->name }} ({{ $var->sku }})
                  </option>
                @endforeach
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label>Costing Method</label>
            <select name="costing_method" class="form-control select2-js">
              <option value="avg"    {{ request('costing_method','avg') == 'avg'    ? 'selected':'' }}>Average Rate</option>
              <option value="max"    {{ request('costing_method','avg') == 'max'    ? 'selected':'' }}>Max Rate</option>
              <option value="min"    {{ request('costing_method','avg') == 'min'    ? 'selected':'' }}>Min Rate</option>
              <option value="latest" {{ request('costing_method','avg') == 'latest' ? 'selected':'' }}>Latest Rate</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle me-1"></i>
        Stock qty = Opening + Purchased + Sale Returns + FG Received + Wastage Returned (raw back)
        − Sold − Purchase Returns − Raw Issued to Production − FG Returned (defective)
      </div>

      @php
        $grandTotal = $stockInHand->sum('total');
        $grandQty   = $stockInHand->sum('quantity');
      @endphp

      <div class="row mb-3">
        <div class="col text-end">
          <span class="me-3">Total Qty: <strong>{{ number_format($grandQty, 2) }}</strong></span>
          <span>Total Value: <strong class="text-danger fs-5">PKR {{ number_format($grandTotal, 0) }}</strong></span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="srTable">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>Variation</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Raw Cost/pc</th>
              <th class="text-end">Mfg Cost/pc</th>
              <th class="text-end">Total Cost/pc</th>
              <th class="text-end">Total Value</th>
            </tr>
          </thead>
          <tbody>
            @forelse($stockInHand as $stock)
              <tr class="{{ $stock['quantity'] < 0 ? 'table-danger' : '' }}">
                <td>{{ $stock['product'] }}</td>
                <td>{{ $stock['variation'] ?? '—' }}</td>
                <td class="text-end {{ $stock['quantity'] < 0 ? 'text-danger fw-bold' : '' }}">
                  {{ number_format($stock['quantity'], 2) }}
                </td>
                <td class="text-end">{{ number_format($stock['raw_cost'], 2) }}</td>
                <td class="text-end">{{ number_format($stock['mfg_cost'], 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($stock['price'], 2) }}</td>
                <td class="text-end">{{ number_format($stock['total'], 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No stock data found.</td></tr>
            @endforelse
          </tbody>
          @if($stockInHand->count())
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="2" class="text-end">Grand Total</td>
                <td class="text-end">{{ number_format($grandQty, 2) }}</td>
                <td colspan="3" class="text-end">—</td>
                <td class="text-end text-danger">{{ number_format($grandTotal, 2) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    @endif

    {{-- ── 3. STOCK TRANSFER ───────────────────────────────────── --}}
    @if($tab === 'STR')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="STR">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label>From Location</label>
            <select name="from_location_id" class="form-control select2-js">
              <option value="">-- All --</option>
              @foreach($locations as $loc)
                <option value="{{ $loc->id }}" {{ request('from_location_id') == $loc->id ? 'selected' : '' }}>
                  {{ $loc->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label>To Location</label>
            <select name="to_location_id" class="form-control select2-js">
              <option value="">-- All --</option>
              @foreach($locations as $loc)
                <option value="{{ $loc->id }}" {{ request('to_location_id') == $loc->id ? 'selected' : '' }}>
                  {{ $loc->name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label>From</label>
            <input type="date" name="from_date" value="{{ request('from_date', $from) }}" class="form-control">
          </div>
          <div class="col-md-2">
            <label>To</label>
            <input type="date" name="to_date" value="{{ request('to_date', $to) }}" class="form-control">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="strTable">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Reference</th>
              <th>Product</th>
              <th>Variation</th>
              <th>From</th>
              <th>To</th>
              <th class="text-end">Qty</th>
            </tr>
          </thead>
          <tbody>
            @forelse($stockTransfers as $st)
              <tr>
                <td>{{ \Carbon\Carbon::parse($st['date'])->format('d-M-Y') }}</td>
                <td>
                  <a href="{{ url('/stock_transfer/'.$st['reference'].'/print') }}" target="_blank">
                    ST#{{ $st['reference'] }}
                  </a>
                </td>
                <td>{{ $st['product'] }}</td>
                <td>{{ $st['variation'] ?? '—' }}</td>
                <td>{{ $st['from'] }}</td>
                <td>{{ $st['to'] }}</td>
                <td class="text-end">{{ number_format($st['quantity'], 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No stock transfers found.</td></tr>
            @endforelse
          </tbody>
          @if(count($stockTransfers))
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="6" class="text-end">Total Transferred</td>
                <td class="text-end">{{ number_format(collect($stockTransfers)->sum('quantity'), 2) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    @endif

    {{-- ── 4. NON-MOVING ITEMS ─────────────────────────────────── --}}
    @if($tab === 'NMI')
      <form method="GET" action="{{ route('reports.inventory') }}" class="mb-3">
        <input type="hidden" name="tab" value="NMI">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label>No movement in last (months)</label>
            <input type="number" name="months" value="{{ request('months', 3) }}" min="1" max="60" class="form-control">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div class="alert alert-warning mb-3">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Items with stock on hand but no movement (purchase, sale, production) in the last
        <strong>{{ request('months', 3) }} months</strong>.
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="nmiTable">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>Variation</th>
              <th class="text-end">Stock Qty</th>
              <th>Last Movement</th>
              <th class="text-end">Days Inactive</th>
            </tr>
          </thead>
          <tbody>
            @forelse($nonMovingItems as $nmi)
              @php $critical = is_numeric($nmi['days_inactive']) && $nmi['days_inactive'] > 180; @endphp
              <tr class="{{ $critical ? 'table-danger' : 'table-warning' }}">
                <td>{{ $nmi['product'] }}</td>
                <td>{{ $nmi['variation'] ?? '—' }}</td>
                <td class="text-end">{{ number_format($nmi['stock_qty'], 2) }}</td>
                <td>
                  @if($nmi['last_date'] === 'Never')
                    <span class="badge bg-danger">Never Moved</span>
                  @else
                    {{ \Carbon\Carbon::parse($nmi['last_date'])->format('d-M-Y') }}
                  @endif
                </td>
                <td class="text-end fw-bold">
                  {{ is_numeric($nmi['days_inactive']) ? $nmi['days_inactive'].' days' : '∞' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-success py-3">
                  <i class="fas fa-check-circle me-1"></i>
                  All items have recent movement.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif

    {{-- ── 5. REORDER LEVEL ────────────────────────────────────── --}}
    @if($tab === 'ROL')
      <div class="alert alert-danger mb-3">
        <i class="fas fa-exclamation-circle me-1"></i>
        Items where <strong>current stock ≤ reorder level</strong>.
        Only products with a reorder level configured are shown.
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm" id="rolTable">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th>Variation</th>
              <th class="text-end">Stock In Hand</th>
              <th class="text-end">Reorder Level</th>
              <th class="text-end">Shortage</th>
              <th class="text-end">Min Order Qty</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reorderLevel as $rl)
              <tr class="{{ $rl['stock_inhand'] <= 0 ? 'table-danger' : 'table-warning' }}">
                <td>{{ $rl['product'] }}</td>
                <td>{{ $rl['variation'] ?? '—' }}</td>
                <td class="text-end {{ $rl['stock_inhand'] <= 0 ? 'text-danger fw-bold' : '' }}">
                  {{ number_format($rl['stock_inhand'], 2) }}
                </td>
                <td class="text-end">{{ number_format($rl['reorder_level'], 2) }}</td>
                <td class="text-end text-danger fw-bold">{{ number_format($rl['shortage'], 2) }}</td>
                <td class="text-end">{{ number_format($rl['min_order_qty'], 2) }}</td>
                <td>
                  @if($rl['stock_inhand'] <= 0)
                    <span class="badge bg-danger">Out of Stock</span>
                  @else
                    <span class="badge bg-warning text-dark">Reorder Now</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-success py-3">
                  <i class="fas fa-check-circle me-1"></i>
                  All stock levels are above reorder points.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif

  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    $('#ilTable, #srTable, #strTable, #nmiTable, #rolTable').DataTable({
      pageLength: 100,
      order: [[0, 'asc']],
    });
  });
</script>
@endsection