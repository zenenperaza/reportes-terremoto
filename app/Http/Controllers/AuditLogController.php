<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('audit-logs.index', [
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = AuditLog::query();
        $total = (clone $query)->count();

        $this->applyFilters($query, $request);
        $filtered = (clone $query)->count();

        $columns = ['created_at', 'user_name', 'action', 'method', 'path', 'status_code', 'ip_address', 'duration_ms'];
        $orderIndex = (int) $request->input('order.0.column', 0);
        $direction = strtolower((string) $request->input('order.0.dir')) === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderIndex] ?? 'created_at';
        $length = min(max((int) $request->input('length', 25), 1), 100);
        $start = max((int) $request->input('start', 0), 0);

        $records = $query->orderBy($orderColumn, $direction)
            ->orderByDesc('id')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'created_at' => $log->created_at?->format('d/m/Y h:i:s A'),
                'user_name' => $log->user_name ?: 'Usuario eliminado',
                'user_email' => $log->user_email,
                'action' => $log->action,
                'method' => $log->method,
                'path' => $log->path,
                'route_name' => $log->route_name,
                'status_code' => $log->status_code,
                'ip_address' => $log->ip_address,
                'duration_ms' => $log->duration_ms,
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $records,
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json([
            'id' => $auditLog->id,
            'fecha' => $auditLog->created_at?->format('d/m/Y h:i:s A'),
            'usuario' => $auditLog->user_name,
            'correo' => $auditLog->user_email,
            'accion' => $auditLog->action,
            'metodo' => $auditLog->method,
            'ruta' => $auditLog->route_name,
            'direccion' => $auditLog->path,
            'resultado' => $auditLog->status_code,
            'duracion_ms' => $auditLog->duration_ms,
            'ip' => $auditLog->ip_address,
            'navegador' => $auditLog->user_agent,
            'datos' => $auditLog->request_payload,
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('method')) {
            $query->where('method', strtoupper((string) $request->input('method')));
        }
        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            $query->whereBetween('status_code', match ($status) {
                'success' => [200, 399],
                'client_error' => [400, 499],
                'server_error' => [500, 599],
                default => [100, 599],
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('to'));
        }
    }
}
