<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Review Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            background: #004D40;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header .subtitle {
            font-size: 12px;
            opacity: 0.9;
        }
        .meta-info {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .meta-info table {
            width: 100%;
        }
        .meta-info td {
            padding: 3px 10px 3px 0;
        }
        .meta-info .label {
            font-weight: bold;
            color: #666;
            width: 120px;
        }
        .summary-box {
            background: #E8F5E9;
            border: 1px solid #4CAF50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .summary-box h3 {
            color: #2E7D32;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 10px;
        }
        .summary-value {
            font-size: 20px;
            font-weight: bold;
            color: #004D40;
        }
        .summary-label {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }
        .month-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .month-header {
            background: #004D40;
            color: white;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: bold;
        }
        .month-stats {
            background: #E0F2F1;
            padding: 8px 15px;
            font-size: 10px;
            color: #00695C;
        }
        table.transactions {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        table.transactions th {
            background: #f5f5f5;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #ddd;
        }
        table.transactions td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        table.transactions tr:nth-child(even) {
            background: #fafafa;
        }
        .date-col { width: 70px; }
        .amount-col { width: 90px; text-align: right; }
        .category-col { width: 100px; }
        .description-col { }
        .question-col { width: 200px; }
        .amount {
            font-weight: bold;
            text-align: right;
        }
        .amount.expense {
            color: #C62828;
        }
        .amount.income {
            color: #2E7D32;
        }
        .category-badge {
            display: inline-block;
            background: #E3F2FD;
            color: #1565C0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
        }
        .question {
            font-style: italic;
            color: #666;
            font-size: 10px;
            margin-top: 5px;
            padding: 5px;
            background: #FFF8E1;
            border-left: 3px solid #FFC107;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #004D40;
            font-size: 10px;
            color: #666;
        }
        .footer-note {
            margin-top: 15px;
            padding: 10px;
            background: #FFF3E0;
            border: 1px solid #FF9800;
            border-radius: 4px;
        }
        .footer-note strong {
            color: #E65100;
        }
        .page-break {
            page-break-after: always;
        }
        .decision-box {
            margin-top: 20px;
            border: 2px solid #004D40;
            padding: 15px;
        }
        .decision-box h4 {
            color: #004D40;
            margin-bottom: 10px;
        }
        .decision-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .decision-check {
            display: table-cell;
            width: 30px;
            vertical-align: middle;
        }
        .decision-checkbox {
            width: 15px;
            height: 15px;
            border: 1px solid #333;
            display: inline-block;
        }
        .decision-label {
            display: table-cell;
            vertical-align: middle;
        }
        .no-items {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Accountant Review Report</h1>
        <div class="subtitle">Transactions Flagged for Professional Review</div>
    </div>

    <!-- Meta Information -->
    <div class="meta-info">
        <table>
            <tr>
                <td class="label">Prepared For:</td>
                <td>{{ $user->name ?? 'Unknown User' }}</td>
                <td class="label">Generated:</td>
                <td>{{ $generatedAt }}</td>
            </tr>
            <tr>
                <td class="label">Email:</td>
                <td>{{ $user->email ?? '' }}</td>
                <td class="label">Status Filter:</td>
                <td>{{ ucfirst($status) }}</td>
            </tr>
            <tr>
                <td class="label">Date Range:</td>
                <td colspan="3">{{ $fromDate }} to {{ $toDate }}</td>
            </tr>
        </table>
    </div>

    <!-- Summary Box -->
    <div class="summary-box">
        <h3>Summary</h3>
        <table width="100%">
            <tr>
                <td width="33%" style="text-align: center; padding: 10px;">
                    <div class="summary-value">{{ $totals['total_flagged'] }}</div>
                    <div class="summary-label">Total Items</div>
                </td>
                <td width="33%" style="text-align: center; padding: 10px;">
                    <div class="summary-value">R {{ number_format($totals['total_amount'], 2) }}</div>
                    <div class="summary-label">Total Amount</div>
                </td>
                <td width="33%" style="text-align: center; padding: 10px;">
                    <div class="summary-value">R {{ number_format($totals['potential_tax_savings'], 2) }}</div>
                    <div class="summary-label">Potential Tax Savings (39.1%)</div>
                </td>
            </tr>
        </table>
    </div>

    @if($groupedByMonth->isEmpty())
        <div class="no-items">
            No transactions flagged for review in the selected period.
        </div>
    @else
        <!-- Transactions by Month -->
        @foreach($groupedByMonth as $yearMonth => $monthData)
            <div class="month-section">
                <div class="month-header">
                    {{ $monthData['month_label'] }}
                </div>
                <div class="month-stats">
                    {{ $monthData['count'] }} item(s) | Total: R {{ number_format($monthData['total_amount'], 2) }}
                </div>
                <table class="transactions">
                    <thead>
                        <tr>
                            <th class="date-col">Date</th>
                            <th class="description-col">Description</th>
                            <th class="category-col">Category</th>
                            <th class="amount-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthData['transactions'] as $tx)
                            <tr>
                                <td class="date-col">{{ $tx->transaction_date->format('d M') }}</td>
                                <td class="description-col">
                                    {{ $tx->description }}
                                    @if($tx->accountant_question)
                                        <div class="question">
                                            <strong>Question:</strong> {{ $tx->accountant_question }}
                                        </div>
                                    @endif
                                </td>
                                <td class="category-col">
                                    @if($tx->category)
                                        <span class="category-badge">{{ $tx->category->name }}</span>
                                    @else
                                        <span style="color: #999;">Uncategorized</span>
                                    @endif
                                </td>
                                <td class="amount {{ $tx->amount < 0 ? 'expense' : 'income' }}">
                                    R {{ number_format(abs($tx->amount), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    <!-- Decision Section -->
    <div class="decision-box">
        <h4>Accountant Decision (For Each Item)</h4>
        <div class="decision-row">
            <span class="decision-check"><span class="decision-checkbox"></span></span>
            <span class="decision-label"><strong>Approve as Business Expense</strong> - Tax-deductible at 39.1% marginal rate</span>
        </div>
        <div class="decision-row">
            <span class="decision-check"><span class="decision-checkbox"></span></span>
            <span class="decision-label"><strong>Reject to Private</strong> - Not eligible for tax deduction</span>
        </div>
        <p style="margin-top: 15px; font-size: 10px; color: #666;">
            Please mark each transaction with your decision and return this report to the client.
            Approved items will reduce taxable income and automatically adjust tax reserves.
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <table width="100%">
            <tr>
                <td>PennyPilot Financial Management</td>
                <td style="text-align: right;">Page 1 of 1</td>
            </tr>
        </table>
        <div class="footer-note">
            <strong>Important:</strong> This report is generated for discussion purposes with your tax professional.
            Decisions made should comply with SARS regulations for business expense deductions.
            PennyPilot does not provide tax advice.
        </div>
    </div>
</body>
</html>
