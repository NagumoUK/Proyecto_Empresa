import { Head, Link, useForm } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import {
    BriefcaseBusiness,
    CheckCircle2,
    Plus,
    Radio,
    Users,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type ActivityItem = {
    id: number;
    userName: string;
    description: string;
    time: string;
};

type ActivityBroadcast = {
    activity: ActivityItem;
};

type DashboardProps = {
    activities: ActivityItem[];
    metrics: {
        clients: number;
        activeProjects: number;
        planningProjects: number;
        completedProjects: number;
    };
    projectStatuses: Record<string, number>;
    projects: {
        id: number;
        name: string;
        client: string;
        status: string;
        endDate: string | null;
    }[];
};

const projectStatusLabels: Record<string, string> = {
    planning: 'Planificación',
    active: 'En curso',
    on_hold: 'En pausa',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

export default function Dashboard({
    activities: initialActivities,
    metrics,
    projectStatuses,
    projects,
}: DashboardProps) {
    const [activities, setActivities] = useState(initialActivities);
    const form = useForm({ description: '' });
    const connectionStatus = useConnectionStatus();

    useEffect(() => {
        setActivities(initialActivities);
    }, [initialActivities]);

    useEcho<ActivityBroadcast>(
        'activities',
        '.activity.created',
        ({ activity }) => {
            setActivities((current) =>
                [
                    activity,
                    ...current.filter((item) => item.id !== activity.id),
                ].slice(0, 8),
            );
        },
    );

    function submitActivity(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/activities', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    const metricCards = [
        {
            label: 'Clientes registrados',
            value: metrics.clients,
            icon: Users,
            tone: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
            detail: 'En el directorio',
        },
        {
            label: 'Proyectos en curso',
            value: metrics.activeProjects,
            icon: BriefcaseBusiness,
            tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
            detail: 'Con estado activo',
        },
        {
            label: 'En planificación',
            value: metrics.planningProjects,
            icon: CheckCircle2,
            tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
            detail: 'Por iniciar',
        },
        {
            label: 'Completados',
            value: metrics.completedProjects,
            icon: CheckCircle2,
            tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
            detail: 'Finalizados',
        },
    ];
    const statusItems = Object.entries(projectStatusLabels).map(
        ([status, label]) => ({
            label,
            count: projectStatuses[status] ?? 0,
            tone: {
                planning: 'bg-violet-500',
                active: 'bg-sky-500',
                on_hold: 'bg-amber-500',
                completed: 'bg-emerald-500',
                cancelled: 'bg-slate-400',
            }[status],
        }),
    );
    const totalProjects = statusItems.reduce(
        (total, item) => total + item.count,
        0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                                Resumen operativo
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                Buenos días, equipo
                            </h1>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Aquí tienes el estado de tu empresa hoy.
                            </p>
                        </div>
                        <form
                            onSubmit={submitActivity}
                            className="flex w-full flex-col gap-2 sm:w-auto sm:min-w-80"
                        >
                            <div className="flex gap-2">
                                <input
                                    aria-label="Describe la actividad"
                                    className="h-10 min-w-0 flex-1 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    maxLength={280}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Registrar una actividad..."
                                    value={form.data.description}
                                />
                                <button
                                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                                    disabled={
                                        form.processing ||
                                        !form.data.description.trim()
                                    }
                                    type="submit"
                                >
                                    <Plus className="size-4" />
                                    Añadir
                                </button>
                            </div>
                            {form.errors.description && (
                                <p className="text-sm text-rose-600 dark:text-rose-400">
                                    {form.errors.description}
                                </p>
                            )}
                        </form>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {metricCards.map((metric) => {
                            const Icon = metric.icon;

                            return (
                                <Card
                                    key={metric.label}
                                    className="gap-4 border-slate-200/80 py-5 shadow-sm dark:border-slate-800"
                                >
                                    <CardContent className="px-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                                    {metric.label}
                                                </p>
                                                <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                                    {metric.value}
                                                </p>
                                            </div>
                                            <span
                                                className={`rounded-md p-2.5 ${metric.tone}`}
                                            >
                                                <Icon className="size-5" />
                                            </span>
                                        </div>
                                        <p className="mt-4 text-xs text-slate-500 dark:text-slate-400">
                                            {metric.detail}
                                        </p>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1.45fr_1fr]">
                        <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                            <CardHeader className="border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                                <div>
                                    <CardTitle className="text-base">
                                        Estado de proyectos
                                    </CardTitle>
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {totalProjects} proyectos en el
                                        portafolio
                                    </p>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 px-5 py-6">
                                {statusItems.map((item) => (
                                    <div key={item.label}>
                                        <div className="mb-2 flex items-center justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-300">
                                                {item.label}
                                            </span>
                                            <span className="font-medium text-slate-900 dark:text-white">
                                                {item.count}
                                            </span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                className={`h-full rounded-full ${item.tone}`}
                                                style={{
                                                    width: `${totalProjects ? (item.count / totalProjects) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                                {totalProjects === 0 && (
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Registra proyectos para ver su
                                        distribución.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                            <CardHeader className="flex-row items-center justify-between border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                                <div>
                                    <CardTitle className="text-base">
                                        Actividad reciente
                                    </CardTitle>
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        Últimos movimientos del equipo
                                    </p>
                                </div>
                                <div
                                    className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"
                                    aria-live="polite"
                                >
                                    <Radio
                                        className={`size-4 ${connectionStatus === 'connected' ? 'text-emerald-500' : 'text-amber-500'}`}
                                    />
                                    {connectionStatus === 'connected'
                                        ? 'En vivo'
                                        : 'Reverb sin conexión'}
                                </div>
                            </CardHeader>
                            <CardContent className="px-5 py-2">
                                {activities.length > 0 ? (
                                    activities.map((item) => {
                                        const initials = item.userName
                                            .trim()
                                            .split(/\s+/)
                                            .slice(0, 2)
                                            .map((part) => part.charAt(0))
                                            .join('')
                                            .toUpperCase();

                                        return (
                                            <div
                                                key={item.id}
                                                className="flex gap-3 border-b border-slate-100 py-4 last:border-0 dark:border-slate-800"
                                            >
                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-semibold text-white">
                                                    {initials}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="text-sm leading-5 text-slate-700 dark:text-slate-200">
                                                        <span className="font-semibold">
                                                            {item.userName}
                                                        </span>{' '}
                                                        {item.description}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-400">
                                                        {item.time}
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })
                                ) : (
                                    <p className="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                        Todavía no hay actividad registrada.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                        <CardHeader className="flex-row items-center justify-between border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                            <div>
                                <CardTitle className="text-base">
                                    Proyectos destacados
                                </CardTitle>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Información actual registrada en el sistema
                                </p>
                            </div>
                            <Link
                                href="/management/proyectos"
                                className="inline-flex items-center gap-1 text-sm font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300"
                            >
                                Ver todos
                            </Link>
                        </CardHeader>
                        <CardContent className="overflow-x-auto px-0 py-0">
                            <table className="w-full min-w-[620px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-900/60 dark:text-slate-400">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Proyecto
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Cliente
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Entrega
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Estado
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {projects.map((project) => (
                                        <tr
                                            key={project.id}
                                            className="transition hover:bg-slate-50/80 dark:hover:bg-slate-900/50"
                                        >
                                            <td className="px-5 py-4 font-medium text-slate-800 dark:text-slate-200">
                                                <Link
                                                    className="hover:text-sky-700 dark:hover:text-sky-300"
                                                    href={`/management/proyectos/${project.id}`}
                                                >
                                                    {project.name}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-4 text-slate-500 dark:text-slate-400">
                                                {project.client}
                                            </td>
                                            <td className="px-5 py-4 text-slate-500 dark:text-slate-400">
                                                {project.endDate || 'Sin fecha'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    {projectStatusLabels[
                                                        project.status
                                                    ] ?? project.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                    {projects.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-5 py-10 text-center text-slate-500 dark:text-slate-400"
                                                colSpan={4}
                                            >
                                                Todavía no hay proyectos
                                                registrados.{' '}
                                                <Link
                                                    className="font-medium text-sky-700 hover:underline dark:text-sky-300"
                                                    href="/management/proyectos"
                                                >
                                                    Crear un proyecto
                                                </Link>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
