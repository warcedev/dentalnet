import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import patients from '@/routes/patients';
import type { Paginated, PatientListItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { AlertTriangle, Search, UserPlus, Users } from 'lucide-react';

type Props = {
    patients: Paginated<PatientListItem>;
    filters: { buscar: string; archivados: boolean };
    can: { create: boolean; view_archived: boolean };
};

export default function PatientsIndex({ patients: list, filters, can }: Props) {
    const searching = filters.buscar !== '';

    return (
        <>
            <Head title="Pacientes" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={filters.archivados ? 'Pacientes archivados' : 'Pacientes'}
                    description="Busque por nombre, número de documento o teléfono."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={patients.create()}>
                                    <UserPlus aria-hidden="true" />
                                    Nuevo paciente
                                </Link>
                            </Button>
                        )
                    }
                />

                <Form
                    {...patients.index.form()}
                    options={{ preserveState: true, replace: true }}
                    role="search"
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    {({ processing }) => (
                        <>
                            <div className="grid flex-1 gap-2 sm:max-w-md">
                                <Label htmlFor="buscar">Buscar paciente</Label>
                                <Input
                                    id="buscar"
                                    name="buscar"
                                    type="search"
                                    defaultValue={filters.buscar}
                                    placeholder="Ej.: Benítez, 4567890 o 0981…"
                                    autoFocus
                                    autoComplete="off"
                                />
                            </div>
                            {can.view_archived && (
                                <div className="flex items-center gap-2 pb-2">
                                    <Checkbox
                                        id="archivados"
                                        name="archivados"
                                        value="1"
                                        defaultChecked={filters.archivados}
                                    />
                                    <Label htmlFor="archivados">Ver archivados</Label>
                                </div>
                            )}
                            <Button type="submit" variant="secondary" disabled={processing}>
                                <Search aria-hidden="true" />
                                Buscar
                            </Button>
                        </>
                    )}
                </Form>

                {list.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={
                            searching ? 'No se encontraron pacientes' : 'Todavía no hay pacientes'
                        }
                        description={
                            searching
                                ? 'Revise la búsqueda o registre al paciente.'
                                : 'Registre el primer paciente para empezar.'
                        }
                        action={
                            can.create && (
                                <Button asChild variant="outline">
                                    <Link href={patients.create()}>Registrar paciente</Link>
                                </Button>
                            )
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">Listado de pacientes</caption>
                            <thead className="border-b bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Paciente
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Documento
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium sm:table-cell"
                                    >
                                        Edad
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium md:table-cell"
                                    >
                                        Teléfono
                                    </th>
                                    <th scope="col" className="px-4 py-3 font-medium">
                                        Alertas
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="border-b last:border-0 hover:bg-muted/40"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={patients.show(p.id)}
                                                className="font-medium text-primary underline-offset-4 hover:underline"
                                            >
                                                {p.full_name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">{p.document}</td>
                                        <td className="hidden px-4 py-3 sm:table-cell">
                                            {p.age} años
                                        </td>
                                        <td className="hidden px-4 py-3 md:table-cell">
                                            {p.phone ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {!!p.allergies && (
                                                    <Badge className="bg-red-600 text-white">
                                                        <AlertTriangle aria-hidden="true" />
                                                        {p.allergies === 1
                                                            ? 'Alergia'
                                                            : `${p.allergies} alergias`}
                                                    </Badge>
                                                )}
                                                {!!p.conditions && (
                                                    <Badge className="bg-amber-200 text-amber-950 dark:bg-amber-800 dark:text-amber-50">
                                                        {p.conditions === 1
                                                            ? 'Condición'
                                                            : `${p.conditions} condiciones`}
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination page={list} itemLabel="pacientes" />
            </div>
        </>
    );
}

PatientsIndex.layout = {
    breadcrumbs: [{ title: 'Pacientes', href: patients.index() }],
};
