@extends('layouts.app')

@section('title', 'Purchases | New Invoice')

@section('content')
  <div class="row">
    <div class="col">
      <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <section class="card">
          <header class="card-header">
            <div class="d-flex justify-content-between">
              <h2 class="card-title">New Purchase</h2>
            </div>
            <div class="card-actions">
                <button type="button" class="btn btn-primary" onclick="addNewRow_btn()"> <i class="fas fa-plus"></i> Add New Row </button>
            </div>
            @if ($errors->has('error'))
              <strong class="text-danger">{{ $errors->first('error') }}</strong>
            @endif
          </header>
          <div class="card-body">
                <div class="row form-group mb-2">
                    <div class="col-6 col-md-2 mb-2">
                        <label class="col-form-label" >Invoice No.</label>
                        <input type="text" placeholder="(New Invoice)" class="form-control" disabled>
                        <input type="hidden" id="itemCount" name="items" value="1" class="form-control">
                    </div>
                    <div class="col-6 col-md-2  mb-2">
                        <label class="col-form-label" >Date</label>
                        <input type="date" name="pur_date"  value="<?php echo date('Y-m-d'); ?>" class="form-control">
                    </div>
                    <div class="col-sm-12 col-md-3 mb-3">
                        <td>
                            <label class="col-form-label">Vendor Name<span style="color: red;"><strong>*</strong></span></label>
                            <select data-plugin-selecttwo class="form-control select2-js"  name="ac_cod" required>
                                <option value="" disabled selected>Select Vendor</option>

                            </select>
                        </td>
                    </div>
                    <div class="col-6 col-md-2  mb-2">
                        <label class="col-form-label" >Payment Terms</label>
                        <input type="text" placeholder="Payment Terms" name="pur_bill_no" class="form-control">
                    </div>
                    <div class="col-6 col-md-2 mb-2">
                        <label class="col-form-label" >Bill #</label>
                        <input type="text" placeholder="Bill #" name="pur_sale_inv" class="form-control">
                    </div>
                    <div class="col-6 col-md-2 mb-2">
                        <label class="col-form-label" >Ref #</label>
                        <input type="text" placeholder="Ref #" name="pur_sale_inv" class="form-control">
                    </div>
                    <div class="col-sm-12 col-md-4 mb-3">
                        <label class="col-form-label">Attachements</label>
                        <input type="file" class="form-control" name="att[]" multiple accept=".zip, appliation/zip, application/pdf, image/png, image/jpeg">
                    </div>

                    <div class="col-12 mb-2">
                        <label class="col-form-label">Remarks</label>
                        <textarea rows="4" cols="50" name="pur_remarks" id="pur_remarks" placeholder="Remarks" class="form-control cust-textarea"></textarea>
                    </div>	
                </div>
            </div>
								
            <div class="col-12 mb-3">
                <section class="card">
                    <div class="card-body" style="overflow-x:auto;max-height:450px;overflow-y:auto">
                        <table class="table table-bordered table-striped mb-0" id="myTable" >
                            <thead>
                                <tr>
                                    <th width="10%">Item Code<span style="color: red;"><strong>*</strong></span></th>
                                    <th width="20%">Item Name<span style="color: red;"><strong>*</strong></span></th>
                                    <th width="10%">Bud/Pkg<span style="color: red;"><strong>*</strong></span></th>
                                    <th width="10%">Qty<span style="color: red;"><strong>*</strong></span></th>
                                    <th width="10%">Unit<span style="color: red;"><strong>*</strong></span></th>
                                    <th width="10%">Price<span style="color: red;"><strong>*</strong></span></th>
                                    <th width="10%">Amount</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody id="Purchase1Table">
                                <tr>
                                    <td>
                                        <input type="text" class="form-control" name="item_cod[]"  id="item_cod1" onchange="getItemDetails(1,1)" required>
                                    </td>	
                                    <td>
                                        <select data-plugin-selecttwo class="form-control select2-js"  id="item_name1" name="item_name[]" onchange="getItemDetails(1,2)" required>
                                            <option value="" selected disabled>Select Item</option>
                                        </select>													
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" onchange="rowTotal(1)" id="pur_qty2_1" name="pur_qty2[]" value="0" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" name="pur_qty[]" id="pur_qty1" onchange="rowTotal(1)" value="0" step="any" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" id="remarks1" name="remarks[]">
                                    </td>

                                    <td>
                                        <input type="number" class="form-control" name="pur_price[]" id="pur_price1" onchange="rowTotal(1)" value="0" step="any" required>
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
                    </div>

                    <footer class="card-footer" >
                        <div class="row">
                            <div class="row form-group mb-3">
                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Total Amount</label>
                                    <input type="text" id="totalAmount" name="totalAmount" placeholder="Total Amount" class="form-control" disabled>
                                    <input type="hidden" id="total_amount_show" name="total_amount" placeholder="Total Weight" class="form-control">
                                </div>

                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Total Pkg/Bnd</label>
                                    <input type="text" id="total_weight" placeholder="Total Weight" class="form-control" disabled>
                                    <input type="hidden" id="total_weight_show" name="total_weight" placeholder="Total Weight" class="form-control">
                                </div>

                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Total Quantity</label>
                                    <input type="text" id="total_quantity" placeholder="Total Quantity" class="form-control" disabled>
                                    <input type="hidden" id="total_quantity_show" name="total_quantity" placeholder="Total Quantity" class="form-control">
                                </div>

                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Convance Charges</label>
                                    <input type="text" id="convance_charges" onchange="netTotal()" name="pur_convance_char" placeholder="Convance Charges" class="form-control">
                                </div>

                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                    <label class="col-form-label">Labour Charges</label>
                                    <input type="text" id="labour_charges"  onchange="netTotal()" name="pur_labor_char" placeholder="Labour Charges" class="form-control">
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
                    <footer class="card-footer">
                        <div class="row form-group mb-2">
                            <div class="text-end">
                                <button type="button" class="btn btn-danger mt-2"  onclick="window.location='{{ route('purchases.index') }}'"> <i class="fas fa-trash"></i> Discard Invoice</button>
                                <button type="submit" class="btn btn-primary mt-2"> <i class="fas fa-save"></i> Add Invoice</button>
                            </div>
                        </div>
                    </footer>
                </section>
            </div>
      </form>
    </div>
  </div>

