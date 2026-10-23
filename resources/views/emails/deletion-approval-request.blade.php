<h1>Solicitud de eliminación pendiente</h1>

<p>{{ $requesterName }} solicita eliminar el siguiente registro:</p>

<p><strong>Registro:</strong> {{ $entityType }} — {{ $entityName }}</p>

@if ($details !== [])
    <h2>Detalles</h2>
    <ul>
        @foreach ($details as $label => $value)
            <li><strong>{{ $label }}:</strong> {{ $value }}</li>
        @endforeach
    </ul>
@endif

<p>Inicia sesión con una cuenta de administrador para revisar y aprobar o rechazar la solicitud:</p>

<p><a href="{{ $approvalUrl }}">Revisar solicitud</a></p>

<p>El enlace no elimina el registro por sí mismo y requiere iniciar sesión como administrador.</p>
