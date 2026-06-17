<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Card {{ $student->admission_no }}</title>
    <style>
        @page { margin: 0; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1a1a1a; }
        .card { width: 100%; height: 100%; }
        .band { background: #0d3b66; color: #ffffff; text-align: center; padding: 8px 6px; }
        .band .school { font-size: 11px; font-weight: bold; margin: 0; }
        .band .sub { font-size: 6px; margin: 2px 0 0; color: #cfe0f1; }
        .id-title { background: #f4b942; color: #1a1a1a; text-align: center; font-size: 7px; font-weight: bold; letter-spacing: 2px; padding: 2px 0; }
        .photo-wrap { text-align: center; margin: 8px 0 6px; }
        .photo { width: 60px; height: 72px; border: 1px solid #0d3b66; object-fit: cover; }
        .photo-placeholder { width: 60px; height: 72px; border: 1px solid #0d3b66; background: #e6e6e6; display: inline-block; text-align: center; color: #999; font-size: 36px; line-height: 72px; }
        .name { text-align: center; font-size: 10px; font-weight: bold; margin: 0 0 6px; }
        table.fields { width: 100%; border-collapse: collapse; padding: 0 8px; }
        table.fields td { font-size: 7px; padding: 1px 6px; vertical-align: top; }
        table.fields td.label { color: #555; width: 42%; }
        table.fields td.value { font-weight: bold; }
        .validity { text-align: center; font-size: 6px; color: #555; margin-top: 6px; }
        .footer-band { background: #0d3b66; height: 6px; position: absolute; bottom: 0; left: 0; right: 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="band">
            <p class="school">{{ $branch->name }}</p>
            @if ($branch->address)<p class="sub">{{ $branch->address }}</p>@endif
        </div>
        <div class="id-title">STUDENT ID CARD</div>

        <div class="photo-wrap">
            @if ($photoPath)
                <img class="photo" src="{{ $photoPath }}" alt="photo">
            @else
                <span class="photo-placeholder">&#9787;</span>
            @endif
        </div>

        <p class="name">{{ $student->name_en }}</p>

        <table class="fields">
            <tr>
                <td class="label">Admission No.</td>
                <td class="value">{{ $student->admission_no }}</td>
            </tr>
            <tr>
                <td class="label">Class / Section</td>
                <td class="value">{{ $enrollment->schoolClass->name ?? '—' }}@if ($enrollment->section) / {{ $enrollment->section->name }}@endif</td>
            </tr>
            <tr>
                <td class="label">Roll No.</td>
                <td class="value">{{ $enrollment->roll_no ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Session</td>
                <td class="value">{{ $session->name }}</td>
            </tr>
        </table>

        <p class="validity">Valid until {{ $session->end_date->format('d M Y') }}</p>
        <div class="footer-band"></div>
    </div>
</body>
</html>
