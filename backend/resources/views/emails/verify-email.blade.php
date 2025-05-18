<x-mail::message>
# Email Verification

Hello {{ $user->nom }} {{ $user->prenom }},

Thank you for registering! Please click the button below to verify your email address.

<x-mail::button :url="$verificationUrl">
Verify Email Address
</x-mail::button>

If you did not create an account, no further action is required.

Regards,<br>
{{ config('app.name') }}

<x-slot:subcopy>
If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser: <span class="break-all">[{{ $verificationUrl }}]({{ $verificationUrl }})</span>
</x-slot:subcopy>
</x-mail::message>