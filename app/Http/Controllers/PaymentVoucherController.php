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
}
