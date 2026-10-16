import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowLeft,
    CalendarDays,
    CircleDollarSign,
    File,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { formatPEN } from '@/lib/currency';
import { dashboard } from '@/routes';
import {
    destroy as destroyProjectFile,
    download as downloadProjectFile,
    store as storeProjectFile,
} from '@/routes/management/proyectos/archivos';

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

type ProjectFile = {
    id: number;
    name: string;
    mimeType: string;
    size: number;
    uploadedAt: string | null;
};

const acceptedFileTypes =
    '.pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg,.webp,.txt';

function formatFileSize(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB'];
    const unitIndex = Math.min(
        Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)),
        units.length - 1,
    );
    const size = bytes / 1024 ** unitIndex;

    return `${new Intl.NumberFormat('es-MX', {
        maximumFractionDigits: 1,
    }).format(size)} ${units[unitIndex]}`;
}

const statusLabels: Record<Project['status'], string> = {
    planning: 'Planificación',
    active: 'En curso',
    on_hold: 'En pausa',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

export default function ProjectShow({
    project,
    files,
}: {
    project: Project;
    files: ProjectFile[];
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const uploadForm = useForm<{ file: File | null }>({ file: null });

    function upload(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!selectedFile) {
            return;
        }

        uploadForm.post(storeProjectFile.url(project.id), {
            forceFormData: true,
            onSuccess: () => {
                setSelectedFile(null);
                uploadForm.reset();
                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    }

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
                                        ? formatPEN(project.budget)
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
                    <section className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-950 dark:text-white">
                                Archivos del proyecto
                            </h2>
                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                Adjunta documentos privados de hasta 20 MB. Solo
                                se descargan desde una sesión iniciada.
                            </p>
                        </div>
                        <form
                            className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-start"
                            onSubmit={upload}
                        >
                            <div className="grid gap-1.5">
                                <input
                                    accept={acceptedFileTypes}
                                    aria-describedby="project-file-help project-file-error"
                                    aria-invalid={Boolean(
                                        uploadForm.errors.file,
                                    )}
                                    className="block min-h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:font-medium dark:text-slate-200 dark:file:bg-slate-800"
                                    name="file"
                                    ref={fileInput}
                                    onChange={(event) => {
                                        const file =
                                            event.currentTarget.files?.[0] ??
                                            null;
                                        setSelectedFile(file);
                                        uploadForm.setData('file', file);
                                        uploadForm.clearErrors('file');
                                    }}
                                    type="file"
                                />
                                <p
                                    className="text-xs text-slate-500 dark:text-slate-400"
                                    id="project-file-help"
                                >
                                    PDF, Word, Excel, CSV, PNG, JPG, WebP o TXT.
                                </p>
                                {uploadForm.errors.file && (
                                    <p
                                        className="text-sm text-rose-600"
                                        id="project-file-error"
                                        role="alert"
                                    >
                                        {uploadForm.errors.file}
                                    </p>
                                )}
                            </div>
                            <Button
                                disabled={
                                    !selectedFile || uploadForm.processing
                                }
                                type="submit"
                            >
                                <Upload className="size-4" />
                                {uploadForm.processing
                                    ? 'Subiendo...'
                                    : 'Subir archivo'}
                            </Button>
                        </form>
                        {uploadForm.progress && (
                            <progress
                                aria-label="Progreso de carga"
                                className="h-2 w-full accent-sky-600"
                                max="100"
                                value={uploadForm.progress.percentage}
                            />
                        )}
                        {files.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                Aún no hay archivos adjuntos.
                            </p>
                        ) : (
                            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                                {files.map((file) => (
                                    <li
                                        className="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                        key={file.id}
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <File className="size-5 shrink-0 text-slate-500" />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                                    {file.name}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                    {formatFileSize(file.size)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button asChild variant="outline">
                                                <a
                                                    href={downloadProjectFile.url(
                                                        {
                                                            proyecto:
                                                                project.id,
                                                            archivo: file.id,
                                                        },
                                                    )}
                                                >
                                                    <ArrowDownToLine className="size-4" />
                                                    Descargar
                                                </a>
                                            </Button>
                                            <Button
                                                aria-label={`Eliminar ${file.name}`}
                                                onClick={() => {
                                                    if (
                                                        window.confirm(
                                                            `¿Eliminar "${file.name}"?`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            destroyProjectFile.url(
                                                                {
                                                                    proyecto:
                                                                        project.id,
                                                                    archivo:
                                                                        file.id,
                                                                },
                                                            ),
                                                        );
                                                    }
                                                }}
                                                type="button"
                                                variant="ghost"
                                            >
                                                <Trash2 className="size-4 text-rose-600" />
                                                Eliminar
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
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
