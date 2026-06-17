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
