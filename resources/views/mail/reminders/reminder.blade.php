<x-mail::message>
# {{ $message->greeting }}

@foreach ($message->lines as $line)
{{ $line }}

@endforeach
Saludos,<br>
{{ config('app.name') }}

<x-slot:subcopy>
Recibe este mensaje porque aceptó recibir recordatorios del consultorio. Si ya no desea recibirlos, avísenos y lo daremos de baja.
</x-slot:subcopy>
</x-mail::message>
