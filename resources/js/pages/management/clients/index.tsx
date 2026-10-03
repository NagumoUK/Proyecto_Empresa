import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Building2,
    Mail,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    Users,
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

type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PageProps = {
    clients: {
        data: Client[];
        links: PageLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    stats: { total: number; withProjects: number };
    filters: { search: string };
};

type ClientForm = {
    name: string;
    company: string;
    email: string;
    phone: string;
    address: string;
    status: 'active' | 'inactive';
};

const emptyClient: ClientForm = {
    name: '',
    company: '',
    email: '',
    phone: '',
    address: '',
    status: 'active',
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

export default function ClientsIndex({ clients, stats, filters }: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingClient, setEditingClient] = useState<Client | null>(null);
    const [deletingClient, setDeletingClient] = useState<Client | null>(null);
    const [deleteError, setDeleteError] = useState('');
    const form = useForm<ClientForm>(emptyClient);

    function openCreateDialog() {
        setEditingClient(null);
        form.setData(emptyClient);
        form.clearErrors();
        setDialogOpen(true);
    }

    function openEditDialog(client: Client) {
        setEditingClient(client);
        form.setData({
            name: client.name,
            company: client.company ?? '',
            email: client.email ?? '',
            phone: client.phone ?? '',
            address: client.address ?? '',
            status: client.status,
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function submitClient(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        };

        if (editingClient) {
            form.put(`/management/clientes/${editingClient.id}`, options);
        } else {
            form.post('/management/clientes', options);
        }
    }

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/management/clientes',
            { search },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function deleteClient() {
        if (!deletingClient) return;

        setDeleteError('');
        router.delete(`/management/clientes/${deletingClient.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingClient(null),
            onError: (errors) =>
                setDeleteError(
                    errors.delete ?? 'No se pudo eliminar el cliente.',
                ),
        });
    }

    return (
        <>
            <Head title="Clientes" />
            <div className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                                Gestión comercial
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                Clientes
                            </h1>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Administra contactos, empresas y su relación con
                                tus proyectos.
                            </p>
                        </div>
                        <Button onClick={openCreateDialog}>
                            <Plus className="size-4" /> Nuevo cliente
                        </Button>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Clientes registrados
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
                                        {stats.total}
                                    </p>
                                </div>
                                <span className="rounded-lg bg-sky-100 p-3 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                                    <Users className="size-5" />
                                </span>
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Con proyectos asociados
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
                                        {stats.withProjects}
                                    </p>
                                </div>
                                <span className="rounded-lg bg-violet-100 p-3 text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                    <Building2 className="size-5" />
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex flex-col justify-between gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center dark:border-slate-800">
                            <div>
                                <h2 className="font-semibold text-slate-950 dark:text-white">
                                    Directorio de clientes
                                </h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Consulta y actualiza la información de tus
                                    clientes.
                                </p>
                            </div>
                            <form
                                onSubmit={submitSearch}
                                className="flex w-full gap-2 sm:max-w-xs"
                            >
                                <div className="relative min-w-0 flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        aria-label="Buscar clientes"
                                        className="pl-9"
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Buscar cliente..."
                                        value={search}
                                    />
                                </div>
                                <Button type="submit" variant="outline">
                                    Buscar
                                </Button>
                            </form>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Contacto
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Correo y teléfono
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Proyectos
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
                                    {clients.data.map((client) => (
                                        <tr
                                            key={client.id}
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-950/30"
                                        >
                                            <td className="px-5 py-4">
                                                <Link
                                                    href={`/management/clientes/${client.id}`}
                                                    className="font-semibold text-slate-900 hover:text-sky-700 dark:text-white dark:hover:text-sky-300"
                                                >
                                                    {client.name}
                                                </Link>
                                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                    {client.company ||
                                                        'Empresa no especificada'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="flex items-center gap-2 text-slate-700 dark:text-slate-200">
                                                    <Mail className="size-3.5 text-slate-400" />
                                                    {client.email ||
                                                        'Sin correo'}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                    {client.phone ||
                                                        'Sin teléfono'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                                                {client.projects_count}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${client.status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}
                                                >
                                                    {client.status === 'active'
                                                        ? 'Activo'
                                                        : 'Inactivo'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        aria-label={`Editar ${client.name}`}
                                                        onClick={() =>
                                                            openEditDialog(
                                                                client,
                                                            )
                                                        }
                                                        size="icon"
                                                        variant="ghost"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        aria-label={`Eliminar ${client.name}`}
                                                        onClick={() => {
                                                            setDeletingClient(
                                                                client,
                                                            );
                                                            setDeleteError('');
                                                        }}
                                                        size="icon"
                                                        variant="ghost"
                                                    >
                                                        <Trash2 className="size-4 text-rose-600" />
                                                    </Button>
                                                    <Link
                                                        aria-label={`Ver ${client.name}`}
                                                        className="inline-flex size-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                                        href={`/management/clientes/${client.id}`}
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {clients.data.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-5 py-14 text-center text-slate-500 dark:text-slate-400"
                                                colSpan={5}
                                            >
                                                {filters.search
                                                    ? 'No se encontraron clientes con esa búsqueda.'
                                                    : 'Aún no hay clientes. Registra el primero para comenzar.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex flex-col justify-between gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center dark:border-slate-800">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {clients.from ?? 0}–{clients.to ?? 0} de{' '}
                                {clients.total} clientes
                            </p>
                            <nav
                                aria-label="Paginación de clientes"
                                className="flex flex-wrap gap-1"
                            >
                                {clients.links.map((link, index) => (
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
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingClient ? 'Editar cliente' : 'Nuevo cliente'}
                        </DialogTitle>
                        <DialogDescription>
                            Guarda los datos de contacto y estado del cliente.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-4" onSubmit={submitClient}>
                        <Field
                            error={form.errors.name}
                            label="Nombre de contacto"
                        >
                            <Input
                                autoFocus
                                maxLength={150}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                value={form.data.name}
                            />
                        </Field>
                        <Field error={form.errors.company} label="Empresa">
                            <Input
                                maxLength={180}
                                onChange={(event) =>
                                    form.setData('company', event.target.value)
                                }
                                value={form.data.company}
                            />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                error={form.errors.email}
                                label="Correo electrónico"
                            >
                                <Input
                                    maxLength={255}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    type="email"
                                    value={form.data.email}
                                />
                            </Field>
                            <Field error={form.errors.phone} label="Teléfono">
                                <Input
                                    maxLength={30}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    value={form.data.phone}
                                />
                            </Field>
                        </div>
                        <Field error={form.errors.address} label="Dirección">
                            <Input
                                maxLength={255}
                                onChange={(event) =>
                                    form.setData('address', event.target.value)
                                }
                                value={form.data.address}
                            />
                        </Field>
                        <Field error={form.errors.status} label="Estado">
                            <select
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                                onChange={(event) =>
                                    form.setData(
                                        'status',
                                        event.target
                                            .value as ClientForm['status'],
                                    )
                                }
                                value={form.data.status}
                            >
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </Field>
                        <DialogFooter>
                            <Button disabled={form.processing} type="submit">
                                {form.processing
                                    ? 'Guardando...'
                                    : 'Guardar cliente'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletingClient(null);
                        setDeleteError('');
                    }
                }}
                open={deletingClient !== null}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Eliminar cliente</DialogTitle>
                        <DialogDescription>
                            ¿Seguro que deseas eliminar a {deletingClient?.name}
                            ? Esta acción no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    {deleteError && (
                        <p role="alert" className="text-sm text-rose-600">
                            {deleteError}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            onClick={() => setDeletingClient(null)}
                            variant="outline"
                        >
                            Cancelar
                        </Button>
                        <Button onClick={deleteClient} variant="destructive">
                            Eliminar cliente
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ClientsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Clientes', href: '/management/clientes' },
    ],
};
