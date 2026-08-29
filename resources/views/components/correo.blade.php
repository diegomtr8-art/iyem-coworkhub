{{-- Envoltura del layout de correo como componente, para escribir
     <x-correo> ... </x-correo> en cada mensaje. --}}
@include('emails.base', ['slot' => $slot, 'titulo' => $titulo ?? null, 'resumen' => $resumen ?? null])
