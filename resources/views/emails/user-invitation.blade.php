<h1>Tu cuenta está lista</h1>

<p>Hola {{ $name }},</p>

<p>Se creó una cuenta para ti en {{ config('app.name') }} con el rol de {{ $role }}.</p>

<p>Para verificar tu correo y crear tu contraseña, usa este enlace seguro:</p>

<p><a href="{{ $setupUrl }}">Establecer contraseña</a></p>

<p>Si no esperabas esta invitación, puedes ignorar este correo.</p>
