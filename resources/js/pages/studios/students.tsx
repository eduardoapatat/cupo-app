import {
    Form,
    Head,
    InfiniteScroll,
    Link,
    router,
    usePage,
} from '@inertiajs/react';
import { ArrowLeft, Link2, Search, UserPlus, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { store as resendActivation } from '@/actions/App/Http/Controllers/Studio/StudentActivationController';
import {
    index as studentsIndex,
    show as studentShow,
    store as enrollStudent,
    update as updateStudent,
} from '@/actions/App/Http/Controllers/Studio/StudentController';
import { update as renewEnrollmentLink } from '@/actions/App/Http/Controllers/Studio/EnrollmentLinkController';
import ConfirmDialog from '@/components/confirm-dialog';
import EnrollmentLinkField from '@/components/enrollment-link-field';
import InitialsAvatar from '@/components/initials-avatar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { cn } from '@/lib/utils';
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

type Filters = { search: string; status: string | null };

type Props = {
    students: { data: Student[] };
    filters: Filters;
    counts: Record<'all' | StudentStatus, number>;
    enrollmentUrl: string;
    canRenewEnrollmentLink: boolean;
    /** The student whose profile is open, if any. */
    student: Student | null;
};

const STATUS_LABELS: Record<StudentStatus, string> = {
    app: 'Con app',
    activation_sent: 'Activación enviada',
    no_app: 'Sin app',
};

const ALL = 'todas';

/** The status filter, with the value each option puts in the URL (`?estado=`). */
const STATUS_FILTERS: {
    value: string;
    label: string;
    count: 'all' | StudentStatus;
}[] = [
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

/** The list or a profile, keeping the search and the filter in the URL. */
function studentsUrl(
    slug: string,
    studentId: number | null,
    search: string,
    status: string | null,
): string {
    const options = {
        query: {
            buscar: search.trim() || undefined,
            estado: status && status !== ALL ? status : undefined,
        },
    };

    return studentId === null
        ? studentsIndex.url(slug, options)
        : studentShow.url([slug, studentId], options);
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
                <Button size="sm" className="flex-1">
                    <UserPlus />
                    Inscribir
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

/**
 * The enrollment link to share. The owner can also renew it here, for
 * example after it was shared where it should not have been.
 */
function ShareLinkDialog({
    slug,
    url,
    canRenew,
}: {
    slug: string;
    url: string;
    canRenew: boolean;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="flex-1">
                    <Link2 />
                    Compartir enlace
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Que se inscriban solas</DialogTitle>
                <DialogDescription>
                    Comparte el enlace del estudio: quien entra por él crea su
                    cuenta y queda como alumna.
                </DialogDescription>
                <EnrollmentLinkField url={url} />
                {canRenew && (
                    <div className="flex flex-col items-start gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            ¿Se compartió donde no debía? Genera uno nuevo y el
                            actual deja de servir.
                        </p>
                        <ConfirmDialog
                            trigger={
                                <Button variant="outline" size="sm">
                                    Generar un enlace nuevo
                                </Button>
                            }
                            title="¿Generar un enlace nuevo?"
                            description="El enlace actual dejará de servir de inmediato. Quienes ya son alumnas no se ven afectadas."
                            action={renewEnrollmentLink.form(slug)}
                            confirmLabel="Generar enlace nuevo"
                        />
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function EditStudentDialog({
    slug,
    student,
}: {
    slug: string;
    student: Student;
}) {
    const [open, setOpen] = useState(false);
    const prefix = `student-${student.id}-`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Editar datos</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Datos de {student.name}</DialogTitle>
                <DialogDescription>
                    Mientras no use la app, recepción mantiene sus datos al día.
                </DialogDescription>
                <Form
                    {...updateStudent.form([slug, student.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
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
                            <ContactFields errors={errors} student={student} />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StudentListItem({
    student,
    href,
    selected,
}: {
    student: Student;
    href: string;
    selected: boolean;
}) {
    const phone = formatPhone(student.phone);

    return (
        <li>
            <Link
                href={href}
                only={['student']}
                preserveState
                preserveScroll
                aria-current={selected ? 'page' : undefined}
                className={cn(
                    'flex items-center gap-3 border-l-2 border-transparent px-4 py-3 outline-none hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:ring-inset',
                    selected && 'border-foreground bg-muted hover:bg-muted',
                )}
            >
                <InitialsAvatar name={student.name} />
                <span className="grid min-w-0 leading-tight">
                    <span className="truncate font-medium">{student.name}</span>
                    <span className="truncate text-sm text-muted-foreground">
                        {STATUS_LABELS[student.status]}
                        {phone && (
                            <span className="tabular-nums"> · {phone}</span>
                        )}
                    </span>
                </span>
            </Link>
        </li>
    );
}

function ProfileRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1 py-3 sm:grid-cols-[8rem_1fr] sm:gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="grid justify-items-start gap-3 text-sm">
                {children}
            </dd>
        </div>
    );
}

function AppStatus({ slug, student }: { slug: string; student: Student }) {
    if (student.status === 'app') {
        return <p>Usa la app: ella mantiene sus datos.</p>;
    }

    if (student.status === 'no_app') {
        return (
            <p>
                No usa la app: recepción mantiene sus datos. Si anotas su
                correo, le llega el enlace para activarla.
            </p>
        );
    }

    return (
        <>
            <p>
                Le enviamos el enlace para activar su cuenta a{' '}
                <span className="break-all">{student.email}</span>. ¿No le
                llegó? Revisa que el correo esté bien y vuelve a enviárselo.
            </p>
            <Form
                {...resendActivation.form([slug, student.id])}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="secondary"
                        size="sm"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Reenviar activación
                    </Button>
                )}
            </Form>
        </>
    );
}

/**
 * The student's profile. Her bookings (2.2) and her plan and payments
 * (phase 3) arrive as tabs of their own.
 */
function StudentProfile({
    slug,
    student,
    backHref,
}: {
    slug: string;
    student: Student;
    backHref: string;
}) {
    return (
        <article className="grid max-w-3xl min-w-0 gap-6 p-4 md:p-6">
            <Button
                asChild
                variant="ghost"
                size="sm"
                className="-ml-2 w-fit md:hidden"
            >
                <Link href={backHref} only={['student']} preserveState>
                    <ArrowLeft />
                    Alumnas
                </Link>
            </Button>

            <header className="flex flex-wrap items-start gap-4">
                <InitialsAvatar
                    name={student.name}
                    className="size-14 text-lg"
                />
                <div className="grid min-w-0 flex-1 gap-1.5">
                    <h2 className="text-xl font-semibold tracking-tight break-words">
                        {student.name}
                    </h2>
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <Badge
                            variant={
                                student.status === 'app'
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {STATUS_LABELS[student.status]}
                        </Badge>
                        {student.enrolledOn && (
                            <span>Alumna desde el {student.enrolledOn}</span>
                        )}
                    </div>
                </div>
                {student.status !== 'app' && (
                    <EditStudentDialog
                        key={student.id}
                        slug={slug}
                        student={student}
                    />
                )}
            </header>

            <Tabs defaultValue="data" className="min-w-0 gap-4">
                {/* Swipes sideways when it does not fit; the padding keeps the underline. */}
                <div className="overflow-x-auto pb-1.5">
                    <TabsList variant="line">
                        <TabsTrigger value="data">Datos</TabsTrigger>
                        <TabsTrigger value="bookings" disabled>
                            Reservas <Badge variant="outline">Pronto</Badge>
                        </TabsTrigger>
                        <TabsTrigger value="plan" disabled>
                            Plan y pagos <Badge variant="outline">Pronto</Badge>
                        </TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="data">
                    <dl className="divide-y">
                        <ProfileRow label="Celular">
                            <span className="tabular-nums">
                                {formatPhone(student.phone) || 'Sin celular'}
                            </span>
                        </ProfileRow>
                        <ProfileRow label="Correo">
                            <span className="break-all">
                                {student.email ?? 'Sin correo'}
                            </span>
                        </ProfileRow>
                        <ProfileRow label="App">
                            <AppStatus slug={slug} student={student} />
                        </ProfileRow>
                    </dl>
                </TabsContent>
            </Tabs>
        </article>
    );
}

function NoStudentSelected() {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-2 p-6 text-center">
            <Users
                aria-hidden="true"
                className="size-8 text-muted-foreground"
            />
            <p className="font-medium">Elige una alumna para ver su ficha</p>
            <p className="max-w-xs text-sm text-muted-foreground">
                Aquí ves sus datos y, pronto, sus reservas y su plan.
            </p>
        </div>
    );
}

export default function Students({
    students,
    filters,
    counts,
    enrollmentUrl,
    canRenewEnrollmentLink,
    student,
}: Props) {
    const { studio } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status ?? ALL);
    const searchTimer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(searchTimer.current), []);

    if (!studio) {
        return null;
    }

    const slug = studio.slug;
    const selectedId = student?.id ?? null;

    /** Reload the list from its first page for a new search or filter. */
    function visit(nextSearch: string, nextStatus: string) {
        window.clearTimeout(searchTimer.current);
        router.get(
            studentsUrl(slug, selectedId, nextSearch, nextStatus),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['students', 'filters', 'counts'],
                reset: ['students'],
            },
        );
    }

    function onSearchChange(value: string) {
        setSearch(value);
        window.clearTimeout(searchTimer.current);
        searchTimer.current = window.setTimeout(
            () => visit(value, status),
            300,
        );
    }

    function onStatusChange(value: string) {
        setStatus(value);
        visit(search, value);
    }

    const isFiltered = filters.search !== '' || filters.status !== null;

    return (
        <>
            <Head title={student ? `${student.name} · Alumnas` : 'Alumnas'} />

            <div className="flex flex-col md:h-[calc(100svh-5rem)] md:flex-row">
                {/* On a phone, one screen at a time: the list or a profile. */}
                <section
                    aria-labelledby="students-title"
                    className={cn(
                        'flex min-h-0 flex-col md:w-72 md:shrink-0 md:border-r lg:w-80 xl:w-96',
                        student && 'hidden md:flex',
                    )}
                >
                    <div className="grid gap-3 border-b p-4">
                        <h1
                            id="students-title"
                            className="text-xl font-semibold tracking-tight"
                        >
                            Alumnas{' '}
                            <span className="font-normal text-muted-foreground tabular-nums">
                                ({counts.all})
                            </span>
                        </h1>
                        <div className="flex gap-2">
                            <EnrollDialog slug={slug} />
                            <ShareLinkDialog
                                slug={slug}
                                url={enrollmentUrl}
                                canRenew={canRenewEnrollmentLink}
                            />
                        </div>
                        <div className="relative">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Label htmlFor="student-search" className="sr-only">
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
                                placeholder="Nombre, celular o correo"
                                className="pl-9"
                            />
                        </div>
                        <Select value={status} onValueChange={onStatusChange}>
                            <SelectTrigger
                                aria-label="Filtrar por estado"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_FILTERS.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label} ({counts[option.count]})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="md:min-h-0 md:flex-1 md:overflow-y-auto">
                        {students.data.length === 0 ? (
                            <div className="grid justify-items-center gap-3 px-4 py-8 text-center">
                                <p className="text-sm text-muted-foreground">
                                    {!isFiltered
                                        ? 'Todavía no hay alumnas. Comparte el enlace o inscribe a la primera en el mesón.'
                                        : filters.search !== ''
                                          ? `No encontramos alumnas con «${filters.search}».`
                                          : 'No hay alumnas con este estado.'}
                                </p>
                                {filters.search !== '' && (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => {
                                            setSearch('');
                                            visit('', status);
                                        }}
                                    >
                                        Limpiar búsqueda
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <InfiniteScroll
                                data="students"
                                as="ul"
                                onlyNext
                                preserveUrl
                                aria-label="Alumnas"
                                className="divide-y"
                                loading={
                                    <p className="flex items-center justify-center gap-2 py-4 text-sm text-muted-foreground">
                                        <Spinner />
                                        Cargando más alumnas
                                    </p>
                                }
                            >
                                {students.data.map((item) => (
                                    <StudentListItem
                                        key={item.id}
                                        student={item}
                                        selected={item.id === selectedId}
                                        href={studentsUrl(
                                            slug,
                                            item.id,
                                            filters.search,
                                            filters.status,
                                        )}
                                    />
                                ))}
                            </InfiniteScroll>
                        )}
                    </div>
                </section>

                <div
                    className={cn(
                        'min-w-0 flex-1 md:overflow-y-auto',
                        !student && 'hidden md:block',
                    )}
                >
                    {student ? (
                        <StudentProfile
                            slug={slug}
                            student={student}
                            backHref={studentsUrl(
                                slug,
                                null,
                                filters.search,
                                filters.status,
                            )}
                        />
                    ) : (
                        <NoStudentSelected />
                    )}
                </div>
            </div>
        </>
    );
}

Students.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Alumnas', href: studentsIndex(props.studio.slug) }]
            : []),
        ...(props.studio && props.student
            ? [
                  {
                      title: props.student.name,
                      href: studentShow([props.studio.slug, props.student.id]),
                  },
              ]
            : []),
    ],
});
