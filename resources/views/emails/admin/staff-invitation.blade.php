<x-mail::message>
# You have been invited to Vyomika Atelier

Hello {{ $invitation->name }},

You have been invited as **{{ \App\Support\AdminRole::labels()[$invitation->admin_role] ?? 'Staff' }}**.

Use the secure link below to set your password. The link expires on {{ $invitation->expires_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}.

<x-mail::button :url="$acceptUrl">
Set up staff account
</x-mail::button>

If you were not expecting this invitation, ignore this email.

Thanks,<br>
Vyomika Atelier
</x-mail::message>
