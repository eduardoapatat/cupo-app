import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { MoreHorizontal, Search, UserPlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { store as resendActivation } from '@/actions/App/Http/Controllers/Studio/StudentActivationController';
import {
    index as studentsIndex,
    store as enrollStudent,
    update as updateStudent,
} from '@/actions/App/Http/Controllers/Studio/StudentController';
import EnrollmentLinkField from '@/components/enrollment-link-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type StudentStatus = 'app' | 'activation_sent' | 'no_app';

type Student = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    status: StudentStatus;
    enrolledOn: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    students: Paginated<Student>;
    filters: { search: string; status: string | null };
    counts: Record<'all' | StudentStatus, number>;
    enrollmentUrl: string;
};

const STATUS_LABELS: Record<StudentStatus, string> = {
    app: 'Con app',
    activation_sent: 'Activación enviada',
    no_app: 'Sin app',
};

const ALL = 'todas';

/** The tabs, with the value each one puts in the URL (`?estado=`). */
const TABS: { value: string; label: string; count: 'all' | StudentStatus }[] = [
    { value: ALL, label: 'Todas', count: 'all' },
    { value: 'con-app', label: 'Con app', count: 'app' },
    {
        value: 'activacion-enviada',
        label: 'Activación enviada',
        count: 'activation_sent',
    },
    { value: 'sin-app', label: 'Sin app', count: 'no_app' },
];

/** +56912345678 → +56 9 1234 5678, as people read it. */
function formatPhone(phone: string | null): string {
    const match = phone?.match(/^\+56(9)(\d{4})(\d{4})$/);

    return match ? `+56 ${match[1]} ${match[2]} ${match[3]}` : (phone ?? '');
}

