<?php

namespace App\Console\Commands;

use App\Models\CompanyActivity;
use Illuminate\Console\Command;

class PruneCompanyActivities extends Command
{
    protected $signature = 'activities:prune
                            {--days=90 : Eliminar actividades con más de estos días}
                            {--pretend : Mostrar cuántos registros se eliminarían sin borrarlos}';

    protected $description = 'Eliminar registros antiguos de actividad de la empresa';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if ($days === false || $days < 1) {
            $this->error('La opción --days debe ser un número entero positivo.');

            return self::FAILURE;
        }

        $activities = CompanyActivity::query()
            ->where('created_at', '<', now()->subDays($days));
        $count = (clone $activities)->count();

        if ($this->option('pretend')) {
            $this->info("Se eliminarían {$count} actividades.");

            return self::SUCCESS;
        }

        $deleted = $activities->delete();
        $this->info("Se eliminaron {$deleted} actividades antiguas.");

        return self::SUCCESS;
    }
}
