<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;

class COAController extends Controller
{

    public function index()
    {
        $chartOfAccounts = ChartOfAccounts::with('subHeadOfAccount')->get();
        $subHeadOfAccounts = SubHeadOfAccounts::with('headOfAccount')->get();

        return view('accounts.coa', compact('chartOfAccounts','subHeadOfAccounts'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'shoa_id' => 'required|exists:sub_head_of_accounts,id',
                'name' => 'required|string|max:255|unique:chart_of_accounts',
                'account_type' => 'nullable|string|max:255',
                'receivables' => 'required|numeric',
                'payables' => 'required|numeric',
                'opening_date' => 'required|date',
                'remarks' => 'nullable|string|max:800',
                'address' => 'nullable|string|max:250',
                'phone_no' => 'nullable|string|max:250',
            ]);
        
            ChartOfAccounts::create($request->all());
        
            return redirect()->route('coa.index')->with('success', 'Chart of Account created successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $account = ChartOfAccounts::findOrFail($id);
        return response()->json($account);
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'shoa_id' => 'required|exists:sub_head_of_accounts,id',
                'name' => 'required|string|max:255|unique:chart_of_accounts,name,' . $id,
                'account_type' => 'nullable|string|max:255',
                'receivables' => 'required|numeric',
                'payables' => 'required|numeric',
                'opening_date' => 'required|date',
                'remarks' => 'nullable|string|max:800',
                'address' => 'nullable|string|max:250',
                'phone_no' => 'nullable|string|max:250',
            ]);

            $account = ChartOfAccounts::findOrFail($id);
            $account->update($request->all());

            return redirect()->route('coa.index')->with('success', 'Chart of Account updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $account = ChartOfAccounts::with('subHeadOfAccount')->findOrFail($id);
        return response()->json($account);
    }

    public function destroy($id)
    {
        $chartOfAccount = ChartOfAccounts::findOrFail($id);
        $chartOfAccount->delete();

        return redirect()->route('coa.index')->with('success', 'Chart of Account deleted successfully.');
    }
}