function ContactFields({
    errors,
    student,
}: {
    errors: Partial<Record<string, string>>;
    student?: Student;
}) {
    const prefix = student ? `student-${student.id}-` : '';

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}name`}>Nombre</Label>
                <Input
                    id={`${prefix}name`}
                    {...fieldErrorProps(`${prefix}name`, errors.name)}
                    name="name"
                    defaultValue={student?.name}
                    required
                    autoComplete="off"
                    placeholder="Rosa Fuentes"
                />
                <InputError id={`${prefix}name-error`} message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}phone`}>Celular</Label>
                <Input
                    id={`${prefix}phone`}
                    {...fieldErrorProps(`${prefix}phone`, errors.phone)}
                    name="phone"
                    type="tel"
                    defaultValue={formatPhone(student?.phone ?? null)}
                    required
                    autoComplete="off"
                    placeholder="9 1234 5678"
                />
                <InputError
                    id={`${prefix}phone-error`}
                    message={errors.phone}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}email`}>Correo (opcional)</Label>
                <Input
                    id={`${prefix}email`}
                    {...fieldErrorProps(
                        `${prefix}email`,
                        errors.email,
                        `${prefix}email-help`,
                    )}
                    name="email"
                    type="email"
                    defaultValue={student?.email ?? ''}
                    autoComplete="off"
                    placeholder="correo@ejemplo.com"
                />
                <p
                    id={`${prefix}email-help`}
                    className="text-sm text-muted-foreground"
                >
                    Si lo anotas, le llega un enlace para crear su contraseña y
                    reservar desde el celular.
                </p>
                <InputError
                    id={`${prefix}email-error`}
                    message={errors.email}
                />
            </div>
        </>
    );
}

function EnrollDialog({ slug }: { slug: string }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus />
                    Inscribir alumna
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Inscribir en el mesón</DialogTitle>
                <DialogDescription>
                    Para quien no usa la app, como quien paga en efectivo y
                    reserva en el mesón. Basta con su nombre y su celular.
                </DialogDescription>
                <Form
                    {...enrollStudent.form(slug)}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) => focusFirstError(errors)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ContactFields errors={errors} />

                            <div className="grid gap-2">
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="adult"
                                        name="adult"
                                        required
                                        {...fieldErrorProps(
                                            'adult',
                                            errors.adult,
                                        )}
                                    />
                                    <Label htmlFor="adult">
                                        Confirmo que es mayor de 18 años
                                    </Label>
                                </div>
                                <InputError
                                    id="adult-error"
                                    message={errors.adult}
                                />
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Inscribir
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ResendActivationForm({
    slug,
    student,
}: {
    slug: string;
    student: Student;
}) {
    return (
        <Form
            {...resendActivation.form([slug, student.id])}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <Button type="submit" variant="secondary" disabled={processing}>
                    {processing && <Spinner />}
                    Reenviar activación
                </Button>
            )}
        </Form>
    );
}

/** A part of the profile that arrives later, dimmed like «Pronto» blocks. */
function UpcomingSection({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <section className="grid gap-1">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-sm font-medium">{title}</h3>
                <Badge variant="outline">Pronto</Badge>
            </div>
            <p className="text-sm text-muted-foreground">{description}</p>
        </section>
    );
}

/**
 * The student's profile in a side panel, so the list stays in sight. Her
 * bookings and plan join it in later phases.
 */
function StudentSheet({
    slug,
    student,
    open,
    onOpenChange,
    onCloseAutoFocus,
}: {
    slug: string;
    student: Student;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCloseAutoFocus: (event: Event) => void;
}) {
    const prefix = `student-${student.id}-`;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className="overflow-y-auto"
                onCloseAutoFocus={onCloseAutoFocus}
            >
                <SheetHeader>
                    <SheetTitle>{student.name}</SheetTitle>
                    <SheetDescription>
                        {STATUS_LABELS[student.status]}
                        {student.enrolledOn &&
                            ` · Alumna desde el ${student.enrolledOn}`}
                    </SheetDescription>
                </SheetHeader>

                <div className="grid gap-6 px-4 pb-4">
                    {student.status === 'app' ? (
                        <div className="grid gap-4">
                            <dl className="grid gap-3 text-sm">
                                <div className="grid gap-0.5">
                                    <dt className="text-muted-foreground">
                                        Celular
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatPhone(student.phone) ||
                                            'Sin celular'}
                                    </dd>
                                </div>
                                <div className="grid gap-0.5">
                                    <dt className="text-muted-foreground">
                                        Correo
                                    </dt>
                                    <dd className="break-all">
                                        {student.email}
                                    </dd>
                                </div>
                            </dl>
                            <p className="text-sm text-muted-foreground">
                                Usa la app: ella mantiene sus datos.
                            </p>
                        </div>
                    ) : (
                        <>
                            <Form
                                {...updateStudent.form([slug, student.id])}
                                options={{ preserveScroll: true }}
                                onSuccess={() => onOpenChange(false)}
                                onError={(errors) =>
                                    focusFirstError(errors, {
                                        name: `${prefix}name`,
                                        phone: `${prefix}phone`,
                                        email: `${prefix}email`,
                                    })
                                }
                                className="grid gap-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <ContactFields
                                            errors={errors}
                                            student={student}
                                        />
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Guardar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>

                            {/* Outside the form above: it is a form of its own. */}
                            {student.status === 'activation_sent' && (
                                <div className="grid gap-2">
                                    <p className="text-sm text-muted-foreground">
                                        ¿No le llegó el enlace? Revisa que su
                                        correo esté bien y vuelve a enviárselo.
                                    </p>
                                    <div>
                                        <ResendActivationForm
                                            slug={slug}
                                            student={student}
                                        />
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    <Separator />

                    <UpcomingSection
                        title="Reservas"
                        description="Sus próximas clases y las pasadas. Llega en el punto 2.2."
                    />
                    <UpcomingSection
                        title="Plan y clases"
                        description="Su plan, cuántas clases le quedan y cuándo vence. Llega en la Fase 3."
                    />
                </div>
            </SheetContent>
        </Sheet>
    );
}

function StatusBadge({
    status,
    className,
}: {
    status: StudentStatus;
    className?: string;
}) {
    return (
        <Badge
            variant={status === 'app' ? 'secondary' : 'outline'}
            className={className}
        >
            {STATUS_LABELS[status]}
        </Badge>
    );
}

function StudentRow({
    slug,
    student,
    onOpen,
}: {
    slug: string;
    student: Student;
    onOpen: () => void;
}) {
    return (
        <TableRow>
            <TableCell className="max-w-0 sm:max-w-none">
                <div className="grid min-w-0 justify-items-start leading-tight">
                    <Button
                        variant="link"
                        data-student-trigger={student.id}
                        onClick={onOpen}
                        aria-label={`Ver la ficha de ${student.name}`}
                        className="h-auto max-w-full justify-start p-0 text-foreground"
                    >
                        <span className="truncate">{student.name}</span>
                    </Button>
                    <span className="max-w-full truncate text-sm text-muted-foreground tabular-nums md:hidden">
                        {formatPhone(student.phone)}
                    </span>
                    <span
                        className="max-w-full truncate text-sm text-muted-foreground"
                        title={student.email ?? undefined}
                    >
                        {student.email ?? 'Sin correo'}
                    </span>
                    <StatusBadge
                        status={student.status}
                        className="mt-1.5 sm:hidden"
                    />
                </div>
            </TableCell>
            <TableCell className="hidden tabular-nums md:table-cell">
                {formatPhone(student.phone)}
            </TableCell>
            <TableCell className="hidden sm:table-cell">
                <StatusBadge status={student.status} />
            </TableCell>
            <TableCell className="w-12 text-right">
                {student.status !== 'app' && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Acciones para ${student.name}`}
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onSelect={onOpen}>
                                Editar datos
                            </DropdownMenuItem>
                            {student.status === 'activation_sent' && (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={resendActivation([
                                            slug,
                                            student.id,
                                        ])}
                                        as="button"
                                        preserveScroll
                                        className="w-full"
                                    >
                                        Reenviar activación
                                    </Link>
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </TableCell>
        </TableRow>
    );
}

