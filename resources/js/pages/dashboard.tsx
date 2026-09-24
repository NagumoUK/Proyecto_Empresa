import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    BriefcaseBusiness,
    CheckCircle2,
    ChevronRight,
    CircleDollarSign,
    Clock3,
    MoreHorizontal,
    Plus,
    Users,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

const metrics = [
    {
        label: 'Ingresos del mes',
        value: '$24,680',
        change: '+12.8%',
        detail: 'vs. mes anterior',
        icon: CircleDollarSign,
        tone: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    },
    {
        label: 'Clientes activos',
        value: '148',
        change: '+8.2%',
        detail: 'este mes',
        icon: Users,
        tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    },
    {
        label: 'Proyectos en curso',
        value: '26',
        change: '+4.5%',
        detail: 'vs. mes anterior',
        icon: BriefcaseBusiness,
        tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    },
    {
        label: 'Tareas pendientes',
        value: '37',
        change: '-6.4%',
        detail: 'esta semana',
        icon: CheckCircle2,
        tone: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
    },
];

const activity = [
    { initials: 'MC', name: 'María Cortés', action: 'actualizó el proyecto Web corporativa', time: 'Hace 12 min', color: 'bg-sky-600' },
    { initials: 'JR', name: 'Jorge Ramírez', action: 'creó una tarea para App móvil', time: 'Hace 38 min', color: 'bg-violet-600' },
    { initials: 'AL', name: 'Ana López', action: 'registró un nuevo cliente', time: 'Hace 1 h', color: 'bg-emerald-600' },
    { initials: 'PS', name: 'Pablo Silva', action: 'marcó una tarea como completada', time: 'Hace 2 h', color: 'bg-orange-600' },
];

