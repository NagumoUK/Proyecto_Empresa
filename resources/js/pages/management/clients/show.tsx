import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Mail,
    MapPin,
    Phone,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type ProjectSummary = {
    id: number;
    name: string;
    status: string;
};

type Client = {
    id: number;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    status: 'active' | 'inactive';
    projects_count: number;
};

export default function ClientShow({
    client,
    projects,
}: {
    client: Client;
    projects: ProjectSummary[];
}) {
    return (
        <>
            <Head title={client.name} />
            <main className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-4xl space-y-6">
                    <Button asChild variant="ghost">
                        <Link href="/management/clientes">
                            <ArrowLeft className="size-4" /> Volver a clientes
                        </Link>
                    </Button>
                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <div>
                                <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                                    Ficha del cliente
                                </p>
                                <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                    {client.name}
                                </h1>
                                <p className="mt-2 text-slate-500 dark:text-slate-400">
                                    {client.company ||
                                        'Empresa no especificada'}
                                </p>
                            </div>
                            <span
                                className={`inline-flex w-fit rounded-full px-3 py-1 text-sm font-medium ${client.status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}
                            >
                                {client.status === 'active'
                                    ? 'Activo'
                                    : 'Inactivo'}
                            </span>
                        </div>
                        <div className="mt-8 grid gap-5 border-t border-slate-100 pt-6 sm:grid-cols-2 dark:border-slate-800">
                            <p className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                                <Mail className="size-4 text-slate-400" />
                                {client.email || 'Sin correo registrado'}
                            </p>
                            <p className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200">
                                <Phone className="size-4 text-slate-400" />
                                {client.phone || 'Sin teléfono registrado'}
                            </p>
                            <p className="flex items-center gap-3 text-sm text-slate-700 sm:col-span-2 dark:text-slate-200">
                                <MapPin className="size-4 text-slate-400" />
                                {client.address || 'Sin dirección registrada'}
                            </p>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between border-b border-slate-100 p-5 dark:border-slate-800">
                            <div>
                                <h2 className="font-semibold text-slate-950 dark:text-white">
                                    Proyectos asociados
                                </h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {client.projects_count} proyectos
                                </p>
                            </div>
                            <BriefcaseBusiness className="size-5 text-slate-400" />
                        </div>
                        {projects.length ? (
                            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                                {projects.map((project) => (
                                    <li
                                        key={project.id}
                                        className="flex items-center justify-between gap-3 px-5 py-4"
                                    >
                                        <Link
                                            className="font-medium text-sky-700 hover:underline dark:text-sky-300"
                                            href={`/management/proyectos/${project.id}`}
                                        >
                                            {project.name}
                                        </Link>
                                        <span className="text-sm text-slate-500 dark:text-slate-400">
                                            {project.status.replace('_', ' ')}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="p-6 text-sm text-slate-500 dark:text-slate-400">
                                Este cliente aún no tiene proyectos.
                            </p>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}

ClientShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: '/management/clientes' },
        { title: 'Detalle', href: '/management/clientes' },
    ],
};
