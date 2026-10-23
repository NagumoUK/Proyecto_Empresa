<h1>Actividad en el sistema</h1>

<p>Se registró la siguiente acción en Limasoft:</p>

<p><strong>Acción:</strong> {{ $action }} {{ $entityType }}</p>
<p><strong>Registro:</strong> {{ $entityName }}</p>
<p><strong>Realizada por:</strong> {{ $actorName }}</p>

@if ($details !== [])
    <h2>Detalles</h2>
    <ul>
        @foreach ($details as $label => $value)
            <li><strong>{{ $label }}:</strong> {{ $value }}</li>
        @endforeach
    </ul>
@endif

<p>Este es un aviso automático del sistema.</p>
