<?php

namespace App\Http\Controllers;

use App\Models\PaymentVoucher;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentVoucherController extends Controller
{
    /**
     * Display all payment vouchers.
     */
    public function index()
    {
        $jv1 = PaymentVoucher::with(['debitAccount', 'creditAccount'])->get();
        $acc = ChartOfAccounts::all();

        return view('payment-vouchers.index', compact('jv1', 'acc'));
    }

    /**
     * Store a newly created payment voucher.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'ac_dr_sid' => 'required|numeric',
            'ac_cr_sid' => 'required|numeric|different:ac_dr_sid',
            'amount' => 'required|numeric|min:1',
            'remarks' => 'nullable|string',
            'att.*' => 'nullable|file|max:2048',
        ]);

        $attachments = [];
        if ($request->hasFile('att')) {
            foreach ($request->file('att') as $file) {
                $attachments[] = $file->store('attachments/payment_vouchers', 'public');
            }
        }

        PaymentVoucher::create([
            'date' => $data['date'],
            'ac_dr_sid' => $data['ac_dr_sid'],
            'ac_cr_sid' => $data['ac_cr_sid'],
            'amount' => $data['amount'],
            'remarks' => $data['remarks'],
            'attachments' => $attachments,
        ]);

        return back()->with('success', 'Payment voucher added successfully!');
    }

    /**
     * Update an existing payment voucher.
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'ac_dr_sid' => 'required|numeric',
            'ac_cr_sid' => 'required|numeric|different:ac_dr_sid',
            'amount' => 'required|numeric|min:1',
            'remarks' => 'nullable|string',
            'att.*' => 'nullable|file|max:2048',
        ]);

        $voucher = PaymentVoucher::findOrFail($id);

        $attachments = $voucher->attachments ?? [];
        if ($request->hasFile('att')) {
            foreach ($request->file('att') as $file) {
                $attachments[] = $file->store('attachments/payment_vouchers', 'public');
            }
        }

        $voucher->update([
            'date' => $data['date'],
            'ac_dr_sid' => $data['ac_dr_sid'],
            'ac_cr_sid' => $data['ac_cr_sid'],
            'amount' => $data['amount'],
            'remarks' => $data['remarks'],
            'attachments' => $attachments,
        ]);

        return redirect()->route('payment_vouchers.index')->with('success', 'Payment voucher updated successfully!');
    }

    /**
     * Delete a payment voucher.
     */
    public function destroy($id)
    {
        $voucher = PaymentVoucher::findOrFail($id);

        // Optionally delete attached files
        if (!empty($voucher->attachments)) {
            foreach ($voucher->attachments as $file) {
                if (Storage::disk('public')->exists($file)) {
                    Storage::disk('public')->delete($file);
                }
            }
        }

        $voucher->delete();

        return back()->with('success', 'Voucher deleted successfully.');
    }

    /**
     * Show a single payment voucher.
     */
    public function show($id)
    {
        $voucher = PaymentVoucher::findOrFail($id);
        return response()->json($voucher);
    }

    public function print($id)
    {
        $voucher = PaymentVoucher::with(['debitAccount', 'creditAccount'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Payment Voucher #' . $voucher->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/jild-Logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        // --- Voucher Info ---
        $pdf->SetXY(130, 12);
        $infoHtml = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Voucher #</b></td><td>' . $voucher->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($voucher->date)->format('d/m/Y') . '</td></tr>
        </table>';
        $pdf->writeHTML($infoHtml, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Box ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Payment Voucher', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // --- Payment Details Table ---
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="10%">S.No</th>
                <th width="40%">Debit Account</th>
                <th width="40%">Credit Account</th>
                <th width="10%">Amount</th>
            </tr>';

        // If you have multiple lines, loop through them, otherwise just 1
        $html .= '<tr>
            <td>1</td>
            <td>' . ($voucher->debitAccount->name ?? '-') . '</td>
            <td>' . ($voucher->creditAccount->name ?? '-') . '</td>
            <td align="right">' . number_format($voucher->amount, 2) . '</td>
        </tr>';

        // Total row
        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="3" align="right"><b>Total</b></td>
                <td align="right"><b>' . number_format($voucher->amount, 2) . '</b></td>
            </tr>';

        $html .= '</table>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(5);

        // --- Remarks ---
        if (!empty($voucher->remarks)) {
            $pdf->writeHTML('<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($voucher->remarks) . '</span>', true, false, true, false, '');
        }

        // --- Signatures ---
        $pdf->Ln(20);
        $yPos = $pdf->GetY();
        $lineWidth = 40;

        $pdf->Line(28, $yPos, 28 + $lineWidth, $yPos);
        $pdf->Line(130, $yPos, 130 + $lineWidth, $yPos);

        $pdf->SetXY(28, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Prepared By', 0, 0, 'C');
        $pdf->SetXY(130, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('payment_voucher_' . $voucher->id . '.pdf', 'I');
    }

}
