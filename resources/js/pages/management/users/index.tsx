import { Form, Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as usersIndex,
    invitacion as resendInvitation,
    rol as updateUserRole,
    store as createUser,
} from '@/routes/management/usuarios';
import type { User } from '@/types/auth';

type Role = User['role'];
type ManagedUser = Pick<
    User,
    'id' | 'name' | 'email' | 'email_verified_at' | 'role' | 'created_at'
>;
type PageLink = { url: string | null; label: string; active: boolean };
type PageProps = {
    users: {
        data: ManagedUser[];
        links: PageLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    roles: Record<Role, string>;
};

export default function UsersIndex({ users, roles }: PageProps) {
    return (
        <>
            <Head title="Usuarios y roles" />
            <main className="min-h-[calc(100vh-4rem)] bg-slate-50/80 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950/40">
                <div className="mx-auto max-w-6xl space-y-6">
                    <header>
                        <p className="text-sm font-medium text-sky-600 dark:text-sky-400">
                            Administración
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                            Usuarios y roles
                        </h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Crea cuentas, asigna permisos y consulta el estado
                            de verificación.
                        </p>
                    </header>

                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="text-lg font-semibold text-slate-950 dark:text-white">
                            Crear usuario
                        </h2>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Se enviará un enlace para verificar el correo y
                            establecer la contraseña.
                        </p>
                        <Form
                            {...createUser.form()}
                            resetOnSuccess
                            className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-user-name">
                                            Nombre
                                        </Label>
                                        <Input
                                            id="new-user-name"
                                            name="name"
                                            autoComplete="name"
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-user-email">
                                            Correo electrónico
                                        </Label>
                                        <Input
                                            id="new-user-email"
                                            name="email"
                                            type="email"
                                            autoComplete="email"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-user-role">
                                            Rol
                                        </Label>
                                        <select
                                            id="new-user-role"
                                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                            defaultValue="manager"
                                            name="role"
                                            required
                                        >
                                            {Object.entries(roles).map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError message={errors.role} />
                                    </div>
                                    <div className="flex items-end">
                                        <Button
                                            className="w-full"
                                            disabled={processing}
                                            type="submit"
                                        >
                                            Crear y enviar invitación
                                        </Button>
                                    </div>
                                    <InputError
                                        className="sm:col-span-2 lg:col-span-4"
                                        message={errors.invitation}
                                    />
                                </>
                            )}
                        </Form>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Usuario
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Correo
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Verificación
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Rol
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Invitación
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {users.data.map((user) => (
                                        <UserRoleRow
                                            key={user.id}
                                            roles={roles}
                                            user={user}
                                        />
                                    ))}
                                    {users.data.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-5 py-12 text-center text-slate-500 dark:text-slate-400"
                                                colSpan={5}
                                            >
                                                No hay usuarios registrados.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="flex flex-col justify-between gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center dark:border-slate-800">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {users.from ?? 0}–{users.to ?? 0} de{' '}
                                {users.total} usuarios
                            </p>
                            <nav
                                aria-label="Paginación de usuarios"
                                className="flex flex-wrap gap-1"
                            >
                                {users.links.map((link, index) => (
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

function UserRoleRow({
    user,
    roles,
}: {
    user: ManagedUser;
    roles: Record<Role, string>;
}) {
    const [role, setRole] = useState<Role>(user.role);
    const [error, setError] = useState('');
    const isDirty = role !== user.role;

    function submitRole(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');
        router.patch(
            updateUserRole.url(user.id),
            { role },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(errors.role ?? 'No se pudo actualizar el rol.'),
                onSuccess: () => setRole(role),
            },
        );
    }

    return (
        <tr className="hover:bg-slate-50/80 dark:hover:bg-slate-950/30">
            <td className="px-5 py-4">
                <p className="font-medium text-slate-900 dark:text-white">
                    {user.name}
                </p>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Registrado el{' '}
                    {new Date(user.created_at).toLocaleDateString('es-PE')}
                </p>
            </td>
            <td className="px-5 py-4 text-slate-600 dark:text-slate-300">
                {user.email}
            </td>
            <td className="px-5 py-4">
                <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${user.email_verified_at ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'}`}
                >
                    {user.email_verified_at ? 'Verificado' : 'Pendiente'}
                </span>
            </td>
            <td className="px-5 py-4">
                <form
                    className="flex flex-wrap items-start gap-2"
                    onSubmit={submitRole}
                >
                    <div>
                        <select
                            aria-label={`Rol de ${user.name}`}
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            onChange={(event) =>
                                setRole(event.target.value as Role)
                            }
                            value={role}
                        >
                            {Object.entries(roles).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        {error && (
                            <p className="mt-1 text-xs text-rose-600">
                                {error}
                            </p>
                        )}
                    </div>
                    {isDirty && (
                        <Button size="sm" type="submit">
                            Guardar
                        </Button>
                    )}
                </form>
            </td>
            <td className="px-5 py-4">
                {!user.email_verified_at && (
                    <Button
                        onClick={() =>
                            router.post(
                                resendInvitation.url(user.id),
                                {},
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                        size="sm"
                        variant="outline"
                    >
                        Reenviar enlace
                    </Button>
                )}
            </td>
        </tr>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Usuarios y roles', href: usersIndex() }],
};
