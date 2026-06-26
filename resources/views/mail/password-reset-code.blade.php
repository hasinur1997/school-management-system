@component('mail::message')
# Password reset requested

Hi {{ $name }},

We received a request to reset the password for your {{ config('app.name') }} account. Use the code below to set a new password:

@component('mail::panel')
# {{ $code }}
@endcomponent

This code expires in **{{ $ttlMinutes }} minutes**. If you did not request a password reset, you can safely ignore this email — your password will remain unchanged.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
