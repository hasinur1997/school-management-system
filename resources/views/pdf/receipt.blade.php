<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_no }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 13px; }
        .header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header .meta { font-size: 11px; color: #555; margin-top: 4px; }
        .title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 2px; margin-bottom: 16px; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details td { padding: 6px 4px; vertical-align: top; }
        table.details td.label { width: 35%; color: #555; }
        table.details td.value { font-weight: bold; }
        .amount-box { margin-top: 20px; border: 1px solid #1a1a1a; padding: 10px 14px; }
        .amount-box .amount { font-size: 20px; font-weight: bold; }
        .footer { margin-top: 40px; font-size: 11px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $payment->branch->name }}</h1>
        <div class="meta">
            @if ($payment->branch->address){{ $payment->branch->address }}@endif
            @if ($payment->branch->phone) &middot; {{ $payment->branch->phone }}@endif
        </div>
    </div>

    <div class="title">MONEY RECEIPT</div>

    <table class="details">
        <tr>
            <td class="label">Receipt No.</td>
            <td class="value">{{ $payment->receipt_no }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td class="value">{{ $payment->paid_at->toDayDateTimeString() }}</td>
        </tr>
        <tr>
            <td class="label">Student</td>
            <td class="value">{{ $payment->invoice->student->name_en }}</td>
        </tr>
        @if ($payment->invoice->enrollment)
            <tr>
                <td class="label">Class / Section</td>
                <td class="value">
                    {{ $payment->invoice->enrollment->schoolClass->name ?? '—' }}
                    @if ($payment->invoice->enrollment->section) / {{ $payment->invoice->enrollment->section->name }}@endif
                </td>
            </tr>
        @endif
        <tr>
            <td class="label">For</td>
            <td class="value">Monthly fee {{ $payment->invoice->month }}/{{ $payment->invoice->year }} ({{ $payment->invoice->invoice_no }})</td>
        </tr>
        <tr>
            <td class="label">Payment Method</td>
            <td class="value">{{ ucfirst($payment->method->value) }}</td>
        </tr>
        <tr>
            <td class="label">Collected By</td>
            <td class="value">{{ $payment->collector->name ?? '—' }}</td>
        </tr>
    </table>

    <div class="amount-box">
        <span class="label">Amount Paid:</span>
        <span class="amount">{{ $payment->amount }}</span>
    </div>

    <div class="footer">
        This is a system-generated receipt.
    </div>
</body>
</html>
