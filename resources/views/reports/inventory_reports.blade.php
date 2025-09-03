@extends('layouts.app')

@section('title', 'Inventory Reports')

@section('content')
    <div class="tabs">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link" data-bs-target="#IL" href="#IL" data-bs-toggle="tab">Item Ledger</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-target="#SR" href="#SR" data-bs-toggle="tab">Stock Inhand</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-target="#STR" href="#STR" data-bs-toggle="tab">Stock Transfer</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-target="#NMI" href="#NMI" data-bs-toggle="tab">Non-Moving Items</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-target="#ROL" href="#ROL" data-bs-toggle="tab">Reorder Level</a>
            </li>
        </ul>
        <div class="tab-content">
            <div id="IL" class="tab-pane active">
                <div class="row form-group pb-3">
                    <div class="col-lg-8">
                        <div class="mb-4 row g-3">
                            <div class="col-md-4">
                                <label for="item_id">Select Item</label>
                                <select class="form-control" id="item_id" name="item_id" required>
                                    <option value="">-- Select Item --</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }}</option>
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
                                <button type="button" class="btn btn-primary"><i class="fa fa-filter"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 text-end pt-4">
                        <a class="mb-1 mt-1 me-1 btn btn-warning" aria-label="Download"><i class="fa fa-download"></i></a>
                        <a class="mb-1 mt-1 me-1 btn btn-danger" aria-label="Print PDF"><i class="fa fa-file-pdf"></i></a>
                        <a class="mb-1 mt-1 me-1 btn btn-success" aria-label="Export to Excel"><i class="fa fa-file-excel"></i></a>      
                    </div>
                    <div class="col-12 mt-4">
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
                                </tbody>
                            </table>
                    </div>
                </div>
            </div>
            
            <div id="SR" class="tab-pane">
                <div class="row form-group pb-3">
                </div>
            </div>

            <div id="STR" class="tab-pane">
                <div class="row form-group pb-3">
                </div>
            </div>

            <div id="NMI" class="tab-pane">
                <div class="row form-group pb-3">
                </div>
            </div>

            <div id="ROL" class="tab-pane">
                <div class="row form-group pb-3">
                </div>
            </div>
        </div>
    </div>
@endsection