function Count({ value }: { value: number }) {
    return (
        <span className="text-muted-foreground tabular-nums">({value})</span>
    );
}

export default function Students({
    students,
    filters,
    counts,
    enrollmentUrl,
}: Props) {
    const { studio } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [tab, setTab] = useState(filters.status ?? ALL);
    const [selected, setSelected] = useState<Student | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const searchTimer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(searchTimer.current), []);

    if (!studio) {
        return null;
    }

    const slug = studio.slug;

    /** Reload the list from its first page, keeping the rest of the page. */
    function visit(nextSearch: string, nextTab: string) {
        window.clearTimeout(searchTimer.current);
        router.get(
            studentsIndex.url(slug, {
                query: {
                    buscar: nextSearch.trim() || undefined,
                    estado: nextTab === ALL ? undefined : nextTab,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['students', 'filters', 'counts'],
            },
        );
    }

    function onSearchChange(value: string) {
        setSearch(value);
        window.clearTimeout(searchTimer.current);
        searchTimer.current = window.setTimeout(() => visit(value, tab), 300);
    }

    function onTabChange(value: string) {
        setTab(value);
        visit(search, value);
    }

    // The panel shows the student's latest data; if she leaves the list
    // (another page or filter), it closes.
    const current = selected
        ? (students.data.find((student) => student.id === selected.id) ?? null)
        : null;

    const isFiltered = filters.search !== '' || filters.status !== null;

    return (
        <>
            <Head title="Alumnas" />

            <div className="flex flex-col gap-8 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Alumnas"
                    description="Quienes toman clases en el estudio. Las que usan la app manejan sus propios datos."
                />

                <Card className="gap-0 py-0">
                    <CardHeader className="py-6">
                        <CardTitle>
                            <h2>Que se inscriban solas</h2>
                        </CardTitle>
                        <CardDescription>
                            Comparte el enlace del estudio: quien entra por él
                            crea su cuenta y queda como alumna.
                        </CardDescription>
                    </CardHeader>
                    <Separator />
                    <CardContent className="py-6">
                        <EnrollmentLinkField url={enrollmentUrl} />
                    </CardContent>
                </Card>

                <Tabs value={tab} onValueChange={onTabChange} className="gap-4">
                    {/* Swipes sideways on a phone; the padding keeps the underline. */}
                    <div className="-mx-4 overflow-x-auto px-4 pb-1.5">
                        <TabsList variant="line">
                            {TABS.map((option) => (
                                <TabsTrigger
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}{' '}
                                    <Count value={counts[option.count]} />
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </div>

                    <TabsContent value={tab} className="grid gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="relative w-full sm:max-w-xs">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Label
                                    htmlFor="student-search"
                                    className="sr-only"
                                >
                                    Buscar alumnas
                                </Label>
                                <Input
                                    id="student-search"
                                    // Not type="search": its native clear button comes in blue.
                                    type="text"
                                    enterKeyHint="search"
                                    value={search}
                                    onChange={(event) =>
                                        onSearchChange(event.target.value)
                                    }
                                    autoComplete="off"
                                    maxLength={100}
                                    placeholder="Buscar por nombre, celular o correo"
                                    className="pl-9"
                                />
                            </div>
                            <EnrollDialog slug={slug} />
                        </div>

                        {students.total === 0 ? (
                            <div className="grid justify-items-center gap-3 rounded-lg border px-4 py-8 text-center">
                                <p className="text-sm text-muted-foreground">
                                    {!isFiltered
                                        ? 'Todavía no hay alumnas. Comparte el enlace o inscribe a la primera en el mesón.'
                                        : filters.search !== ''
                                          ? `No encontramos alumnas con «${filters.search}».`
                                          : 'No hay alumnas en esta pestaña.'}
                                </p>
                                {filters.search !== '' && (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => {
                                            setSearch('');
                                            visit('', tab);
                                        }}
                                    >
                                        Limpiar búsqueda
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Alumna</TableHead>
                                                <TableHead className="hidden md:table-cell">
                                                    Celular
                                                </TableHead>
                                                <TableHead className="hidden sm:table-cell">
                                                    Estado
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    <span className="sr-only">
                                                        Acciones
                                                    </span>
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {students.data.map((student) => (
                                                <StudentRow
                                                    key={student.id}
                                                    slug={slug}
                                                    student={student}
                                                    onOpen={() => {
                                                        setSelected(student);
                                                        setSheetOpen(true);
                                                    }}
                                                />
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                {students.last_page > 1 && (
                                    <nav
                                        aria-label="Páginas de alumnas"
                                        className="flex items-center justify-between gap-4 text-sm"
                                    >
                                        <span className="text-muted-foreground">
                                            Página {students.current_page} de{' '}
                                            {students.last_page}
                                        </span>
                                        <div className="flex gap-2">
                                            {students.prev_page_url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={
                                                            students.prev_page_url
                                                        }
                                                        preserveScroll
                                                    >
                                                        Anterior
                                                    </Link>
                                                </Button>
                                            )}
                                            {students.next_page_url && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={
                                                            students.next_page_url
                                                        }
                                                        preserveScroll
                                                    >
                                                        Siguiente
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </nav>
                                )}
                            </>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            {selected && (
                <StudentSheet
                    slug={slug}
                    student={current ?? selected}
                    open={sheetOpen && current !== null}
                    onOpenChange={setSheetOpen}
                    onCloseAutoFocus={(event) => {
                        // The panel has no trigger of its own: the focus goes
                        // back to the student's name in the list.
                        event.preventDefault();
                        document
                            .querySelector<HTMLElement>(
                                `[data-student-trigger="${selected.id}"]`,
                            )
                            ?.focus();
                    }}
                />
            )}
        </>
    );
}

Students.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Alumnas', href: studentsIndex(props.studio.slug) }]
            : []),
    ],
});
