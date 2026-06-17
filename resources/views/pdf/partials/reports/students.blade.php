{{-- Student report: total, by-status, by-class head-count, new admissions in range. --}}
<div class="summary">
    <div class="item">
        <div class="val">{{ $data['total'] }}</div>
        <div class="cap">Total Students</div>
    </div>
    <div class="item">
        <div class="val">{{ $data['new_admissions'] }}</div>
        <div class="cap">New Admissions</div>
    </div>
</div>

<h2>By Status</h2>
<table class="data">
    <thead>
        <tr><th>Status</th><th class="num">Count</th></tr>
    </thead>
    <tbody>
        @foreach ($data['by_status'] as $status => $count)
            <tr><td>{{ ucfirst($status) }}</td><td class="num">{{ $count }}</td></tr>
        @endforeach
    </tbody>
</table>

<h2>By Class</h2>
<table class="data">
    <thead>
        <tr><th>Class</th><th class="num">Active Students</th></tr>
    </thead>
    <tbody>
        @forelse ($data['by_class'] as $row)
            <tr><td>{{ $row['class'] }}</td><td class="num">{{ $row['count'] }}</td></tr>
        @empty
            <tr><td colspan="2" class="empty">No active enrollments for the current session.</td></tr>
        @endforelse
    </tbody>
</table>
