@extends('layouts.app')

@section('title', 'Production | Order Receiving')

@section('content')
  <div class="row">
    <form action="" method="POST" enctype="multipart/form-data">
      @csrf
      @if ($errors->has('error'))
        <strong class="text-danger">{{ $errors->first('error') }}</strong>
      @endif
      <div class="row">
        <div class="col-12 mb-4">
          <section class="card">
            <header class="card-header">
              <div style="display: flex;justify-content: space-between;">
                <h2 class="card-title">Order Receiving</h2>
              </div>
            </header>
            <div class="card-body">
              <div class="row mb-4">
                <div class="col-12 col-md-2">
                  <label>GRN #</label>
                  <input type="text" class="form-control" placeholder="GRN #" disabled/>
                </div>
                
                <div class="col-12 col-md-2">
                  <label>Receiving Date</label>
                  <input type="date" name="rec_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required/>
                </div>

                <div class="col-12 col-md-2">
                    <label>Vendor</label>
                    <select data-plugin-selecttwo class="form-control select2-js"   name="item_name[]" onchange="getItemDetails(1,2)" required>
                        <option value="" selected disabled>Select Vendor</option>
                    </select>	                
                </div>
                
              </div>
            </div>
          </section>
        </div>
        <div class="col-12 mb-4">
          <section class="card">
            <header class="card-header">
              <h2 class="card-title">Product Details</h2>
            </header>
            
            <div class="card-body">
              <table class="table table-bordered" id="myTable">
                <thead>
                  <tr>
                    <th>Item Code</th>
                    <th>Item Name</th>
                    <th>Variation</th>
                    <th>M.Cost</th>
                    <th>Received</th>
                    <th>Remarks</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody id="PurPOTbleBody">
                  <tr>
                    <td>
                      <input type="text" class="form-control" name="item_cod[]"  id="item_cod1" onchange="getItemDetails(1,1)" required>
                    </td>	
                    <td>
                        <select data-plugin-selecttwo class="form-control select2-js"   name="item_name[]" onchange="getItemDetails(1,2)" required>
                            <option value="" selected disabled>Select Item</option>
                        </select>													
                    </td>
                    <td>
                        <select data-plugin-selecttwo class="form-control select2-js"   name="item_name[]" onchange="getItemDetails(1,2)" required>
                            <option value="" selected disabled>Select Variation</option>
                        </select>													
                    </td>
                    <td>
                        <input type="number" class="form-control" name="pur_qty[]" id="pur_qty1" onchange="rowTotal(1)" value="0" step="any" disabled>
                    </td>
                    <td>
                        <input type="number" class="form-control" name="pur_qty[]" id="pur_qty1" onchange="rowTotal(1)" value="0" step="any" required>
                    </td>
                    <td>
                        <input type="text" class="form-control" id="remarks1" name="remarks[]">
                    </td>
                    <td>
                        <input type="number" class="form-control" id="amount1" onchange="tableTotal()" value="0" disabled required step="any">
                    </td>
                    <td style="vertical-align: middle;">
                        <button type="button" onclick="removeRow(this)" class="btn btn-danger" tabindex="1"><i class="fas fa-times"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
                                  <footer class="card-footer" >
                        <div class="row">
                            <div class="row form-group mb-3">
                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Total Pcs</label>
                                    <input type="text" id="totalAmount" name="totalAmount" placeholder="Total Amount" class="form-control" disabled>
                                    <input type="hidden" id="total_amount_show" name="total_amount" placeholder="Total Weight" class="form-control">
                                </div>

                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Total Amount</label>
                                    <input type="text" id="total_weight" placeholder="Total Weight" class="form-control" disabled>
                                    <input type="hidden" id="total_weight_show" name="total_weight" placeholder="Total Weight" class="form-control">
                                </div>

                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Convance Charges</label>
                                    <input type="text" id="convance_charges" onchange="netTotal()" name="pur_convance_char" placeholder="Convance Charges" class="form-control">
                                </div>

                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Bill Discount</label>
                                    <input type="text" id="bill_discount"  onchange="netTotal()" name="bill_discount" placeholder="Bill Discount" class="form-control">
                                </div>

                                <div class="col-12 pb-sm-3 pb-md-0 text-end">
                                    <h3 class="font-weight-bold mt-3 mb-0 text-5 text-primary">Net Amount</h3>
                                    <span>
                                        <strong class="text-4 text-primary">PKR <span id="netTotal" class="text-4 text-danger">0.00 </span></strong>
                                    </span>
                                    <input type="hidden" id="net_amount" name="net_amount" placeholder="Total Weight" class="form-control">
                                </div>
                            </div>
                        </div>
                    </footer>
              <footer class="card-footer text-end mt-1">
                <a class="btn btn-danger" href="" >Discard</a>
                <button type="submit" class="btn btn-primary">Received</button>
              </footer>
            </div>
          </section>
        </div>
      </div>
    </form>
  </div>

@endsection