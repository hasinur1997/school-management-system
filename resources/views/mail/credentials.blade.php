@component('mail::message')
# Welcome, {{ $name }}

A {{ $role }} account has been created for you at {{ config('app.name') }}.

Use the credentials below to sign in. You can log in with either your email or your phone number:

@if ($email)
- **Login (email):** {{ $email }}
@endif
@if ($phone)
- **Login (phone):** {{ $phone }}
@endif
- **Temporary password:** {{ $password }}

@component('mail::button', ['url' => $loginUrl])
Log in
@endcomponent

For your security, please **change your password after your first login**.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
