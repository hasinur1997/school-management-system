<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Card {{ $student->admission_no }}</title>
    @include('pdf.partials.id-card-styles')
</head>
<body>
    @include('pdf.partials.id-card-body')
</body>
</html>
