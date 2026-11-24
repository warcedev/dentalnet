import { agenda, dashboard } from '@/routes';
import admin from '@/routes/admin';
import patients from '@/routes/patients';
import reminders from '@/routes/reminders';
import reports from '@/routes/reports';
import waitingList from '@/routes/waiting-list';
import type { NavItem } from '@/types';
import {
    BellRing,
    CalendarCog,
    CalendarDays,
    ChartColumn,
    FileSignature,
    History,
    LayoutGrid,
    ListTodo,
    ReceiptText,
    Stethoscope,
    Users,
} from 'lucide-react';

export type NavGroup = {
    label: string;
    items: (NavItem & { permission?: string })[];
};

/**
 * Menú principal. Cada ítem declara el permiso que necesita; los módulos de
 * las fases siguientes se agregan aquí.
 */
export const navGroups: NavGroup[] = [
    {
        label: 'Consultorio',
        items: [
            { title: 'Inicio', href: dashboard(), icon: LayoutGrid },
            {
                title: 'Agenda',
                href: agenda(),
                icon: CalendarDays,
                permission: 'appointments.view',
            },
            {
                title: 'Pacientes',
                href: patients.index(),
                icon: Stethoscope,
                permission: 'patients.view',
            },
            {
                title: 'Lista de espera',
                href: waitingList.index(),
                icon: ListTodo,
                permission: 'appointments.view',
            },
            {
                title: 'Recordatorios',
                href: reminders.index(),
                icon: BellRing,
                permission: 'reminders.manage',
            },
            {
                title: 'Reportes',
                href: reports.index(),
                icon: ChartColumn,
                permission: 'reports.view',
            },
        ],
    },
    {
        label: 'Administración',
        items: [
            {
                title: 'Usuarios',
                href: admin.users.index(),
                icon: Users,
                permission: 'users.manage',
            },
            {
                title: 'Agenda',
                href: admin.agendaSettings(),
                icon: CalendarCog,
                permission: 'settings.manage',
            },
            {
                title: 'Procedimientos',
                href: admin.procedures.index(),
                icon: ReceiptText,
                permission: 'settings.manage',
            },
            {
                title: 'Consentimientos',
                href: admin.consents.index(),
                icon: FileSignature,
                permission: 'settings.manage',
            },
            {
                title: 'Auditoría',
                href: admin.audit.index(),
                icon: History,
                permission: 'audit.view',
            },
        ],
    },
];

export function visibleNavGroups(can: (permission: string) => boolean): NavGroup[] {
    return navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.permission || can(item.permission)),
        }))
        .filter((group) => group.items.length > 0);
}
