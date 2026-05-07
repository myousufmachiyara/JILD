@extends('layouts.app')
@section('title', 'Production Reports')

@section('content')
<div class="tabs">

  <ul class="nav nav-tabs flex-wrap">
    <li class="nav-item">
      <a class="nav-link {{ $tab=='RMI'?'active':'' }}" data-bs-toggle="tab" href="#RMI">
        <i class="fas fa-industry me-1"></i> Production Orders
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='PR'?'active':'' }}" data-bs-toggle="tab" href="#PR">
        <i class="fas fa-box-open me-1"></i> Production Receiving
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='CR'?'active':'' }}" data-bs-toggle="tab" href="#CR">
        <i class="fas fa-calculator me-1"></i> Product Costing
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='VRB'?'active':'' }}" data-bs-toggle="tab" href="#VRB">
        <i class="fas fa-warehouse me-1"></i> Vendor Raw Balance
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='RTN'?'active':'' }}" data-bs-toggle="tab" href="#RTN">
        <i class="fas fa-undo me-1"></i> Production Returns
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='DLV'?'active':'' }}" data-bs-toggle="tab" href="#DLV">
        <i class="fas fa-clock me-1"></i> Delivery Tracking
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $tab=='SUM'?'active':'' }}" data-bs-toggle="tab" href="#SUM">
        <i class="fas fa-chart-bar me-1"></i> Vendor Summary
      </a>
    </li>
  </ul>

  <div class="tab-content mt-3">

    {{-- ── 1. PRODUCTION ORDERS ──────────────────────────────────── --}}
    <div id="RMI" class="tab-pane fade {{ $tab=='RMI'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.production') }}">
        <input type="hidden" name="tab" value="RMI">
        @include('reports._filter', ['showVendor' => false])
      </form>

      @forelse($rawIssued as $order)
        <div class="card mb-3 {{ $order->consumption_details->where('alert', true)->count() ? 'border-danger' : '' }}">
          <div class="card-header d-flex justify-content-between align-items-center
                       {{ $order->consumption_details->where('alert', true)->count() ? 'bg-danger text-white' : 'bg-light' }}">
            <div>
              <strong>Production #{{ $order->id }}</strong>
              <span class="ms-3 badge bg-secondary">{{ $order->type }}</span>
              <span class="ms-2 text-light   small">{{ $order->date }}</span>
            </div>
            <div>
              <strong>{{ $order->vendor }}</strong>
              @if($order->consumption_details->where('alert', true)->count())
                <span class="badge bg-warning text-dark ms-2">⚠ High Consumption</span>
              @endif
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-2 text-center">
                <small class="text-muted d-block">Raw Given</small>
                <strong>{{ number_format($order->total_raw_given, 2) }}</strong>
              </div>
              <div class="col-md-2 text-center">
                <small class="text-muted d-block">Raw Cost</small>
                <strong>{{ number_format($order->total_raw_cost, 2) }}</strong>
              </div>
              <div class="col-md-2 text-center">
                <small class="text-muted d-block">FG Received</small>
                <strong class="text-success">{{ number_format($order->total_fg_received, 2) }}</strong>
              </div>
              <div class="col-md-2 text-center">
                <small class="text-muted d-block">Wastage Returned</small>
                <strong class="text-info">{{ number_format($order->wastage_returned, 2) }}</strong>
              </div>
              <div class="col-md-2 text-center">
                <small class="text-muted d-block">Raw at Vendor</small>
                <strong class="{{ $order->raw_at_vendor > 0 ? 'text-warning' : 'text-success' }}">
                  {{ number_format($order->raw_at_vendor, 2) }}
                </strong>
              </div>
            </div>

            {{-- Raw Material Breakdown --}}
            <h6 class="text-muted">Raw Material Issued</h6>
            <table class="table table-sm table-bordered mb-3">
              <thead class="table-light">
                <tr>
                  <th>Item</th><th>Qty</th><th>Rate</th><th>Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach($order->raw_details as $d)
                  <tr>
                    <td>{{ $d->product->name ?? '-' }}</td>
                    <td>{{ number_format($d->qty, 2) }}</td>
                    <td>{{ number_format($d->rate, 2) }}</td>
                    <td>{{ number_format($d->qty * $d->rate, 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            {{-- Per-Product Consumption --}}
            @if($order->consumption_details->count())
              <h6 class="text-muted">Product Consumption Analysis</h6>
              <table class="table table-sm table-bordered">
                <thead class="table-light">
                  <tr>
                    <th>Product</th>
                    <th class="text-end">FG Received</th>
                    <th class="text-end">Actual Consumption</th>
                    <th class="text-end">Expected Consumption</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($order->consumption_details as $con)
                    <tr class="{{ $con['alert'] ? 'table-danger' : '' }}">
                      <td>{{ $con['name'] }}</td>
                      <td class="text-end">{{ number_format($con['received_qty'], 2) }}</td>
                      <td class="text-end">{{ $con['actual_con'] }}</td>
                      <td class="text-end">{{ $con['expected_con'] ?: '-' }}</td>
                      <td>
                        @if($con['alert'])
                          <span class="badge bg-danger">⚠ High</span>
                        @elseif($con['expected_con'] > 0)
                          <span class="badge bg-success">Normal</span>
                        @else
                          <span class="badge bg-secondary">No Baseline</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @endif
          </div>
        </div>
      @empty
        <div class="text-center text-muted py-4">No production orders found.</div>
      @endforelse
    </div>

    {{-- ── 2. PRODUCTION RECEIVING ───────────────────────────────── --}}
    <div id="PR" class="tab-pane fade {{ $tab=='PR'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.production') }}">
        <input type="hidden" name="tab" value="PR">
        @include('reports._filter', ['showVendor' => false])
      </form>

      @php
        $prTotal = $produced->sum('total');
        $prQty   = $produced->sum('qty');
      @endphp
      <div class="row mb-2">
        <div class="col text-end">
          <span class="me-3">Total Qty: <strong>{{ number_format($prQty, 2) }}</strong></span>
          <span>Total Cost: <strong class="text-danger">{{ number_format($prTotal, 2) }}</strong></span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
          <thead class="table-light">
            <tr>
              <th>Date</th><th>GRN #</th><th>Vendor</th><th>Production #</th>
              <th>Item</th><th>Variation</th><th>Unit</th>
              <th class="text-end">Qty</th><th class="text-end">M.Cost</th><th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($produced as $row)
              <tr>
                <td>{{ $row->date }}</td>
                <td>{{ $row->grn_no }}</td>
                <td>{{ $row->vendor }}</td>
                <td>#{{ $row->production }}</td>
                <td>{{ $row->item_name }}</td>
                <td>{{ $row->variation !== '-' ? $row->variation : '' }}</td>
                <td>{{ $row->unit }}</td>
                <td class="text-end">{{ number_format($row->qty, 2) }}</td>
                <td class="text-end">{{ number_format($row->m_cost, 2) }}</td>
                <td class="text-end">{{ number_format($row->total, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="10" class="text-center text-muted">No production receiving found.</td></tr>
            @endforelse
          </tbody>
          @if($produced->count())
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="7" class="text-end">Total</td>
                <td class="text-end">{{ number_format($prQty, 2) }}</td>
                <td></td>
                <td class="text-end">{{ number_format($prTotal, 2) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ── 3. PRODUCT COSTING ────────────────────────────────────── --}}
    <div id="CR" class="tab-pane fade {{ $tab=='CR'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.production') }}">
        <input type="hidden" name="tab" value="CR">
        @include('reports._filter', ['showVendor' => false])
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
          <thead class="table-light">
            <tr>
              <th>Product</th>
              <th class="text-end">Total Qty Received</th>
              <th class="text-end">Avg Mfg Cost</th>
              <th class="text-end">Expected Mfg Cost</th>
              <th class="text-end">Variance</th>
              <th class="text-end">Total Cost</th>
            </tr>
          </thead>
          <tbody>
            @forelse($costings as $row)
              <tr class="{{ $row->variance > 0 ? 'table-warning' : '' }}">
                <td>{{ $row->product_name }}</td>
                <td class="text-end">{{ number_format($row->total_qty, 2) }}</td>
                <td class="text-end">{{ number_format($row->avg_cost, 2) }}</td>
                <td class="text-end">{{ number_format($row->expected_cost, 2) }}</td>
                <td class="text-end {{ $row->variance > 0 ? 'text-danger' : 'text-success' }}">
                  {{ $row->variance > 0 ? '+' : '' }}{{ number_format($row->variance, 2) }}
                </td>
                <td class="text-end">{{ number_format($row->total_cost, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">No costing data found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ── 4. VENDOR RAW BALANCE ─────────────────────────────────── --}}
    <div id="VRB" class="tab-pane fade {{ $tab=='VRB'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.production') }}">
        <input type="hidden" name="tab" value="VRB">
        @include('reports._filter', ['showVendor' => false])
      </form>

      <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle me-1"></i>
        Shows raw material that should currently be at each vendor's warehouse
        (sent minus wastage returned).
      </div>

      @forelse($vendorRawBalance as $vb)
        <div class="card mb-3">
          <div class="card-header bg-light d-flex justify-content-between">
            <strong><i class="fas fa-user me-1"></i> {{ $vb->vendor }}</strong>
            <span class="badge bg-warning text-dark">{{ $vb->total }} raw items pending</span>
          </div>
          <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th>Raw Material</th>
                  <th class="text-end">Total Sent</th>
                  <th class="text-end">Wastage Returned</th>
                  <th class="text-end">Remaining at Vendor</th>
                </tr>
              </thead>
              <tbody>
                @foreach($vb->balance as $b)
                  <tr>
                    <td>{{ $b->product }}</td>
                    <td class="text-end">{{ number_format($b->sent, 2) }} {{ $b->unit }}</td>
                    <td class="text-end">{{ number_format($b->returned, 2) }}</td>
                    <td class="text-end fw-bold text-warning">
                      {{ number_format($b->remaining, 2) }} {{ $b->unit }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @empty
        <div class="text-center text-muted py-4">
          No raw material pending at vendors for this period.
        </div>
      @endforelse
    </div>

    {{-- ── 5. PRODUCTION RETURNS ─────────────────────────────────── --}}
    <div id="RTN" class="tab-pane fade {{ $tab=='RTN'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.production') }}">
        <input type="hidden" name="tab" value="RTN">
        @include('reports._filter', ['showVendor' => false])
      </form>

      @php
        $rtnTotal = $returnReport->sum('total');
        $rtnQty   = $returnReport->sum('qty');
      @endphp
      <div class="row mb-2">
        <div class="col text-end">
          <span class="me-3">Total Qty: <strong>{{ number_format($rtnQty, 2) }}</strong></span>
          <span>Total Value: <strong class="text-danger">{{ number_format($rtnTotal, 2) }}</strong></span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
          <thead class="table-light">
            <tr>
              <th>Date</th><th>Return #</th><th>Vendor</th><th>Item</th>
              <th>Variation</th><th>Production #</th>
              <th class="text-end">Qty</th><th>Unit</th>
              <th class="text-end">Rate</th><th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse($returnReport as $row)
              <tr>
                <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                <td>#{{ $row->return_id }}</td>
                <td>{{ $row->vendor }}</td>
                <td>{{ $row->item_name }}</td>
                <td>{{ $row->variation !== '-' ? $row->variation : '' }}</td>
                <td>{{ $row->production !== '-' ? '#'.$row->production : '-' }}</td>
                <td class="text-end">{{ number_format($row->qty, 2) }}</td>
                <td>{{ $row->unit }}</td>
                <td class="text-end">{{ number_format($row->rate, 2) }}</td>
                <td class="text-end">{{ number_format($row->total, 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="10" class="text-center text-muted">No production returns found.</td></tr>
            @endforelse
          </tbody>
          @if($returnReport->count())
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="6" class="text-end">Total</td>
                <td class="text-end">{{ number_format($rtnQty, 2) }}</td>
                <td></td>
                <td></td>
                <td class="text-end">{{ number_format($rtnTotal, 2) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>

    {{-- ── 6. DELIVERY TRACKING ──────────────────────────────────── --}}
    <div id="DLV" class="tab-pane fade {{ $tab=='DLV'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.production') }}">
        <input type="hidden" name="tab" value="DLV">
        @include('reports._filter', ['showVendor' => false])
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
          <thead class="table-light">
            <tr>
              <th>Production #</th>
              <th>Vendor</th>
              <th>Order Date</th>
              <th>First Receiving</th>
              <th>Last Receiving</th>
              <th class="text-end">Days to First</th>
              <th class="text-end">Days to Last</th>
              <th class="text-end">Raw Sent</th>
              <th class="text-end">FG Received</th>
              <th class="text-end">Receivings</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($deliveryReport as $row)
              <tr class="{{ $row->status === 'Pending' ? 'table-warning' : '' }}">
                <td>#{{ $row->production_id }}</td>
                <td>{{ $row->vendor }}</td>
                <td>{{ \Carbon\Carbon::parse($row->order_date)->format('d-m-Y') }}</td>
                <td>{{ $row->first_receiving !== 'Pending' ? \Carbon\Carbon::parse($row->first_receiving)->format('d-m-Y') : '—' }}</td>
                <td>{{ $row->last_receiving  !== 'Pending' ? \Carbon\Carbon::parse($row->last_receiving)->format('d-m-Y')  : '—' }}</td>
                <td class="text-end">
                  @if($row->days_to_first !== null)
                    <span class="{{ $row->days_to_first > 30 ? 'text-danger fw-bold' : '' }}">
                      {{ $row->days_to_first }} days
                    </span>
                  @else
                    <span class="text-warning">Pending</span>
                  @endif
                </td>
                <td class="text-end">
                  {{ $row->days_to_last !== null ? $row->days_to_last . ' days' : '—' }}
                </td>
                <td class="text-end">{{ number_format($row->total_raw, 2) }}</td>
                <td class="text-end">{{ number_format($row->total_fg, 2) }}</td>
                <td class="text-end">{{ $row->receiving_count }}</td>
                <td>
                  @if($row->status === 'Pending')
                    <span class="badge bg-warning text-dark">Pending</span>
                  @else
                    <span class="badge bg-success">Received</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="11" class="text-center text-muted">No data found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- ── 7. VENDOR SUMMARY ─────────────────────────────────────── --}}
    <div id="SUM" class="tab-pane fade {{ $tab=='SUM'?'show active':'' }}">
      <form method="GET" action="{{ route('reports.production') }}">
        <input type="hidden" name="tab" value="SUM">
        @include('reports._filter', ['showVendor' => false])
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
          <thead class="table-light">
            <tr>
              <th>Vendor</th>
              <th class="text-end">Orders</th>
              <th class="text-end">Total Raw Sent</th>
              <th class="text-end">Raw Cost</th>
              <th class="text-end">Total FG Received</th>
              <th class="text-end">Mfg. Cost</th>
              <th class="text-end">Avg Consumption</th>
            </tr>
          </thead>
          <tbody>
            @forelse($orderSummary as $row)
              <tr>
                <td>{{ $row->vendor }}</td>
                <td class="text-end">{{ $row->orders }}</td>
                <td class="text-end">{{ number_format($row->total_raw, 2) }}</td>
                <td class="text-end">{{ number_format($row->raw_cost, 2) }}</td>
                <td class="text-end">{{ number_format($row->total_fg, 2) }}</td>
                <td class="text-end">{{ number_format($row->mfg_cost, 2) }}</td>
                <td class="text-end">{{ $row->avg_con }}</td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">No vendor data found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
  // Restore active tab from URL
  document.addEventListener('DOMContentLoaded', function () {
    const tab = new URLSearchParams(window.location.search).get('tab') || 'RMI';
    const el  = document.querySelector(`.nav-link[href="#${tab}"]`);
    if (el && typeof bootstrap !== 'undefined') {
      new bootstrap.Tab(el).show();
    }
  });
</script>
@endsection