const projects = [
    { name: 'Web corporativa', client: 'Grupo Andina', progress: 78, status: 'En curso', tone: 'bg-sky-500' },
    { name: 'App móvil', client: 'Logística Norte', progress: 54, status: 'En revisión', tone: 'bg-amber-500' },
    { name: 'Portal de clientes', client: 'Inversiones Delta', progress: 32, status: 'Planificación', tone: 'bg-violet-500' },
    { name: 'Automatización interna', client: 'Operaciones Sur', progress: 91, status: 'En curso', tone: 'bg-emerald-500' },
];

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 dark:bg-slate-950/40 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-sm font-medium text-sky-600 dark:text-sky-400">Resumen operativo</p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-3xl">Buenos días, equipo</h1>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Aquí tienes el estado de tu empresa hoy.</p>
                        </div>
                        <Link
                            href="#"
                            className="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                        >
                            <Plus className="size-4" />
                            Nueva actividad
                        </Link>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {metrics.map((metric) => {
                            const Icon = metric.icon;

                            return (
                                <Card key={metric.label} className="gap-4 border-slate-200/80 py-5 shadow-sm dark:border-slate-800">
                                    <CardContent className="px-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm text-slate-500 dark:text-slate-400">{metric.label}</p>
                                                <p className="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{metric.value}</p>
                                            </div>
                                            <span className={`rounded-md p-2.5 ${metric.tone}`}>
                                                <Icon className="size-5" />
                                            </span>
                                        </div>
                                        <div className="mt-4 flex items-center gap-2 text-xs">
                                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">{metric.change}</span>
                                            <span className="text-slate-500 dark:text-slate-400">{metric.detail}</span>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[1.45fr_1fr]">
                        <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                            <CardHeader className="flex-row items-center justify-between border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                                <div>
                                    <CardTitle className="text-base">Rendimiento mensual</CardTitle>
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Ingresos y objetivos de los últimos 6 meses</p>
                                </div>
                                <button type="button" aria-label="Más opciones" className="rounded-md p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                                    <MoreHorizontal className="size-5" />
                                </button>
                            </CardHeader>
                            <CardContent className="px-5 py-6">
                                <div className="flex items-end justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-700">
                                    <div>
                                        <p className="text-3xl font-semibold text-slate-950 dark:text-white">$24,680</p>
                                        <p className="mt-1 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"><ArrowUpRight className="size-3.5" /> 12.8% vs. periodo anterior</p>
                                    </div>
                                    <div className="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                        <span className="flex items-center gap-1.5"><i className="size-2 rounded-full bg-sky-500" /> Ingresos</span>
                                        <span className="flex items-center gap-1.5"><i className="size-2 rounded-full bg-slate-300 dark:bg-slate-600" /> Objetivo</span>
                                    </div>
                                </div>
                                <div className="mt-6 flex h-48 items-end gap-3 sm:gap-6">
                                    {[58, 72, 64, 86, 76, 96].map((height, index) => (
                                        <div key={height} className="flex h-full flex-1 flex-col items-center justify-end gap-2">
                                            <div className="flex h-full w-full items-end gap-1.5">
                                                <div className="w-1/2 rounded-t-sm bg-sky-500 transition hover:bg-sky-400" style={{ height: `${height}%` }} />
                                                <div className="w-1/2 rounded-t-sm bg-slate-200 dark:bg-slate-700" style={{ height: `${Math.min(height + 12, 100)}%` }} />
                                            </div>
                                            <span className="text-xs text-slate-400">{['Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep'][index]}</span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                            <CardHeader className="flex-row items-center justify-between border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                                <div>
                                    <CardTitle className="text-base">Actividad reciente</CardTitle>
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Últimos movimientos del equipo</p>
                                </div>
                                <Clock3 className="size-5 text-slate-400" />
                            </CardHeader>
                            <CardContent className="px-5 py-2">
                                {activity.map((item) => (
                                    <div key={`${item.name}-${item.time}`} className="flex gap-3 border-b border-slate-100 py-4 last:border-0 dark:border-slate-800">
                                        <span className={`flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white ${item.color}`}>{item.initials}</span>
                                        <div className="min-w-0">
                                            <p className="text-sm leading-5 text-slate-700 dark:text-slate-200"><span className="font-semibold">{item.name}</span> {item.action}</p>
                                            <p className="mt-1 text-xs text-slate-400">{item.time}</p>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="border-slate-200/80 py-0 shadow-sm dark:border-slate-800">
                        <CardHeader className="flex-row items-center justify-between border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                            <div>
                                <CardTitle className="text-base">Proyectos destacados</CardTitle>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Seguimiento de los proyectos activos</p>
                            </div>
                            <Link href="#" className="inline-flex items-center gap-1 text-sm font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300">Ver todos <ChevronRight className="size-4" /></Link>
                        </CardHeader>
                        <CardContent className="overflow-x-auto px-0 py-0">
                            <table className="w-full min-w-[620px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                                    <tr><th className="px-5 py-3 font-medium">Proyecto</th><th className="px-5 py-3 font-medium">Cliente</th><th className="px-5 py-3 font-medium">Progreso</th><th className="px-5 py-3 font-medium">Estado</th><th className="px-5 py-3" /></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {projects.map((project) => (
                                        <tr key={project.name} className="transition hover:bg-slate-50/80 dark:hover:bg-slate-900/50">
                                            <td className="px-5 py-4 font-medium text-slate-800 dark:text-slate-200">{project.name}</td>
                                            <td className="px-5 py-4 text-slate-500 dark:text-slate-400">{project.client}</td>
                                            <td className="px-5 py-4"><div className="flex items-center gap-3"><div className="h-1.5 w-24 rounded-full bg-slate-100 dark:bg-slate-700"><div className={`h-1.5 rounded-full ${project.tone}`} style={{ width: `${project.progress}%` }} /></div><span className="text-xs text-slate-500">{project.progress}%</span></div></td>
                                            <td className="px-5 py-4"><span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{project.status}</span></td>
                                            <td className="px-5 py-4 text-right"><button type="button" aria-label={`Opciones de ${project.name}`} className="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"><MoreHorizontal className="size-4" /></button></td>
                                        </tr>
                                    ))}
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
