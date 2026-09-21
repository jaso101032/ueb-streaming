@if ($evento->transmision && !in_array($evento->estado, ['finalizado', 'cancelado']))
    <form action="{{ route('streaming.finalizar', $evento) }}" method="POST" class="mt-3"
          onsubmit="if (!confirm('¿Finalizar la transmisión de este evento? Se cortará la señal y OBS no podrá reconectarse.')) return false; this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Finalizando…';">
        @csrf
        <button type="submit" class="inline-flex items-center rounded-xl bg-red-700 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-800 focus:ring-2 focus:ring-red-400 focus:ring-offset-2 disabled:opacity-50">■ Finalizar transmisión</button>
    </form>
@endif
