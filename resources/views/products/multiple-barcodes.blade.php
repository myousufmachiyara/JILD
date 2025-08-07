<!DOCTYPE html>
<html>
<head>
    <title>Print Barcodes</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .barcode-block {
            display: inline-block;
            width: 200px;
            text-align: center;
            margin: 10px;
            page-break-inside: avoid;
        }
        .barcode-block img {
            width: 100%;
        }
        .price {
            font-weight: bold;
            margin-top: 5px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 20px;">
    <button onclick="window.print()">Print</button>
</div>
@foreach($barcodes as $barcode)
    <div class="barcode-label" style="width: 200px; border: 1px solid #ccc; padding: 10px; margin: 10px; display: inline-block; text-align: center;">
        <strong>{{ $barcode['product'] }}</strong><br>
        <small>{{ $barcode['variation'] }}</small><br>
        <img src="data:image/png;base64,{{ $barcode['barcodeImage'] }}" alt="barcode"><br>
        <small>{{ $barcode['barcodeText'] }}</small><br>
        <strong>Rs. {{ $barcode['price'] }}</strong>
    </div>
@endforeach

</body>
</html>