<script>
	
	var index=2;
	var itemCount = Number($('#itemCount').val());

	$(document).ready(function() {
		$(window).keydown(function(event){
			if(event.keyCode == 13) {
				event.preventDefault();
				return false;
			}
		});
	});

    function removeRow(button) {
		var tableRows = $("#Purchase1Table tr").length;
		if(tableRows>1){
			var row = button.parentNode.parentNode;
			row.parentNode.removeChild(row);
			index--;	
			itemCount = Number($('#itemCount').val());
			itemCount = itemCount-1;
			$('#itemCount').val(itemCount);
		}  
		tableTotal(); 
    }

	function addNewRow(){
		var lastRow =  $('#myTable tr:last');
		latestValue=lastRow[0].cells[2].querySelector('select').value;
		if(latestValue!=""){
			var table = document.getElementById('myTable').getElementsByTagName('tbody')[0];
			var newRow = table.insertRow(table.rows.length);

			var cell1 = newRow.insertCell(0);
			var cell2 = newRow.insertCell(1);
			var cell3 = newRow.insertCell(2);
			var cell4 = newRow.insertCell(3);
			var cell5 = newRow.insertCell(4);
			var cell6 = newRow.insertCell(5);
			var cell7 = newRow.insertCell(6);
			var cell8 = newRow.insertCell(7);

			cell1.innerHTML  = '<input type="text" class="form-control" name="item_cod[]" id="item_cod'+index+'"  onchange="getItemDetails('+index+','+1+')" required>';
			cell2.innerHTML  = '<input type="text" class="form-control" onchange="rowTotal('+index+')" id="pur_qty2_'+index+'" value="0" name="pur_qty2[]" step="any" required>';
			cell3.innerHTML  = '<select data-plugin-selecttwo class="form-control select2-js" id="item_name'+index+'"  onchange="getItemDetails('+index+','+2+')" name ="item_name[]" required>'+
									'<option value="" disabled selected>Select Account</option>'+
									
								'</select>';
			cell4.innerHTML  = '<input type="text" class="form-control" id="remarks'+index+'" name="remarks[]">';
			cell5.innerHTML  = '<input type="number" id="pur_qty'+index+'" class="form-control" name="pur_qty[]" value="0" onchange="rowTotal('+index+')" step="any" required>';
			cell6.innerHTML  = '<input type="number" id="pur_price'+index+'" class="form-control" name="pur_price[]"  value="0" onchange="rowTotal('+index+')" step="any" required>';
			cell7.innerHTML  = '<input type="number" id="amount'+index+'" class="form-control"  value="0" onchange="tableTotal()" step="any" disabled>';
			cell8.innerHTML = '<button type="button" onclick="removeRow(this)" class="btn btn-danger" tabindex="1"><i class="fas fa-times"></i></button>';
			index++;

			itemCount = Number($('#itemCount').val());
			itemCount = itemCount+1;
			$('#itemCount').val(itemCount);
			$('#myTable select[data-plugin-selecttwo]').select2();

	
		}
	}
		
		function addNewRow_btn() {

    		addNewRow(); // Call the same function
			// Set focus on the new item_code input field
			document.getElementById('item_cod' + (index - 1)).focus();


		}

	function getItemDetails(row_no,option){
		var itemId;
		if(option==1){
			itemId = document.getElementById("item_cod"+row_no).value;
		}
		else if(option==2){
			itemId = document.getElementById("item_name"+row_no).value;
		}
		$.ajax({
			type: "GET",
			url: "/items/detail",
			data: {id:itemId},
			success: function(result){
				$('#item_cod'+row_no).val(result[0]['it_cod']);
				$('#item_name'+row_no).val(result[0]['it_cod']).select2();
				$('#remarks'+row_no).val(result[0]['item_remark']);
				$('#pur_price'+row_no).val(result[0]['OPP_qty_cost']);
				addNewRow();
			},
			error: function(){
				alert("error");
			}
		});
		
	}

	function getCOADetails(){
		var coaId = document.getElementById("coa_name").value;
		
		$.ajax({
			type: "GET",
			url: "/coa/detail",
			data: {id:coaId},
			success: function(result){
				$('#address').val(result[0]['address']);
				$('#cash_pur_phone').val(result[0]['phone_no']);
				$('#remarks').val(result[0]['remarks']);
			},
			error: function(){
				alert("error");
			}
		});
	}

	function rowTotal(index){
		var weight = $('#pur_qty'+index+'').val();
		var price = $('#pur_price'+index+'').val();
		var amount = weight * price;
		$('#amount'+index+'').val(amount);
		tableTotal();
	}

	function tableTotal(){
		var totalAmount=0;
		var totalWeight=0;
		var totalQuantity=0;
		var tableRows = $("#Purchase1Table tr").length;
		var table = document.getElementById('myTable').getElementsByTagName('tbody')[0];

		for (var i = 0; i < tableRows; i++) {
			var currentRow =  table.rows[i];
			totalAmount = totalAmount + Number(currentRow.cells[6].querySelector('input').value);
			totalWeight = totalWeight + Number(currentRow.cells[4].querySelector('input').value);
			totalQuantity = totalQuantity + Number(currentRow.cells[1].querySelector('input').value);
        }

		$('#totalAmount').val(totalAmount);
		$('#total_amount_show').val(totalAmount);
		$('#total_weight').val(totalWeight);
		$('#total_weight_show').val(totalWeight);
		$('#total_quantity').val(totalQuantity);
		$('#total_quantity_show').val(totalQuantity);
		
		netTotal();
	}

	function netTotal(){
		var netTotal = 0;
		var total = Number($('#totalAmount').val());
		var convance_charges = Number($('#convance_charges').val());
		var labour_charges = Number($('#labour_charges').val());
		var bill_discount = Number($('#bill_discount').val());

		netTotal = total + convance_charges + labour_charges - bill_discount;
		netTotal = netTotal.toFixed(0);
		FormattednetTotal = formatNumberWithCommas(netTotal);
		document.getElementById("netTotal").innerHTML = '<span class="text-4 text-danger">'+FormattednetTotal+'</span>';
		$('#net_amount').val(netTotal);
	}
 
	function formatNumberWithCommas(number) {
    	// Convert number to string and add commas
    	return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
	}
</script>
@endsection