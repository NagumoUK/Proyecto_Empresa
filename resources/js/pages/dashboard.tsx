import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CheckCircle2,
    Cloud,
    CloudDrizzle,
    CloudFog,
    CloudLightning,
    CloudRain,
    CloudSnow,
    CloudSun,
    Droplets,
    Plus,
    Radio,
    Sun,
    Users,
    Wind,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useWorkspacePresence } from '@/hooks/use-workspace-presence';
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

type WeatherItem = {
    location: string;
    temperatureC: number;
    feelsLikeC: number;
    humidity: number;
    windKph: number;
    weatherCode: number;
    observedAt: string;
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
    weather: WeatherItem | null;
};

const projectStatusLabels: Record<string, string> = {
    planning: 'Planificación',
    active: 'En curso',
    on_hold: 'En pausa',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

function weatherDescription(code: number): {
    label: string;
    icon: typeof Sun;
} {
    if (code === 0) {
        return { label: 'Despejado', icon: Sun };
    }

    if ([1, 2].includes(code)) {
        return { label: 'Parcialmente nublado', icon: CloudSun };
    }

    if (code === 3) {
        return { label: 'Nublado', icon: Cloud };
    }

    if ([45, 48].includes(code)) {
        return { label: 'Niebla', icon: CloudFog };
    }

    if ([51, 53, 55, 56, 57].includes(code)) {
        return { label: 'Llovizna', icon: CloudDrizzle };
    }

    if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) {
        return { label: 'Lluvia', icon: CloudRain };
    }

    if ([71, 73, 75, 77, 85, 86].includes(code)) {
        return { label: 'Nieve', icon: CloudSnow };
    }

    if ([95, 96, 97, 99].includes(code)) {
        return { label: 'Tormenta', icon: CloudLightning };
    }

    return { label: 'Condiciones actuales', icon: Cloud };
}

export default function Dashboard({
    activities: initialActivities,
    metrics,
    projectStatuses,
    projects,
    weather,
}: DashboardProps) {
    const [activities, setActivities] = useState(initialActivities);
    const [connectionStatus, setConnectionStatus] = useState('connecting');
    const form = useForm({ description: '' });
    const { auth } = usePage().props;
    const { onlineUsers, typingUsers, status, notifyTyping } =
        useWorkspacePresence(auth.user?.id);
    const currentWeather = weather
        ? weatherDescription(weather.weatherCode)
        : null;

    useEffect(() => {
        setActivities(initialActivities);
    }, [initialActivities]);

    useEffect(() => {
        const echo = window.Echo;
        const channel = echo.private('activities');

        channel.listen(
            '.activity.created',
            ({ activity }: ActivityBroadcast) => {
                setActivities((current) =>
                    [
                        activity,
                        ...current.filter((item) => item.id !== activity.id),
                    ].slice(0, 8),
                );
            },
        );

        const updateConnectionStatus = (state: { current: string }) => {
            setConnectionStatus(state.current);
        };

        const connection = echo.connector.ably.connection;

        setConnectionStatus(connection.state);
        connection.on(updateConnectionStatus);

        return () => {
            channel.stopListening('.activity.created');
            connection.off(updateConnectionStatus);
            echo.leaveChannel('private:activities');
        };
    }, []);

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
            <Head title="Inicio" />
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
                                    onChange={(event) => {
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        );
                                        notifyTyping();
                                    }}
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
                            {typingUsers.length > 0 && (
                                <p
                                    className="text-xs text-sky-700 dark:text-sky-300"
                                    aria-live="polite"
                                >
                                    {typingUsers
                                        .map((user) => user.name)
                                        .join(', ')}{' '}
                                    {typingUsers.length === 1
                                        ? 'está escribiendo...'
                                        : 'están escribiendo...'}
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

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                            <CardHeader className="border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-base">
                                            Radar del equipo
                                        </CardTitle>
                                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            Compañeros conectados ahora
                                        </p>
                                    </div>
                                    <span className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                        <span className="size-2 animate-pulse rounded-full bg-emerald-500" />
                                        {onlineUsers.length}{' '}
                                        {onlineUsers.length === 1
                                            ? 'colega'
                                            : 'colegas'}{' '}
                                        en línea
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="px-5 py-5">
                                {status === 'error' ? (
                                    <p className="text-sm text-rose-600 dark:text-rose-400">
                                        No se pudo conectar al espacio en vivo.
                                    </p>
                                ) : onlineUsers.length > 0 ? (
                                    <div className="flex flex-wrap gap-3">
                                        {onlineUsers.map((user) => (
                                            <div
                                                key={user.id}
                                                className="flex items-center gap-2 rounded-full bg-slate-100 py-1 pr-3 pl-1 dark:bg-slate-800"
                                            >
                                                <span className="flex size-8 items-center justify-center rounded-full bg-sky-600 text-xs font-semibold text-white">
                                                    {user.name
                                                        .trim()
                                                        .split(/\s+/)
                                                        .slice(0, 2)
                                                        .map((part) =>
                                                            part.charAt(0),
                                                        )
                                                        .join('')
                                                        .toUpperCase()}
                                                </span>
                                                <span className="text-sm text-slate-700 dark:text-slate-200">
                                                    {user.name}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                ) : status === 'connecting' ? (
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Conectando con el equipo...
                                    </p>
                                ) : (
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Eres el único conectado por ahora.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                            <CardHeader className="border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-base">
                                            Clima actual
                                        </CardTitle>
                                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            {weather?.location ?? 'Lima, Perú'}
                                        </p>
                                    </div>
                                    {weather && currentWeather && (
                                        <div className="flex items-center gap-2 text-sky-600 dark:text-sky-300">
                                            <currentWeather.icon className="size-8" />
                                            <span className="text-3xl font-semibold">
                                                {Math.round(
                                                    weather.temperatureC,
                                                )}
                                                °
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="px-5 py-5">
                                {weather && currentWeather ? (
                                    <div>
                                        <p className="text-sm font-medium text-slate-800 dark:text-slate-100">
                                            {currentWeather.label}
                                        </p>
                                        <div className="mt-4 grid grid-cols-3 gap-3 text-xs text-slate-500 dark:text-slate-400">
                                            <span className="flex items-center gap-1.5">
                                                <CloudSun className="size-4" />
                                                Sensación{' '}
                                                {Math.round(weather.feelsLikeC)}
                                                °C
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <Droplets className="size-4" />
                                                {weather.humidity}% humedad
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <Wind className="size-4" />
                                                {Math.round(
                                                    weather.windKph,
                                                )}{' '}
                                                km/h
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        El clima no está disponible en este
                                        momento.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
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
                                        : connectionStatus === 'connecting'
                                          ? 'Conectando con Ably...'
                                          : 'Ably sin conexión'}
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
            title: 'Inicio',
            href: dashboard(),
        },
    ],
};
