import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CalendarDays,
    CircleDollarSign,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

type ProjectStatus =
    | 'planning'
    | 'active'
    | 'on_hold'
    | 'completed'
    | 'cancelled';

type ClientOption = {
    id: number;
    name: string;
    company: string | null;
};

type Project = {
    id: number;
    client_id: number;
    name: string;
    description: string | null;
    status: ProjectStatus;
    budget: string | null;
    start_date: string | null;
    end_date: string | null;
    client: ClientOption;
};

type PageLink = { url: string | null; label: string; active: boolean };

type PageProps = {
    projects: {
        data: Project[];
        links: PageLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    clients: ClientOption[];
    stats: { total: number; active: number; withBudget: number };
    filters: { search: string; status: string };
};

type ProjectForm = {
    client_id: string;
    name: string;
    description: string;
    status: ProjectStatus;
    budget: string;
    start_date: string;
    end_date: string;
};

const emptyProject: ProjectForm = {
    client_id: '',
    name: '',
    description: '',
    status: 'planning',
    budget: '',
    start_date: '',
    end_date: '',
};

const statusLabels: Record<ProjectStatus, string> = {
    planning: 'Planificación',
    active: 'En curso',
    on_hold: 'En pausa',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

const statusStyles: Record<ProjectStatus, string> = {
    planning:
        'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    active: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    on_hold:
        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    completed:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    cancelled:
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
            {label}
            {children}
            {error && (
                <span className="text-xs font-normal text-rose-600">
                    {error}
                </span>
            )}
        </label>
    );
}

export default function ProjectsIndex({
    projects,
    clients,
    stats,
    filters,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [statusFilter, setStatusFilter] = useState(filters.status);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingProject, setEditingProject] = useState<Project | null>(null);
    const [deletingProject, setDeletingProject] = useState<Project | null>(
        null,
    );
    const form = useForm<ProjectForm>(emptyProject);

    function openCreateDialog() {
        setEditingProject(null);
        form.setData({
            ...emptyProject,
            client_id: clients[0]?.id.toString() ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function openEditDialog(project: Project) {
        setEditingProject(project);
        form.setData({
            client_id: project.client_id.toString(),
            name: project.name,
            description: project.description ?? '',
            status: project.status,
            budget: project.budget ?? '',
            start_date: project.start_date ?? '',
            end_date: project.end_date ?? '',
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function submitProject(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        };

        if (editingProject) {
            form.put(`/management/proyectos/${editingProject.id}`, options);
        } else {
            form.post('/management/proyectos', options);
        }
    }

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/management/proyectos',
            { search, status: statusFilter },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function deleteProject() {
        if (!deletingProject) return;

        router.delete(`/management/proyectos/${deletingProject.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingProject(null),
        });
    }

    return (
        <>
            <Head title="Proyectos" />
            <div className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                                Planificación y seguimiento
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                Proyectos
                            </h1>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Organiza entregas, presupuesto y fechas por
                                cliente.
                            </p>
                        </div>
                        <Button
                            disabled={clients.length === 0}
                            onClick={openCreateDialog}
                        >
                            <Plus className="size-4" /> Nuevo proyecto
                        </Button>
                    </div>

                    {clients.length === 0 && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            Primero registra un cliente para poder asociar
                            proyectos.{' '}
                            <Link
                                className="font-semibold underline"
                                href="/management/clientes"
                            >
                                Ir a clientes
                            </Link>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-3">
                        {[
                            {
                                label: 'Proyectos registrados',
                                value: stats.total,
                                icon: BriefcaseBusiness,
                                tone: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
                            },
                            {
                                label: 'En curso',
                                value: stats.active,
                                icon: CalendarDays,
                                tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                            },
                            {
                                label: 'Con presupuesto',
                                value: stats.withBudget,
                                icon: CircleDollarSign,
                                tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
                            },
                        ].map((metric) => {
                            const Icon = metric.icon;
                            return (
                                <div
                                    key={metric.label}
                                    className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                {metric.label}
                                            </p>
                                            <p className="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
                                                {metric.value}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-lg p-3 ${metric.tone}`}
                                        >
                                            <Icon className="size-5" />
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex flex-col justify-between gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center dark:border-slate-800">
                            <div>
                                <h2 className="font-semibold text-slate-950 dark:text-white">
                                    Portafolio de proyectos
                                </h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Consulta el estado, el cliente y las fechas
                                    previstas.
                                </p>
                            </div>
                            <form
                                onSubmit={submitSearch}
                                className="flex flex-col gap-2 sm:flex-row"
                            >
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        aria-label="Buscar proyectos"
                                        className="pl-9 sm:w-56"
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Proyecto o cliente..."
                                        value={search}
                                    />
                                </div>
                                <select
                                    aria-label="Filtrar por estado"
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                                    onChange={(event) =>
                                        setStatusFilter(event.target.value)
                                    }
                                    value={statusFilter}
                                >
                                    <option value="">Todos los estados</option>
                                    {Object.entries(statusLabels).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <Button type="submit" variant="outline">
                                    Filtrar
                                </Button>
                            </form>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[850px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Proyecto
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Cliente
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Fechas
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Presupuesto
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Estado
                                        </th>
                                        <th className="px-5 py-3 text-right font-medium">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {projects.data.map((project) => (
                                        <tr
                                            key={project.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-950/30"
                                        >
                                            <td className="px-5 py-4">
                                                <Link
                                                    className="font-semibold text-slate-900 hover:text-sky-700 dark:text-white dark:hover:text-sky-300"
                                                    href={`/management/proyectos/${project.id}`}
                                                >
                                                    {project.name}
                                                </Link>
                                                <p className="mt-1 max-w-xs truncate text-xs text-slate-500 dark:text-slate-400">
                                                    {project.description ||
                                                        'Sin descripción'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                                                {project.client.company ||
                                                    project.client.name}
                                            </td>
                                            <td className="px-5 py-4 text-xs text-slate-500 dark:text-slate-400">
                                                {project.start_date ||
                                                    'Sin inicio'}
                                                <br />
                                                {project.end_date
                                                    ? `Hasta ${project.end_date}`
                                                    : 'Sin fecha de entrega'}
                                            </td>
                                            <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                                                {project.budget
                                                    ? new Intl.NumberFormat(
                                                          'es-MX',
                                                          {
                                                              style: 'currency',
                                                              currency: 'MXN',
                                                          },
                                                      ).format(
                                                          Number(
                                                              project.budget,
                                                          ),
                                                      )
                                                    : 'Sin definir'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[project.status]}`}
                                                >
                                                    {
                                                        statusLabels[
                                                            project.status
                                                        ]
                                                    }
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        aria-label={`Editar ${project.name}`}
                                                        onClick={() =>
                                                            openEditDialog(
                                                                project,
                                                            )
                                                        }
                                                        size="icon"
                                                        variant="ghost"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        aria-label={`Eliminar ${project.name}`}
                                                        onClick={() =>
                                                            setDeletingProject(
                                                                project,
                                                            )
                                                        }
                                                        size="icon"
                                                        variant="ghost"
                                                    >
                                                        <Trash2 className="size-4 text-rose-600" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {projects.data.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-5 py-14 text-center text-slate-500 dark:text-slate-400"
                                                colSpan={6}
                                            >
                                                {filters.search ||
                                                filters.status
                                                    ? 'No se encontraron proyectos con esos filtros.'
                                                    : 'Aún no hay proyectos. Crea uno y asígnalo a un cliente.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex flex-col justify-between gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center dark:border-slate-800">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {projects.from ?? 0}–{projects.to ?? 0} de{' '}
                                {projects.total} proyectos
                            </p>
                            <nav
                                aria-label="Paginación de proyectos"
                                className="flex flex-wrap gap-1"
                            >
                                {projects.links.map((link, index) => (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        className={`rounded-md border px-3 py-1.5 text-sm ${link.active ? 'border-sky-600 bg-sky-600 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                        href={link.url ?? '#'}
                                        preserveScroll
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </Link>
                                ))}
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog onOpenChange={setDialogOpen} open={dialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editingProject
                                ? 'Editar proyecto'
                                : 'Nuevo proyecto'}
                        </DialogTitle>
                        <DialogDescription>
                            Define los datos principales y asígnalo a un
                            cliente.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={submitProject}>
                        <Field
                            error={form.errors.name}
                            label="Nombre del proyecto"
                        >
                            <Input
                                autoFocus
                                maxLength={180}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                value={form.data.name}
                            />
                        </Field>
                        <Field error={form.errors.client_id} label="Cliente">
                            <select
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                                onChange={(event) =>
                                    form.setData(
                                        'client_id',
                                        event.target.value,
                                    )
                                }
                                value={form.data.client_id}
                            >
                                {clients.map((client) => (
                                    <option key={client.id} value={client.id}>
                                        {client.company || client.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            error={form.errors.description}
                            label="Descripción"
                        >
                            <textarea
                                className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-sky-500"
                                maxLength={5000}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                value={form.data.description}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field error={form.errors.status} label="Estado">
                                <select
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value as ProjectStatus,
                                        )
                                    }
                                    value={form.data.status}
                                >
                                    {Object.entries(statusLabels).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                            <Field
                                error={form.errors.budget}
                                label="Presupuesto (MXN)"
                            >
                                <Input
                                    min="0"
                                    onChange={(event) =>
                                        form.setData(
                                            'budget',
                                            event.target.value,
                                        )
                                    }
                                    step="0.01"
                                    type="number"
                                    value={form.data.budget}
                                />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                error={form.errors.start_date}
                                label="Fecha de inicio"
                            >
                                <Input
                                    onChange={(event) =>
                                        form.setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                    type="date"
                                    value={form.data.start_date}
                                />
                            </Field>
                            <Field
                                error={form.errors.end_date}
                                label="Fecha de entrega"
                            >
                                <Input
                                    onChange={(event) =>
                                        form.setData(
                                            'end_date',
                                            event.target.value,
                                        )
                                    }
                                    type="date"
                                    value={form.data.end_date}
                                />
                            </Field>
                        </div>
                        <DialogFooter>
                            <Button disabled={form.processing} type="submit">
                                {form.processing
                                    ? 'Guardando...'
                                    : 'Guardar proyecto'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                onOpenChange={(open) => {
                    if (!open) setDeletingProject(null);
                }}
                open={deletingProject !== null}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Eliminar proyecto</DialogTitle>
                        <DialogDescription>
                            ¿Seguro que deseas eliminar “{deletingProject?.name}
                            ”? Esta acción no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            onClick={() => setDeletingProject(null)}
                            variant="outline"
                        >
                            Cancelar
                        </Button>
                        <Button onClick={deleteProject} variant="destructive">
                            Eliminar proyecto
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Proyectos', href: '/management/proyectos' },
    ],
};
