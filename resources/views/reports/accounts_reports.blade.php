@extends('layouts.app')

@section('title', 'Accounting Reports')

@section('content')
<div class="tabs">

    <ul class="nav nav-tabs" id="reportTabs">
        @foreach ([
            'general_ledger'   => 'General Ledger',
            'trial_balance'    => 'Trial Balance',
            'profit_loss'      => 'Profit & Loss',
            'balance_sheet'    => 'Balance Sheet',
            'party_ledger'     => 'Party Ledger',
            'receivables'      => 'Receivables',
            'payables'         => 'Payables',
            'cash_book'        => 'Cash Book',
            'bank_book'        => 'Bank Book',
            'journal_book'     => 'Journal / Day Book',
            'expense_analysis' => 'Expense Analysis',
            'cash_flow'        => 'Cash Flow',
        ] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $key === $report ? 'active' : '' }}"
                   href="{{ route('reports.accounts') }}?report={{ $key }}&from_date={{ $from }}&to_date={{ $to }}">
                   {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="tab-content mt-3">

        @foreach ([
            'general_ledger'   => 'General Ledger',
            'trial_balance'    => 'Trial Balance',
            'profit_loss'      => 'Profit & Loss',
            'balance_sheet'    => 'Balance Sheet',
            'party_ledger'     => 'Party Ledger',
            'receivables'      => 'Receivables',
            'payables'         => 'Payables',
            'cash_book'        => 'Cash Book',
            'bank_book'        => 'Bank Book',
            'journal_book'     => 'Journal / Day Book',
            'expense_analysis' => 'Expense Analysis',
            'cash_flow'        => 'Cash Flow',
        ] as $key => $label)

        <div class="tab-pane fade {{ $key === $report ? 'show active' : '' }}" id="{{ $key }}">

            {{-- Filter --}}
            <form method="GET" action="{{ route('reports.accounts') }}" class="row g-2 mb-3">
                <input type="hidden" name="report" value="{{ $key }}">

                <div class="col-md-3">
                    <input type="date" name="from_date"
                           value="{{ $from }}"
                           class="form-control" required>
                </div>
                <div class="col-md-3">
                    <input type="date" name="to_date"
                           value="{{ $to }}"
                           class="form-control" required>
                </div>

                {{-- Account dropdown only for GL and Party Ledger --}}
                @if (in_array($key, ['general_ledger', 'party_ledger']))
                    <div class="col-md-4">
                        <select name="account_id" data-plugin-selecttwo class="form-control select2-js">
                            <option value="">-- All Accounts --</option>
                            @foreach ($chartOfAccounts as $coa)
                                <option value="{{ $coa->id }}"
                                    {{ ($key === $report && $accountId == $coa->id) ? 'selected' : '' }}>
                                    {{ $coa->account_code }} — {{ $coa->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                @else
                    <div class="col-md-6 d-flex align-items-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                @endif
            </form>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm align-middle">
                    <thead class="table-light">
                        @if ($key === 'general_ledger')
                            <tr>
                                <th>Date</th>
                                <th>Voucher</th>
                                <th>Contra Account</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        @elseif ($key === 'trial_balance')
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Net</th>
                            </tr>
                        @elseif ($key === 'profit_loss')
                            <tr>
                                <th>Particulars</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        @elseif ($key === 'balance_sheet')
                            <tr>
                                <th colspan="2" class="text-center">Assets</th>
                                <th colspan="2" class="text-center">Liabilities & Equity</th>
                            </tr>
                        @elseif ($key === 'party_ledger')
                            <tr>
                                <th>Date</th>
                                <th>Party</th>
                                <th>Voucher</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        @elseif ($key === 'receivables')
                            <tr>
                                <th>Customer</th>
                                <th class="text-end">Total Receivable</th>
                                <th class="text-end">0–30 Days</th>
                                <th class="text-end">31–60 Days</th>
                                <th class="text-end">61–90 Days</th>
                                <th class="text-end">&gt;90 Days</th>
                            </tr>
                        @elseif ($key === 'payables')
                            <tr>
                                <th>Vendor</th>
                                <th class="text-end">Total Payable</th>
                                <th class="text-end">0–30 Days</th>
                                <th class="text-end">31–60 Days</th>
                                <th class="text-end">61–90 Days</th>
                                <th class="text-end">&gt;90 Days</th>
                            </tr>
                        @elseif ($key === 'cash_book')
                            <tr>
                                <th>Date</th>
                                <th>Particulars</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        @elseif ($key === 'bank_book')
                            <tr>
                                <th>Date</th>
                                <th>Particulars</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        @elseif ($key === 'journal_book')
                            <tr>
                                <th>Date</th>
                                <th>Voucher</th>
                                <th>Debit Account</th>
                                <th>Credit Account</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        @elseif ($key === 'expense_analysis')
                            <tr>
                                <th>Expense Head</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        @elseif ($key === 'cash_flow')
                            <tr>
                                <th>Activity</th>
                                <th class="text-end">Inflows</th>
                                <th class="text-end">Outflows</th>
                                <th class="text-end">Net Flow</th>
                            </tr>
                        @endif
                    </thead>

                    <tbody>
                        @php
                            $reportData = $reports[$key] ?? [];
                            if ($reportData instanceof \Illuminate\Support\Collection) {
                                $reportData = $reportData->toArray();
                            }
                        @endphp

                        @forelse ($reportData as $row)
                            <tr>
                                @if ($key === 'general_ledger')
                                    <td>{{ $row['date'] }}</td>
                                    <td>{{ $row['voucher'] }}</td>
                                    <td>{{ $row['account'] }}</td>
                                    <td class="text-end">{{ $row['debit'] }}</td>
                                    <td class="text-end">{{ $row['credit'] }}</td>
                                    <td class="text-end fw-bold {{ str_starts_with((string)$row['balance'], '-') ? 'text-danger' : 'text-success' }}">
                                        {{ $row['balance'] }}
                                    </td>

                                @elseif ($key === 'trial_balance')
                                    <td>{{ $row['account'] }}</td>
                                    <td class="text-end">{{ $row['debit'] }}</td>
                                    <td class="text-end">{{ $row['credit'] }}</td>
                                    <td class="text-end fw-bold {{ str_starts_with((string)$row['net'], '-') ? 'text-danger' : '' }}">
                                        {{ $row['net'] }}
                                    </td>

                                @elseif ($key === 'profit_loss')
                                    <td>{{ $row['particulars'] }}</td>
                                    <td class="text-end fw-bold {{ str_starts_with((string)$row['amount'], '-') ? 'text-danger' : '' }}">
                                        {{ $row['amount'] }}
                                    </td>

                                @elseif ($key === 'balance_sheet')
                                    <td>{{ $row['asset'] ?? '' }}</td>
                                    <td class="text-end text-muted">{{ $row['asset_amt'] ?? '' }}</td>
                                    <td>{{ $row['liab'] ?? '' }}</td>
                                    <td class="text-end text-muted">{{ $row['liab_amt'] ?? '' }}</td>

                                @elseif ($key === 'party_ledger')
                                    <td>{{ $row['date'] }}</td>
                                    <td>{{ $row['party'] }}</td>
                                    <td><small>{{ $row['voucher'] }}</small></td>
                                    <td class="text-end">{{ $row['debit'] }}</td>
                                    <td class="text-end">{{ $row['credit'] }}</td>
                                    <td class="text-end fw-bold {{ str_starts_with((string)$row['balance'], '-') ? 'text-danger' : 'text-success' }}">
                                        {{ $row['balance'] }}
                                    </td>

                                @elseif ($key === 'receivables')
                                    <td>{{ $row['customer'] }}</td>
                                    <td class="text-end fw-bold">{{ $row['total_receivable'] }}</td>
                                    <td class="text-end">{{ $row['0_30'] }}</td>
                                    <td class="text-end">{{ $row['31_60'] }}</td>
                                    <td class="text-end">{{ $row['61_90'] }}</td>
                                    <td class="text-end">{{ $row['over_90'] }}</td>

                                @elseif ($key === 'payables')
                                    <td>{{ $row['vendor'] }}</td>
                                    <td class="text-end fw-bold">{{ $row['total_payable'] }}</td>
                                    <td class="text-end">{{ $row['0_30'] }}</td>
                                    <td class="text-end">{{ $row['31_60'] }}</td>
                                    <td class="text-end">{{ $row['61_90'] }}</td>
                                    <td class="text-end">{{ $row['over_90'] }}</td>

                                @elseif ($key === 'cash_book')
                                    <td>{{ $row['date'] }}</td>
                                    <td><small>{{ $row['particulars'] }}</small></td>
                                    <td class="text-end">{{ $row['debit'] }}</td>
                                    <td class="text-end">{{ $row['credit'] }}</td>
                                    <td class="text-end fw-bold {{ str_starts_with((string)$row['balance'], '-') ? 'text-danger' : 'text-success' }}">
                                        {{ $row['balance'] }}
                                    </td>

                                @elseif ($key === 'bank_book')
                                    <td>{{ $row['date'] }}</td>
                                    <td><small>{{ $row['bank'] }}</small></td>
                                    <td class="text-end">{{ $row['debit'] }}</td>
                                    <td class="text-end">{{ $row['credit'] }}</td>
                                    <td class="text-end fw-bold {{ str_starts_with((string)$row['balance'], '-') ? 'text-danger' : 'text-success' }}">
                                        {{ $row['balance'] }}
                                    </td>

                                @elseif ($key === 'journal_book')
                                    <td>{{ $row['date'] }}</td>
                                    <td>{{ $row['voucher'] }}</td>
                                    <td>{{ $row['dr_account'] }}</td>
                                    <td>{{ $row['cr_account'] }}</td>
                                    <td class="text-end">{{ $row['amount'] }}</td>

                                @elseif ($key === 'expense_analysis')
                                    <td>{{ $row['expense_head'] }}</td>
                                    <td class="text-end">{{ $row['amount'] }}</td>

                                @elseif ($key === 'cash_flow')
                                    <td>{{ $row['activity'] }}</td>
                                    <td class="text-end">{{ $row['inflows'] }}</td>
                                    <td class="text-end">{{ $row['outflows'] }}</td>
                                    <td class="text-end fw-bold {{ str_starts_with((string)$row['net flow'], '-') ? 'text-danger' : 'text-success' }}">
                                        {{ $row['net flow'] }}
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">
                                    @if ($key === 'general_ledger' && !$accountId)
                                        Please select an account from the filter above.
                                    @else
                                        No data found for the selected date range.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
        @endforeach

    </div>
</div>
@endsection