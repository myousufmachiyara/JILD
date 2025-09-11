@extends('layouts.app')
@section('title', 'Purchase Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab=='PUR'?'active':'' }}" data-bs-toggle="tab" href="#PUR">Purchase Register</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='PR'?'active':'' }}" data-bs-toggle="tab" href="#PR">Purchase Returns</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='VWP'?'active':'' }}" data-bs-toggle="tab" href="#VWP">Vendor-wise Purchases</a></li>
    </ul>

    <div class="tab-content mt-3">
        {{-- PURCHASE REGISTER --}}
        <div id="PUR" class="tab-pane fade {{ $tab=='PUR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.purchase') }}">
                <input type="hidden" name="tab" value="PUR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th><th>Invoice No</th><th>Vendor</th><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseRegister as $pur)
                        <tr>
                            <td>{{ $pur->date }}</td>
                            <td>{{ $pur->invoice_no }}</td>
                            <td>{{ $pur->vendor_name }}</td>
                            <td>{{ $pur->item_name }}</td>
                            <td>{{ $pur->quantity }}</td>
                            <td>{{ $pur->rate }}</td>
                            <td>{{ $pur->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No purchase records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PURCHASE RETURNS --}}
        <div id="PR" class="tab-pane fade {{ $tab=='PR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.purchase') }}">
                <input type="hidden" name="tab" value="PR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th><th>Return No</th><th>Vendor</th><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseReturns as $pr)
                        <tr>
                            <td>{{ $pr->date }}</td>
                            <td>{{ $pr->return_no }}</td>
                            <td>{{ $pr->vendor_name }}</td>
                            <td>{{ $pr->item_name }}</td>
                            <td>{{ $pr->quantity }}</td>
                            <td>{{ $pr->rate }}</td>
                            <td>{{ $pr->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No purchase return records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- VENDOR WISE PURCHASE --}}
        <div id="VWP" class="tab-pane fade {{ $tab=='VWP'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.purchase') }}">
                <input type="hidden" name="tab" value="VWP">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control" required>
                            <option value="">-- Select Vendor --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
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
                    <tr>
                        <th>Vendor</th><th>Total Qty</th><th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorWisePurchase as $vwp)
                        <tr>
                            <td>{{ $vwp->vendor_name }}</td>
                            <td>{{ $vwp->total_qty }}</td>
                            <td>{{ $vwp->total_amount }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No vendor purchase data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
