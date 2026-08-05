<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Evento;
use App\Models\Transmision;

class DashboardController extends Controller
{
    public function index()
    {
        // Estadísticas institucionales en tiempo real
        $totalUsuarios = User::count();
        $totalEventos = Evento::count();
        $eventosEnVivo = Evento::where('estado', 'en_vivo')->count();
        
        // CORREGIDO: Usamos count() general para evitar errores de columnas faltantes en transmisiones
        $transmisionesActivas = Transmision::count();

        // Listado reciente de eventos para la tabla administrativa
        $eventosRecientes = Evento::with(['organizador', 'facultad', 'categoria'])
                                  ->latest()
                                  ->take(5)
                                  ->get();

        return view('admin.dashboard', compact(
            'totalUsuarios', 
            'totalEventos', 
            'eventosEnVivo',
            'transmisionesActivas',
            'eventosRecientes'
        ));
    }
}