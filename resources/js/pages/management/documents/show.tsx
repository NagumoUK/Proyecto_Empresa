import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useMemo, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPEN } from '@/lib/currency';
import { show as showClient } from '@/routes/management/clientes';
import { show as showProject } from '@/routes/management/proyectos';
import {
    convertirAFactura,
    destroy as destroyDocument,
    estado as updateDocumentStatus,
    index as documentsIndex,
    show as showDocument,
    update as updateDocument,
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
type ItemForm = { description: string; quantity: string; unit_price: string };
type ClientOption = { id: number; name: string; company: string | null };
type ProjectOption = { id: number; client_id: number; name: string };
type Document = {
    id: number;
    reference: string;
    type: DocumentType;
    status: DocumentStatus;
    currency: 'PEN';
    issue_date: string;
    due_date: string | null;
    notes: string | null;
    subtotal: string;
    tax_rate: string;
    tax_amount: string;
    total: string;
    client_id: number;
    project_id: number | null;
    client: ClientOption & {
        email: string | null;
        address: string | null;
    };
    project: { id: number; name: string } | null;
    source_document: { id: number; type: DocumentType } | null;
    items: Array<ItemForm & { id: number; line_total: string }>;
};

const statusLabels: Record<DocumentStatus, string> = {
    draft: 'Borrador',
    sent: 'Enviado',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    paid: 'Pagada',
    cancelled: 'Anulada',
};

const nextStatusLabels: Record<DocumentStatus, string> = {
    sent: 'Marcar como enviado',
    approved: 'Aprobar cotización',
    rejected: 'Rechazar cotización',
    paid: 'Marcar como pagada',
    cancelled: 'Anular documento',
    draft: 'Volver a borrador',
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

export default function FinancialDocumentShow({
    document,
    nextStatuses,
    invoice,
    clients,
    projects,
}: {
    document: Document;
    nextStatuses: DocumentStatus[];
    invoice: { id: number; type: DocumentType } | null;
    clients: ClientOption[];
    projects: ProjectOption[];
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManage = auth.user.role !== 'viewer';
    const form = useForm({
        client_id: document.client_id.toString(),
        project_id: document.project_id?.toString() ?? '',
        issue_date: document.issue_date,
        due_date: document.due_date ?? '',
        notes: document.notes ?? '',
        items: document.items.map(({ description, quantity, unit_price }) => ({
            description,
            quantity,
            unit_price,
        })),
    });
    const formErrors = form.errors as Record<string, string | undefined>;
    const selectedProjects = useMemo(
        () =>
            projects.filter(
                (project) =>
                    project.client_id.toString() === form.data.client_id,
            ),
        [projects, form.data.client_id],
    );
    const editSubtotal = form.data.items.reduce((sum, item) => {
        const line =
            Math.round(
                (Number(item.quantity || 0) * Number(item.unit_price || 0) +
                    Number.EPSILON) *
                    100,
            ) / 100;

        return sum + line;
    }, 0);
    const editTax =
        Math.round((editSubtotal * 0.18 + Number.EPSILON) * 100) / 100;

    function updateItem(index: number, key: keyof ItemForm, value: string) {
        form.setData(
            'items',
            form.data.items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [key]: value } : item,
            ),
        );
    }

    function saveDraft(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(updateDocument.url(document.id), {
            preserveScroll: true,
        });
    }

    function changeStatus(status: DocumentStatus) {
        router.patch(
            updateDocumentStatus.url(document.id),
            { status },
            { preserveScroll: true },
        );
    }

    function removeDraft() {
        if (
            window.confirm(
                '¿Eliminar este borrador? Esta acción no se puede deshacer.',
            )
        ) {
            router.delete(destroyDocument.url(document.id));
        }
    }

    const title = document.type === 'quote' ? 'Cotización' : 'Factura interna';

    return (
        <>
            <Head title={`${title} ${document.reference}`} />
            <main className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-5xl space-y-6">
                    <Button asChild variant="ghost">
                        <Link href={documentsIndex()}>
                            <ArrowLeft className="size-4" />
                            Volver a cotizaciones y facturas
                        </Link>
                    </Button>

                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                        <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <div>
                                <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                                    {title} · documento interno no fiscal
                                </p>
                                <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                    {document.reference}
                                </h1>
                                <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    Estado: {statusLabels[document.status]}
                                </p>
                            </div>
                            {canManage &&
                                document.status === 'draft' &&
                                (document.type === 'invoice' || !invoice) && (
                                    <Button
                                        onClick={removeDraft}
                                        variant="destructive"
                                    >
                                        <Trash2 className="size-4" />
                                        Eliminar borrador
                                    </Button>
                                )}
                        </header>

                        <div className="mt-8 grid gap-6 border-t border-slate-100 pt-6 sm:grid-cols-2 dark:border-slate-800">
                            <div>
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    Cliente
                                </p>
                                <Link
                                    className="mt-2 inline-block font-medium text-sky-700 hover:underline dark:text-sky-300"
                                    href={showClient.url(document.client.id)}
                                >
                                    {document.client.company ||
                                        document.client.name}
                                </Link>
                                {document.client.email && (
                                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {document.client.email}
                                    </p>
                                )}
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    Proyecto
                                </p>
                                {document.project ? (
                                    <Link
                                        className="mt-2 inline-block font-medium text-sky-700 hover:underline dark:text-sky-300"
                                        href={showProject.url(
                                            document.project.id,
                                        )}
                                    >
                                        {document.project.name}
                                    </Link>
                                ) : (
                                    <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                        Sin proyecto asociado
                                    </p>
                                )}
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    Fecha de emisión
                                </p>
                                <p className="mt-2 font-medium text-slate-800 dark:text-slate-200">
                                    {document.issue_date}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    Fecha de vencimiento
                                </p>
                                <p className="mt-2 font-medium text-slate-800 dark:text-slate-200">
                                    {document.due_date || 'No especificada'}
                                </p>
                            </div>
                            {document.source_document && (
                                <div className="sm:col-span-2">
                                    <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        Origen
                                    </p>
                                    <Link
                                        className="mt-2 inline-block font-medium text-sky-700 hover:underline dark:text-sky-300"
                                        href={showDocument.url(
                                            document.source_document.id,
                                        )}
                                    >
                                        Ver cotización de origen
                                    </Link>
                                </div>
                            )}
                        </div>

                        <div className="mt-8 overflow-x-auto">
                            <table className="w-full min-w-[550px] text-left text-sm">
                                <thead className="border-b border-slate-200 text-xs tracking-wide text-slate-500 uppercase dark:border-slate-700">
                                    <tr>
                                        <th className="py-3 pr-4 font-medium">
                                            Concepto
                                        </th>
                                        <th className="px-3 py-3 text-right font-medium">
                                            Cantidad
                                        </th>
                                        <th className="px-3 py-3 text-right font-medium">
                                            Precio unitario
                                        </th>
                                        <th className="py-3 pl-3 text-right font-medium">
                                            Importe
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {document.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="py-4 pr-4 text-slate-800 dark:text-slate-200">
                                                {item.description}
                                            </td>
                                            <td className="px-3 py-4 text-right text-slate-600 dark:text-slate-300">
                                                {item.quantity}
                                            </td>
                                            <td className="px-3 py-4 text-right text-slate-600 dark:text-slate-300">
                                                {formatPEN(item.unit_price)}
                                            </td>
                                            <td className="py-4 pl-3 text-right font-medium text-slate-800 dark:text-slate-200">
                                                {formatPEN(item.line_total)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-6 ml-auto grid max-w-sm gap-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-800">
                            <div className="flex justify-between gap-4 text-slate-600 dark:text-slate-300">
                                <span>Subtotal</span>
                                <span>{formatPEN(document.subtotal)}</span>
                            </div>
                            <div className="flex justify-between gap-4 text-slate-600 dark:text-slate-300">
                                <span>IGV ({document.tax_rate}%)</span>
                                <span>{formatPEN(document.tax_amount)}</span>
                            </div>
                            <div className="flex justify-between gap-4 border-t border-slate-100 pt-3 text-base font-semibold text-slate-950 dark:border-slate-800 dark:text-white">
                                <span>Total (PEN)</span>
                                <span>{formatPEN(document.total)}</span>
                            </div>
                        </div>

                        {document.notes && (
                            <div className="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-950 dark:text-slate-300">
                                <p className="font-semibold">Notas</p>
                                <p className="mt-1 whitespace-pre-wrap">
                                    {document.notes}
                                </p>
                            </div>
                        )}

                        {canManage &&
                            (nextStatuses.length > 0 ||
                                (document.type === 'quote' &&
                                    document.status === 'approved' &&
                                    !invoice) ||
                                invoice !== null) && (
                                <div className="mt-8 flex flex-wrap gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
                                    {nextStatuses.map((status) => (
                                        <Button
                                            key={status}
                                            onClick={() => changeStatus(status)}
                                            variant={
                                                status === 'cancelled' ||
                                                status === 'rejected'
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                        >
                                            {nextStatusLabels[status]}
                                        </Button>
                                    ))}
                                    {document.type === 'quote' &&
                                        document.status === 'approved' &&
                                        !invoice && (
                                            <Button
                                                onClick={() =>
                                                    router.post(
                                                        convertirAFactura.url(
                                                            document.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Convertir a factura interna
                                            </Button>
                                        )}
                                    {invoice && (
                                        <Button asChild variant="outline">
                                            <Link
                                                href={showDocument.url(
                                                    invoice.id,
                                                )}
                                            >
                                                Ver factura interna
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )}
                    </section>

                    {canManage && document.status === 'draft' && (
                        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                            <div className="mb-5">
                                <h2 className="text-lg font-semibold text-slate-950 dark:text-white">
                                    Editar borrador
                                </h2>
                                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Los importes y el IGV se recalculan al
                                    guardar.
                                </p>
                            </div>
                            <form className="grid gap-5" onSubmit={saveDraft}>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        error={form.errors.client_id}
                                        label="Cliente"
                                    >
                                        <select
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                            onChange={(event) =>
                                                form.setData({
                                                    ...form.data,
                                                    client_id:
                                                        event.target.value,
                                                    project_id: '',
                                                })
                                            }
                                            value={form.data.client_id}
                                        >
                                            {clients.map((client) => (
                                                <option
                                                    key={client.id}
                                                    value={client.id}
                                                >
                                                    {client.company ||
                                                        client.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field
                                        error={form.errors.project_id}
                                        label="Proyecto"
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
                                            <option value="">
                                                Sin proyecto
                                            </option>
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
                                    {document.type === 'invoice' && (
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
                                                                itemIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                                type="button"
                                                variant="outline"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                    {form.errors.items && (
                                        <p className="text-xs text-rose-600">
                                            {form.errors.items}
                                        </p>
                                    )}
                                    <Button
                                        className="w-fit"
                                        onClick={() =>
                                            form.setData('items', [
                                                ...form.data.items,
                                                {
                                                    description: '',
                                                    quantity: '1',
                                                    unit_price: '',
                                                },
                                            ])
                                        }
                                        type="button"
                                        variant="outline"
                                    >
                                        <Plus className="size-4" />
                                        Agregar concepto
                                    </Button>
                                </div>
                                <Field error={form.errors.notes} label="Notas">
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
                                <div className="grid gap-1 text-right text-sm text-slate-600 dark:text-slate-300">
                                    <p>Subtotal: {formatPEN(editSubtotal)}</p>
                                    <p>IGV (18%): {formatPEN(editTax)}</p>
                                </div>
                                <div className="flex justify-end">
                                    <Button
                                        disabled={form.processing}
                                        type="submit"
                                    >
                                        {form.processing
                                            ? 'Guardando...'
                                            : 'Guardar cambios'}
                                    </Button>
                                </div>
                            </form>
                        </section>
                    )}
                </div>
            </main>
        </>
    );
}

FinancialDocumentShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cotizaciones y facturas', href: documentsIndex() },
        { title: 'Documento', href: documentsIndex() },
    ],
};
