import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    aprobar as approveDeletion,
    index as deletionRequestsIndex,
    rechazar as rejectDeletion,
} from '@/routes/management/solicitudes-eliminacion';

type DeletionRequest = {
    id: number;
    action: 'create' | 'update' | 'delete';
    entity_type: 'client' | 'project';
    entity_name: string;
    details: Record<string, string | number | null>;
    changes: {
        before: Record<string, string | number | boolean | null>;
        proposed: Record<string, string | number | boolean | null>;
    };
    requester_name: string;
    status: 'pending' | 'approved' | 'rejected';
    created_at: string;
    reviewed_at: string | null;
    reviewer: { name: string } | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
type PageProps = {
    selectedRequestId: number | null;
    requests: {
        data: DeletionRequest[];
        links: PageLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
};

const entityLabels = { client: 'Cliente', project: 'Proyecto' };
const actionLabels = {
    create: 'Crear',
    update: 'Actualizar',
    delete: 'Eliminar',
};
const statusLabels = {
    pending: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
};
const fieldLabels: Record<string, string> = {
    name: 'Nombre',
    company: 'Empresa',
    email: 'Correo',
    phone: 'Teléfono',
    address: 'Dirección',
    status: 'Estado',
    client: 'Cliente',
    description: 'Descripción',
    budget: 'Presupuesto',
    budget_currency: 'Moneda',
    start_date: 'Fecha de inicio',
    end_date: 'Fecha de fin',
    logo: 'Logotipo',
};

export default function DeletionRequestsIndex({
    requests,
    selectedRequestId,
}: PageProps) {
    const [error, setError] = useState('');

    function reviewRequest(
        request: DeletionRequest,
        action: 'approve' | 'reject',
    ) {
        const isApproval = action === 'approve';
        const message = isApproval
            ? `¿Aprobar la solicitud para ${request.action === 'create' ? 'crear' : request.action === 'update' ? 'actualizar' : 'eliminar'} ${request.entity_type === 'client' ? 'el cliente' : 'el proyecto'} "${request.entity_name}"?`
            : `¿Rechazar la solicitud para "${request.entity_name}"?`;

        if (!window.confirm(message)) {
            return;
        }

        setError('');
        router.post(
            (isApproval ? approveDeletion : rejectDeletion).url(request.id),
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        errors.approval ??
                            errors.deletion_request ??
                            'No se pudo procesar la solicitud.',
                    ),
            },
        );
    }

    return (
        <>
            <Head title="Solicitudes de cambios" />
            <main className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-6xl space-y-6">
                    <header>
                        <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                            Administración
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                            Solicitudes de cambios
                        </h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Revisa y aprueba o rechaza altas, actualizaciones y
                            eliminaciones solicitadas por los gestores.
                        </p>
                    </header>

                    {error && (
                        <div
                            className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
                            role="alert"
                        >
                            {error}
                        </div>
                    )}

                    <section className="space-y-4">
                        {requests.data.map((request) => (
                            <article
                                className={`rounded-xl border bg-white p-5 shadow-sm dark:bg-slate-900 ${selectedRequestId === request.id ? 'border-sky-500 ring-2 ring-sky-500/20 dark:border-sky-400' : 'border-slate-200 dark:border-slate-800'}`}
                                key={request.id}
                            >
                                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                                {actionLabels[request.action]} ·{' '}
                                                {
                                                    entityLabels[
                                                        request.entity_type
                                                    ]
                                                }
                                            </span>
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-medium ${request.status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : request.status === 'approved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}
                                            >
                                                {statusLabels[request.status]}
                                            </span>
                                        </div>
                                        <h2 className="text-lg font-semibold text-slate-950 dark:text-white">
                                            {request.entity_name}
                                        </h2>
                                        <p className="text-sm text-slate-600 dark:text-slate-300">
                                            Solicitó: {request.requester_name} ·{' '}
                                            {new Date(
                                                request.created_at,
                                            ).toLocaleString('es-PE')}
                                        </p>
                                        {request.reviewed_at && (
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                Revisó{' '}
                                                {request.reviewer?.name ??
                                                    'un administrador'}{' '}
                                                el{' '}
                                                {new Date(
                                                    request.reviewed_at,
                                                ).toLocaleString('es-PE')}
                                            </p>
                                        )}
                                        <dl className="grid gap-x-6 gap-y-1 pt-2 text-sm sm:grid-cols-2">
                                            {request.action === 'delete'
                                                ? Object.entries(
                                                      request.details,
                                                  )
                                                      .filter(
                                                          ([, value]) =>
                                                              value !== null &&
                                                              value !== '',
                                                      )
                                                      .map(([key, value]) => (
                                                          <div
                                                              className="flex gap-2"
                                                              key={key}
                                                          >
                                                              <dt className="text-slate-500 dark:text-slate-400">
                                                                  {fieldLabels[
                                                                      key
                                                                  ] ?? key}
                                                                  :
                                                              </dt>
                                                              <dd className="break-all text-slate-800 dark:text-slate-200">
                                                                  {value}
                                                              </dd>
                                                          </div>
                                                      ))
                                                : Object.entries(
                                                      request.changes.proposed,
                                                  )
                                                      .filter(
                                                          ([, value]) =>
                                                              value !== null &&
                                                              value !== '',
                                                      )
                                                      .map(([key, value]) => (
                                                          <div
                                                              className="flex flex-col gap-1"
                                                              key={key}
                                                          >
                                                              <dt className="text-slate-500 dark:text-slate-400">
                                                                  {fieldLabels[
                                                                      key
                                                                  ] ?? key}
                                                              </dt>
                                                              <dd className="break-all text-slate-800 dark:text-slate-200">
                                                                  {request.action ===
                                                                      'update' &&
                                                                      request
                                                                          .changes
                                                                          .before[
                                                                          key
                                                                      ] !==
                                                                          undefined && (
                                                                          <span className="mr-2 text-slate-500 dark:text-slate-400">
                                                                              {request
                                                                                  .changes
                                                                                  .before[
                                                                                  key
                                                                              ] ===
                                                                              null
                                                                                  ? 'Sin valor'
                                                                                  : String(
                                                                                        request
                                                                                            .changes
                                                                                            .before[
                                                                                            key
                                                                                        ],
                                                                                    )}{' '}
                                                                              →
                                                                          </span>
                                                                      )}
                                                                  {String(
                                                                      value,
                                                                  )}
                                                              </dd>
                                                          </div>
                                                      ))}
                                        </dl>
                                    </div>
                                    {request.status === 'pending' && (
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                onClick={() =>
                                                    reviewRequest(
                                                        request,
                                                        'reject',
                                                    )
                                                }
                                                variant="outline"
                                            >
                                                Rechazar
                                            </Button>
                                            <Button
                                                onClick={() =>
                                                    reviewRequest(
                                                        request,
                                                        'approve',
                                                    )
                                                }
                                                variant="destructive"
                                            >
                                                Aprobar{' '}
                                                {request.action === 'create'
                                                    ? 'creación'
                                                    : request.action ===
                                                        'update'
                                                      ? 'actualización'
                                                      : 'eliminación'}
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </article>
                        ))}
                        {requests.data.length === 0 && (
                            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                                No hay solicitudes pendientes o anteriores.
                            </div>
                        )}
                        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {requests.from ?? 0}–{requests.to ?? 0} de{' '}
                                {requests.total} solicitudes
                            </p>
                            <nav
                                aria-label="Paginación de solicitudes"
                                className="flex flex-wrap gap-1"
                            >
                                {requests.links.map((link, index) => (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        className={`rounded-md border px-3 py-1.5 text-sm ${link.active ? 'border-sky-600 bg-sky-600 text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                        href={link.url ?? '#'}
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </Link>
                                ))}
                            </nav>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}

DeletionRequestsIndex.layout = {
    breadcrumbs: [
        { title: 'Solicitudes de cambios', href: deletionRequestsIndex() },
    ],
};
