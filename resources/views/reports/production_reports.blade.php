@extends('layouts.app')
@section('title', 'Production Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab=='RMI'?'active':'' }}" data-bs-toggle="tab" href="#RMI">Raw Material Issued</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='PR'?'active':'' }}" data-bs-toggle="tab" href="#PR">Production Received</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='CR'?'active':'' }}" data-bs-toggle="tab" href="#CR">Costing Report</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='WIP'?'active':'' }}" data-bs-toggle="tab" href="#WIP">Work in Progress</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='YW'?'active':'' }}" data-bs-toggle="tab" href="#YW">Yield / Waste</a></li>
    </ul>

    <div class="tab-content mt-3">
        {{-- RAW MATERIAL ISSUED --}}
        <div id="RMI" class="tab-pane fade {{ $tab=='RMI'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="RMI">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Production</th><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($rawIssued as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->production }}</td>
                            <td>{{ $row->item_name }}</td>
                            <td>{{ $row->qty }}</td>
                            <td>{{ $row->rate }}</td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No raw material issued found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PRODUCTION RECEIVED --}}
        <div id="PR" class="tab-pane fade {{ $tab=='PR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="PR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Production</th><th>Item</th><th>Qty</th><th>M. Cost</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($produced as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->production }}</td>
                            <td>{{ $row->item_name }}</td>
                            <td>{{ $row->qty }}</td>
                            <td>{{ $row->m_cost }}</td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No production received found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- COSTING REPORT --}}
        <div id="CR" class="tab-pane fade {{ $tab=='CR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="CR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Project</th><th>Total Pcs</th><th>Cost/pc</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($costings as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->project }}</td>
                            <td>{{ $row->pcs }}</td>
                            <td>{{ $row->cost_per_pc }}</td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No costing data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- WORK IN PROGRESS --}}
        <div id="WIP" class="tab-pane fade {{ $tab=='WIP'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="WIP">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Production</th><th>Total Items</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($wip as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->production }}</td>
                            <td>{{ $row->total_items }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No WIP data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- YIELD / WASTE --}}
        <div id="YW" class="tab-pane fade {{ $tab=='YW'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="YW">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Production</th><th>Yield</th><th>Waste</th></tr></thead>
                <tbody>
                    @forelse($yieldWaste as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->production }}</td>
                            <td>{{ $row->yield }}</td>
                            <td>{{ $row->waste }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No yield/waste found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
