import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
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
import { formatPEN } from '@/lib/currency';
import { index as clientsIndex } from '@/routes/management/clientes';
import {
    index as documentsIndex,
    show as showDocument,
    store as storeDocument,
} from '@/routes/management/documentos';
import { dashboard } from '@/routes';
import type { Auth } from '@/types/auth';

type DocumentType = 'quote' | 'invoice';
type DocumentStatus =
    | 'draft'
    | 'sent'
    | 'approved'
    | 'rejected'
    | 'paid'
    | 'cancelled';

type ClientOption = { id: number; name: string; company: string | null };
type ProjectOption = { id: number; client_id: number; name: string };
type DocumentItemForm = {
    description: string;
    quantity: string;
    unit_price: string;
};
type DocumentForm = {
    type: DocumentType;
    client_id: string;
    project_id: string;
    issue_date: string;
    due_date: string;
    notes: string;
    items: DocumentItemForm[];
};
type FinancialDocument = {
    id: number;
    reference: string;
    type: DocumentType;
    status: DocumentStatus;
    currency: 'PEN';
    issue_date: string;
    total: string;
    client: ClientOption;
    project: { id: number; name: string } | null;
};
type PageLink = { url: string | null; label: string; active: boolean };

type PageProps = {
    documents: {
        data: FinancialDocument[];
        links: PageLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    clients: ClientOption[];
    projects: ProjectOption[];
    stats: {
        quotes: number;
        pendingQuotes: number;
        invoices: number;
        paidTotal: string | number;
    };
    filters: { search: string; type: string; status: string };
};

const statusLabels: Record<DocumentStatus, string> = {
    draft: 'Borrador',
    sent: 'Enviado',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    paid: 'Pagada',
    cancelled: 'Anulada',
};

const statusStyles: Record<DocumentStatus, string> = {
    draft: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    sent: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    approved:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
    paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    cancelled:
        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
};

const emptyItem: DocumentItemForm = {
    description: '',
    quantity: '1',
    unit_price: '',
};

function dateToday(): string {
    const date = new Date();
    const localDate = new Date(
        date.getTime() - date.getTimezoneOffset() * 60000,
    );

    return localDate.toISOString().slice(0, 10);
}

function newDocumentForm(type: DocumentType = 'quote'): DocumentForm {
    return {
        type,
        client_id: '',
        project_id: '',
        issue_date: dateToday(),
        due_date: '',
        notes: '',
        items: [{ ...emptyItem }],
    };
}

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

export default function FinancialDocumentsIndex({
    documents,
    clients,
    projects,
    stats,
    filters,
}: PageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManage = auth.user.role !== 'viewer';
    const [search, setSearch] = useState(filters.search);
    const [typeFilter, setTypeFilter] = useState(filters.type);
    const [statusFilter, setStatusFilter] = useState(filters.status);
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm<DocumentForm>(newDocumentForm());
    const formErrors = form.errors as Record<string, string | undefined>;

    const selectedProjects = useMemo(
        () =>
            projects.filter(
                (project) =>
                    project.client_id.toString() === form.data.client_id,
            ),
        [projects, form.data.client_id],
    );

    const subtotal = form.data.items.reduce((sum, item) => {
        const line =
            Math.round(
                (Number(item.quantity || 0) * Number(item.unit_price || 0) +
                    Number.EPSILON) *
                    100,
            ) / 100;

        return sum + line;
    }, 0);
    const igv = Math.round((subtotal * 0.18 + Number.EPSILON) * 100) / 100;
    const total = subtotal + igv;

    function openCreateDialog() {
        const next = newDocumentForm();
        next.client_id = clients[0]?.id.toString() ?? '';
        form.setData(next);
        form.clearErrors();
        setDialogOpen(true);
    }

    function submitDocument(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(storeDocument.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        });
    }

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            documentsIndex.url(),
            { search, type: typeFilter, status: statusFilter },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function updateItem(
        index: number,
        key: keyof DocumentItemForm,
        value: string,
    ) {
        const items = form.data.items.map((item, itemIndex) =>
            itemIndex === index ? { ...item, [key]: value } : item,
        );
        form.setData('items', items);
    }

    return (
        <>
            <Head title="Cotizaciones y facturas" />
            <main className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-7xl space-y-6">
                    <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                                Gestión comercial
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                                Cotizaciones y facturas
                            </h1>
                            <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                                Documentos internos en soles. Las facturas de
                                este módulo no son comprobantes electrónicos
                                SUNAT.
                            </p>
                        </div>
                        {canManage && (
                            <Button
                                disabled={clients.length === 0}
                                onClick={openCreateDialog}
                            >
                                <Plus className="size-4" /> Nuevo documento
                            </Button>
                        )}
                    </header>

                    {clients.length === 0 && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            Primero registra un cliente para crear cotizaciones
                            o facturas.{' '}
                            <Link
                                className="font-semibold underline"
                                href={clientsIndex()}
                            >
                                Ir a clientes
                            </Link>
                        </div>
                    )}

                    <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ['Cotizaciones', stats.quotes],
                            ['Pendientes de respuesta', stats.pendingQuotes],
                            ['Facturas internas', stats.invoices],
                            ['Total pagado', formatPEN(stats.paidTotal)],
                        ].map(([label, value]) => (
                            <div
                                className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                                key={label}
                            >
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    {label}
                                </p>
                                <p className="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">
                                    {value}
                                </p>
                            </div>
                        ))}
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <form
                            className="flex flex-col justify-between gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center dark:border-slate-800"
                            onSubmit={submitSearch}
                        >
                            <div>
                                <h2 className="font-semibold text-slate-950 dark:text-white">
                                    Registro de documentos
                                </h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Busca por cliente o proyecto y filtra por
                                    tipo o estado.
                                </p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <Input
                                        aria-label="Buscar por cliente o proyecto"
                                        className="pl-9 sm:w-56"
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cliente o proyecto..."
                                        value={search}
                                    />
                                </div>
                                <select
                                    aria-label="Filtrar tipo de documento"
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                                    onChange={(event) =>
                                        setTypeFilter(event.target.value)
                                    }
                                    value={typeFilter}
                                >
                                    <option value="">Todos los tipos</option>
                                    <option value="quote">Cotizaciones</option>
                                    <option value="invoice">Facturas</option>
                                </select>
                                <select
                                    aria-label="Filtrar estado"
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
                            </div>
                        </form>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[820px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Documento
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Cliente / proyecto
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Fecha
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Estado
                                        </th>
                                        <th className="px-5 py-3 text-right font-medium">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {documents.data.map((document) => (
                                        <tr
                                            className="hover:bg-slate-50/80 dark:hover:bg-slate-950/30"
                                            key={document.id}
                                        >
                                            <td className="px-5 py-4">
                                                <Link
                                                    className="font-semibold text-slate-900 hover:text-sky-700 dark:text-white dark:hover:text-sky-300"
                                                    href={showDocument.url(
                                                        document.id,
                                                    )}
                                                >
                                                    {document.reference}
                                                </Link>
                                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                    {document.type === 'quote'
                                                        ? 'Cotización'
                                                        : 'Factura interna'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-slate-700 dark:text-slate-300">
                                                {document.client.company ||
                                                    document.client.name}
                                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                    {document.project?.name ||
                                                        'Sin proyecto'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                                                {document.issue_date}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusStyles[document.status]}`}
                                                >
                                                    {
                                                        statusLabels[
                                                            document.status
                                                        ]
                                                    }
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right font-semibold text-slate-800 dark:text-slate-200">
                                                {formatPEN(document.total)}
                                            </td>
                                        </tr>
                                    ))}
                                    {documents.data.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-5 py-12 text-center text-slate-500 dark:text-slate-400"
                                                colSpan={5}
                                            >
                                                <FileText className="mx-auto mb-3 size-8 text-slate-300" />
                                                No hay documentos para mostrar.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {documents.links.length > 3 && (
                            <nav
                                aria-label="Paginación de documentos"
                                className="flex flex-wrap justify-end gap-1 border-t border-slate-100 p-4 dark:border-slate-800"
                            >
                                {documents.links.map((link, index) =>
                                    link.url ? (
                                        <Link
                                            aria-current={
                                                link.active ? 'page' : undefined
                                            }
                                            className={`rounded-md border px-3 py-1.5 text-sm ${
                                                link.active
                                                    ? 'border-sky-600 bg-sky-600 text-white'
                                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'
                                            }`}
                                            href={link.url}
                                            key={`${link.label}-${index}`}
                                        >
                                            {link.label
                                                .replace('&laquo;', '«')
                                                .replace('&raquo;', '»')}
                                        </Link>
                                    ) : (
                                        <span
                                            className="rounded-md border border-slate-100 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-800"
                                            key={`${link.label}-${index}`}
                                        >
                                            {link.label
                                                .replace('&laquo;', '«')
                                                .replace('&raquo;', '»')}
                                        </span>
                                    ),
                                )}
                            </nav>
                        )}
                    </section>
                </div>
            </main>

            {canManage && (
                <Dialog onOpenChange={setDialogOpen} open={dialogOpen}>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>
                                Crear cotización o factura interna
                            </DialogTitle>
                            <DialogDescription>
                                Registra los conceptos; el subtotal, el IGV
                                (18%) y el total se calculan automáticamente en
                                soles.
                            </DialogDescription>
                        </DialogHeader>
                        <form className="grid gap-5" onSubmit={submitDocument}>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    error={form.errors.type}
                                    label="Tipo de documento"
                                >
                                    <select
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        onChange={(event) =>
                                            form.setData(
                                                'type',
                                                event.target
                                                    .value as DocumentType,
                                            )
                                        }
                                        value={form.data.type}
                                    >
                                        <option value="quote">
                                            Cotización
                                        </option>
                                        <option value="invoice">
                                            Factura interna
                                        </option>
                                    </select>
                                </Field>
                                <Field
                                    error={form.errors.client_id}
                                    label="Cliente"
                                >
                                    <select
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        onChange={(event) => {
                                            form.setData({
                                                ...form.data,
                                                client_id: event.target.value,
                                                project_id: '',
                                            });
                                        }}
                                        value={form.data.client_id}
                                    >
                                        <option value="">
                                            Selecciona un cliente
                                        </option>
                                        {clients.map((client) => (
                                            <option
                                                key={client.id}
                                                value={client.id}
                                            >
                                                {client.company || client.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    error={form.errors.project_id}
                                    label="Proyecto (opcional)"
                                >
                                    <select
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                        onChange={(event) =>
                                            form.setData(
                                                'project_id',
                                                event.target.value,
                                            )
                                        }
                                        value={form.data.project_id}
                                    >
                                        <option value="">Sin proyecto</option>
                                        {selectedProjects.map((project) => (
                                            <option
                                                key={project.id}
                                                value={project.id}
                                            >
                                                {project.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    error={form.errors.issue_date}
                                    label="Fecha de emisión"
                                >
                                    <Input
                                        onChange={(event) =>
                                            form.setData(
                                                'issue_date',
                                                event.target.value,
                                            )
                                        }
                                        type="date"
                                        value={form.data.issue_date}
                                    />
                                </Field>
                                {form.data.type === 'invoice' && (
                                    <Field
                                        error={form.errors.due_date}
                                        label="Fecha de vencimiento"
                                    >
                                        <Input
                                            onChange={(event) =>
                                                form.setData(
                                                    'due_date',
                                                    event.target.value,
                                                )
                                            }
                                            type="date"
                                            value={form.data.due_date}
                                        />
                                    </Field>
                                )}
                            </div>

                            <div className="grid gap-3">
                                <div className="flex items-center justify-between">
                                    <h3 className="font-semibold text-slate-900 dark:text-white">
                                        Conceptos
                                    </h3>
                                    <Button
                                        onClick={() =>
                                            form.setData('items', [
                                                ...form.data.items,
                                                { ...emptyItem },
                                            ])
                                        }
                                        type="button"
                                        variant="outline"
                                    >
                                        <Plus className="size-4" /> Agregar
                                        concepto
                                    </Button>
                                </div>
                                {form.data.items.map((item, index) => (
                                    <div
                                        className="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_100px_140px_auto] dark:border-slate-800"
                                        key={index}
                                    >
                                        <Field
                                            error={
                                                formErrors[
                                                    `items.${index}.description`
                                                ]
                                            }
                                            label="Descripción"
                                        >
                                            <Input
                                                maxLength={255}
                                                onChange={(event) =>
                                                    updateItem(
                                                        index,
                                                        'description',
                                                        event.target.value,
                                                    )
                                                }
                                                value={item.description}
                                            />
                                        </Field>
                                        <Field
                                            error={
                                                formErrors[
                                                    `items.${index}.quantity`
                                                ]
                                            }
                                            label="Cantidad"
                                        >
                                            <Input
                                                min="0.01"
                                                onChange={(event) =>
                                                    updateItem(
                                                        index,
                                                        'quantity',
                                                        event.target.value,
                                                    )
                                                }
                                                step="0.01"
                                                type="number"
                                                value={item.quantity}
                                            />
                                        </Field>
                                        <Field
                                            error={
                                                formErrors[
                                                    `items.${index}.unit_price`
                                                ]
                                            }
                                            label="Precio unitario (S/)"
                                        >
                                            <Input
                                                min="0"
                                                onChange={(event) =>
                                                    updateItem(
                                                        index,
                                                        'unit_price',
                                                        event.target.value,
                                                    )
                                                }
                                                step="0.01"
                                                type="number"
                                                value={item.unit_price}
                                            />
                                        </Field>
                                        <Button
                                            aria-label={`Quitar concepto ${index + 1}`}
                                            className="self-end"
                                            disabled={
                                                form.data.items.length === 1
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'items',
                                                    form.data.items.filter(
                                                        (_, itemIndex) =>
                                                            itemIndex !== index,
                                                    ),
                                                )
                                            }
                                            type="button"
                                            variant="outline"
                                        >
                                            Quitar
                                        </Button>
                                    </div>
                                ))}
                                {form.errors.items && (
                                    <p className="text-xs text-rose-600">
                                        {form.errors.items}
                                    </p>
                                )}
                                <div className="ml-auto grid min-w-64 gap-1 text-right text-sm">
                                    <p className="text-slate-600 dark:text-slate-300">
                                        Subtotal: {formatPEN(subtotal)}
                                    </p>
                                    <p className="text-slate-600 dark:text-slate-300">
                                        IGV (18%): {formatPEN(igv)}
                                    </p>
                                    <p className="font-semibold text-slate-950 dark:text-white">
                                        Total: {formatPEN(total)}
                                    </p>
                                </div>
                            </div>

                            <Field
                                error={form.errors.notes}
                                label="Notas (opcional)"
                            >
                                <textarea
                                    className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    maxLength={2000}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    value={form.data.notes}
                                />
                            </Field>
                            <DialogFooter>
                                <Button
                                    disabled={form.processing}
                                    type="submit"
                                >
                                    {form.processing
                                        ? 'Guardando...'
                                        : 'Guardar borrador'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

FinancialDocumentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cotizaciones y facturas', href: documentsIndex() },
    ],
};
