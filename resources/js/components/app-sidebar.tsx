import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    BriefcaseBusiness,
    ClipboardCheck,
    FileText,
    FolderGit2,
    LayoutGrid,
    Shield,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as clientsIndex } from '@/routes/management/clientes';
import { index as projectsIndex } from '@/routes/management/proyectos';
import { index as documentsIndex } from '@/routes/management/documentos';
import { index as usersIndex } from '@/routes/management/usuarios';
import { index as deletionRequestsIndex } from '@/routes/management/solicitudes-eliminacion';
import type { NavItem } from '@/types';
import type { Auth } from '@/types/auth';

type PageProps = { auth: Auth };

const mainNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Clientes',
        href: clientsIndex(),
        icon: Users,
    },
    {
        title: 'Proyectos',
        href: projectsIndex(),
        icon: BriefcaseBusiness,
    },
    {
        title: 'Cotizaciones y facturas',
        href: documentsIndex(),
        icon: FileText,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repositorio',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentación',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;
    const items =
        auth.user.role === 'admin'
            ? [
                  ...mainNavItems,
                  {
                      title: 'Usuarios y roles',
                      href: usersIndex(),
                      icon: Shield,
                  },
                  {
                      title: 'Solicitudes de eliminación',
                      href: deletionRequestsIndex(),
                      icon: ClipboardCheck,
                  },
              ]
            : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
