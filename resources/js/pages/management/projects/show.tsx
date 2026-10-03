import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CircleDollarSign } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Project = {
    id: number;
    name: string;
    description: string | null;
    status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
    budget: string | null;
    start_date: string | null;
    end_date: string | null;
    client: { id: number; name: string; company: string | null };
};

const statusLabels: Record<Project['status'], string> = {
    planning: 'Planificación',
    active: 'En curso',
    on_hold: 'En pausa',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

export default function ProjectShow({ project }: { project: Project }) {
    return (
        <>
            <Head title={project.name} />
            <main className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-4xl space-y-6">
                    <Button asChild variant="ghost">
                        <Link href="/management/proyectos">
                            <ArrowLeft className="size-4" /> Volver a proyectos
                        </Link>
                    </Button>
                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                        <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                            Detalle del proyecto
                        </p>
                        <div className="mt-1 flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <h1 className="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                {project.name}
                            </h1>
                            <span className="inline-flex w-fit rounded-full bg-sky-100 px-3 py-1 text-sm font-medium text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                {statusLabels[project.status]}
                            </span>
                        </div>
                        <p className="mt-3 text-slate-600 dark:text-slate-300">
                            {project.description ||
                                'Este proyecto no tiene una descripción.'}
                        </p>
                        <div className="mt-8 grid gap-5 border-t border-slate-100 pt-6 sm:grid-cols-2 dark:border-slate-800">
                            <div>
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    Cliente
                                </p>
                                <Link
                                    className="mt-2 inline-block font-medium text-sky-700 hover:underline dark:text-sky-300"
                                    href={`/management/clientes/${project.client.id}`}
                                >
                                    {project.client.company ||
                                        project.client.name}
                                </Link>
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    Presupuesto
                                </p>
                                <p className="mt-2 flex items-center gap-2 font-medium text-slate-800 dark:text-slate-200">
                                    <CircleDollarSign className="size-4 text-slate-400" />
                                    {project.budget
                                        ? new Intl.NumberFormat('es-MX', {
                                              style: 'currency',
                                              currency: 'MXN',
                                          }).format(Number(project.budget))
                                        : 'Sin definir'}
                                </p>
                            </div>
                            <div className="sm:col-span-2">
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    Fechas
                                </p>
                                <p className="mt-2 flex items-center gap-2 font-medium text-slate-800 dark:text-slate-200">
                                    <CalendarDays className="size-4 text-slate-400" />
                                    {project.start_date ||
                                        'Sin fecha de inicio'}{' '}
                                    –{' '}
                                    {project.end_date || 'Sin fecha de entrega'}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Proyectos', href: '/management/proyectos' },
        { title: 'Detalle', href: '/management/proyectos' },
    ],
};
