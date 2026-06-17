<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Transfer Certificate {{ $tc->tc_no }}</title>
    <style>
        @page { margin: 36px 48px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1a1a1a; font-size: 13px; }
        .header { text-align: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header .meta { font-size: 11px; color: #555; margin-top: 4px; }
        .title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 3px; margin: 8px 0 4px; }
        .tc-no { text-align: center; font-size: 11px; color: #555; margin-bottom: 18px; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.details td { padding: 6px 4px; vertical-align: top; }
        table.details td.label { width: 35%; color: #555; }
        table.details td.value { font-weight: bold; }
        .reason { margin: 16px 0; line-height: 1.6; }
        .reason .label { color: #555; }
        .signatures { width: 100%; margin-top: 64px; border-collapse: collapse; }
        .signatures td { width: 50%; text-align: center; font-size: 12px; padding-top: 6px; }
        .sign-line { border-top: 1px solid #1a1a1a; margin: 0 24px 4px; }
        .footer { margin-top: 36px; font-size: 10px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $branch->name }}</h1>
        <div class="meta">
            @if ($branch->address){{ $branch->address }}@endif
            @if ($branch->phone) &middot; {{ $branch->phone }}@endif
        </div>
    </div>

    <div class="title">TRANSFER CERTIFICATE</div>
    <div class="tc-no">No. {{ $tc->tc_no }}</div>

    <table class="details">
        <tr>
            <td class="label">Name (English)</td>
            <td class="value">{{ $student->name_en }}</td>
        </tr>
        <tr>
            <td class="label">Name (Bengali)</td>
            <td class="value">{{ $student->name_bn }}</td>
        </tr>
        <tr>
            <td class="label">Father's Name</td>
            <td class="value">{{ $student->father_name_en }}</td>
        </tr>
        <tr>
            <td class="label">Mother's Name</td>
            <td class="value">{{ $student->mother_name_en }}</td>
        </tr>
        <tr>
            <td class="label">Admission No.</td>
            <td class="value">{{ $student->admission_no }}</td>
        </tr>
        <tr>
            <td class="label">Class / Section</td>
            <td class="value">{{ $enrollment?->schoolClass?->name ?? '—' }}@if ($enrollment?->section) / {{ $enrollment->section->name }}@endif</td>
        </tr>
        <tr>
            <td class="label">Roll No.</td>
            <td class="value">{{ $enrollment?->roll_no ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Session</td>
            <td class="value">{{ $enrollment?->session?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date of Issue</td>
            <td class="value">{{ $tc->issue_date->format('d M Y') }}</td>
        </tr>
    </table>

    <p class="reason"><span class="label">Reason for transfer:</span> {{ $tc->reason }}</p>

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line"></div>
                Class Teacher
            </td>
            <td>
                <div class="sign-line"></div>
                Head of Institution
            </td>
        </tr>
    </table>

    <div class="footer">This is a computer-generated transfer certificate and is a legal record of the institution.</div>
</body>
</html>
