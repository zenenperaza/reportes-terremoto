<?php

namespace App\Support;

use App\Models\Report;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemNotifications
{
    public const PERMISSION = 'Recibir notificaciones';

    public static function allowed(User $user): bool
    {
        $current = $user->fresh();
        return $current && $current->is_active && $current->can(self::PERMISSION);
    }

    public static function login(User $actor): void
    {
        self::record($actor, 'login');
    }

    public static function reportChange(User $actor, Report $report, string $action): void
    {
        if (in_array($action, ['created', 'updated', 'deleted'], true)) {
            self::record($actor, 'record_'.$action, $report->user_id);
        }
    }

    private static function record(User $actor, string $kind, ?int $ownerId = null): void
    {
        // Only called after successful writes/authentication. Rows share the same
        // database transaction, so a surrounding rollback also removes the notices.
        try {
            if (! Schema::hasTable('system_notifications')) return;
            $actorGroups = $actor->activeNotificationGroupIds();
            $recipients = User::query()->where('is_active', true)->whereKeyNot($actor->id)
                ->permission(self::PERMISSION)->get();
            $rows = [];
            foreach ($recipients as $recipient) {
                $eligible = $kind === 'login' ? $recipient->isAdministrator()
                    : self::teamEligible($recipient, $actorGroups, $ownerId);
                if ($eligible) {
                    $rows[] = ['user_id' => $recipient->id, 'actor_id' => $actor->id,
                        'report_owner_id' => $ownerId, 'kind' => $kind, 'created_at' => now()];
                }
            }
            foreach (array_chunk($rows, 200) as $chunk) DB::table('system_notifications')->insert($chunk);
        } catch (Throwable $exception) {
            // A notification failure must not undo a saved record or a valid login.
            Log::warning('No fue posible generar un aviso del sistema.', ['type' => $kind]);
        }
    }

    private static function teamEligible(User $recipient, array $actorGroups, ?int $ownerId): bool
    {
        if ($recipient->isAdministrator() || ! in_array($recipient->role, ['reporter', 'coordinator'], true)
            || ! $ownerId || ! array_intersect($actorGroups, $recipient->activeNotificationGroupIds())) return false;

        // The actor's team alone must never reveal a different team's records.
        return $recipient->canViewReport(new Report(['user_id' => $ownerId]));
    }

    public static function pending(User $user): array
    {
        $empty = ['notifications' => [], 'notification_count' => 0,
            'can_receive_notifications' => self::allowed($user)];
        if (! $empty['can_receive_notifications'] || ! Schema::hasTable('system_notifications')) return $empty;

        $query = SystemNotification::query()->where('user_id', $user->id)->whereNull('read_at')
            ->where('created_at', '>=', now()->subDay());
        if ($user->isAdministrator()) {
            $query->where('kind', 'login');
        } elseif (in_array($user->role, ['reporter', 'coordinator'], true)) {
            $groups = $user->activeNotificationGroupIds();
            $members = User::query()->whereHas('userGroups', fn ($q) => $q->whereIn('user_groups.id', $groups)
                ->where('user_groups.is_active', true))->pluck('users.id')->push($user->id)->unique()->all();
            $query->whereIn('kind', ['record_created', 'record_updated', 'record_deleted'])
                ->whereIn('actor_id', $members)->where('actor_id', '!=', $user->id)->whereIn('report_owner_id', $members);
        } else {
            return $empty;
        }
        $count = (clone $query)->count();
        $items = $query->with('actor')->orderBy('id')->limit(20)->get()->map(function (SystemNotification $notice): array {
            $name = $notice->actor?->name ?? 'Un usuario';
            [$title, $body] = match ($notice->kind) {
                'login' => ['Acceso al sistema', $name.' inició sesión en SIA.'],
                'record_created' => ['Registro agregado por su equipo', $name.' agregó un registro.'],
                'record_updated' => ['Registro actualizado por su equipo', $name.' actualizó un registro o sus beneficiarios.'],
                default => ['Eliminación realizada por su equipo', $name.' eliminó un registro o un beneficiario.'],
            };
            return ['id' => (string) $notice->id, 'kind' => $notice->kind, 'title' => $title,
                'body' => $body, 'created_at' => $notice->created_at->utc()->toIso8601String()];
        })->values()->all();

        return ['notifications' => $items, 'notification_count' => $count, 'can_receive_notifications' => true];
    }
}
