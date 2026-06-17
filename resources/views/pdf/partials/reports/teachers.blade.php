{{-- Teacher report: total, by-status, attendance summary over the window. --}}
<div class="summary">
    <div class="item">
        <div class="val">{{ $data['total'] }}</div>
        <div class="cap">Total Teachers</div>
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

<h2>Attendance Summary</h2>
<table class="data">
    <thead>
        <tr><th>Status</th><th class="num">Count</th></tr>
    </thead>
    <tbody>
        @foreach ($data['attendance'] as $status => $count)
            <tr><td>{{ ucfirst($status) }}</td><td class="num">{{ $count }}</td></tr>
        @endforeach
    </tbody>
</table>
