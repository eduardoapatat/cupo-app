import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    create as sellCreate,
    store as sellStore,
    students as sellStudents,
} from '@/actions/App/Http/Controllers/Studio/SellPlanController';
import { index as cashRegisterIndex } from '@/actions/App/Http/Controllers/Studio/CashRegisterController';
import Heading from '@/components/heading';
import InitialsAvatar from '@/components/initials-avatar';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { formatClp } from '@/lib/money';
import { formatPhone } from '@/lib/phone';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio, Option } from '@/types';

type SalePlan = {
    id: number;
    name: string;
    price: number;
    credits: number | null;
    validityDays: number;
    dailyLimit: number | null;
    /** Its last day if sold today, in words. */
    lastDay: string;
};

type StudentPlan = {
    name: string;
    creditsLeft: number | null;
    lastDay: string;
};

type SaleStudent = {
    id: number;
    name: string;
    phone: string | null;
    plans: StudentPlan[];
};

type Props = {
    plans: SalePlan[];
    student: SaleStudent | null;
    methods: Option[];
    canChangePrice: boolean;
    /** Her cash register: sales go into today's open one. */
    register: {
        state: 'open' | 'none' | 'closed' | 'stale';
        /** The day of a register left open on another day. */
        day: string | null;
        /** Since when today's register is open. */
        openedAt: string | null;
    };
};

const METHOD_HELP: Record<string, string> = {
    cash: 'Recibe el efectivo en el mesón.',
    card: 'Cóbrala en el POS del estudio antes de vender.',
    transfer: 'Revisa que la transferencia haya llegado antes de vender.',
};

function classesLabel(credits: number | null): string {
    if (credits === null) {
        return 'Clases ilimitadas';
    }

    return credits === 1 ? '1 clase' : `${credits} clases`;
}

function currentPlanLabel(plan: StudentPlan): string {
    const left =
        plan.creditsLeft === null
            ? 'ilimitado'
            : plan.creditsLeft === 1
              ? 'le queda 1 clase'
              : `le quedan ${plan.creditsLeft} clases`;

    return `${plan.name}: ${left}, vale hasta el ${plan.lastDay}`;
}

/** Search the studio's students and pick the one who buys. */
function StudentPicker({
    slug,
    student,
    onChange,
    error,
}: {
    slug: string;
    student: SaleStudent | null;
    onChange: (student: SaleStudent | null) => void;
    error?: string;
}) {
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<SaleStudent[] | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (student) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(
                sellStudents.url(slug, {
                    query: { buscar: search.trim() || undefined },
                }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    return response.json() as Promise<{
                        students: SaleStudent[];
                    }>;
                })
                .then((data) => {
                    setFailed(false);
                    setResults(data.students);
                })
                .catch((caught: unknown) => {
                    if (
                        caught instanceof DOMException &&
                        caught.name === 'AbortError'
                    ) {
                        return;
                    }

                    setFailed(true);
                    setResults([]);
                });
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [search, slug, student]);

    if (student) {
        return (
            <div className="grid gap-3 rounded-xl border p-4">
                <div className="flex items-center justify-between gap-3">
                    <span className="flex min-w-0 items-center gap-3">
                        <InitialsAvatar name={student.name} />
                        <span className="grid min-w-0 leading-tight">
                            <span className="truncate font-medium">
                                {student.name}
                            </span>
                            {student.phone && (
                                <span className="truncate text-sm text-muted-foreground">
                                    {formatPhone(student.phone)}
                                </span>
                            )}
                        </span>
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSearch('');
                            setResults(null);
                            onChange(null);
                        }}
                    >
                        Cambiar
                    </Button>
                </div>
                <p className="text-sm text-muted-foreground">
                    {student.plans.length === 0
                        ? 'No tiene un plan vigente.'
                        : student.plans.map(currentPlanLabel).join('. ') + '.'}
                </p>
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="sale-student"
                    {...fieldErrorProps('sale-student', error)}
                    type="text"
                    enterKeyHint="search"
                    aria-label="Buscar alumna"
                    placeholder="Nombre, celular o correo"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={(event) => {
                        // Enter searches; it never submits the sale.
                        if (event.key === 'Enter') {
                            event.preventDefault();
                        }
                    }}
                    autoComplete="off"
                    className="pl-9"
                />
            </div>
            <InputError id="sale-student-error" message={error} />
            <div
                aria-live="polite"
                className="grid max-h-64 overflow-y-auto rounded-xl border"
            >
                {failed ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No pudimos buscar. Recarga la página e inténtalo de
                        nuevo.
                    </p>
                ) : results === null ? (
                    <p className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
                        <Spinner /> Buscando…
                    </p>
                ) : results.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        No encontramos alumnas con esa búsqueda.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {results.map((result) => (
                            <li key={result.id}>
                                <button
                                    type="button"
                                    onClick={() => onChange(result)}
                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset"
                                >
                                    <InitialsAvatar name={result.name} />
                                    <span className="grid min-w-0 leading-tight">
                                        <span className="truncate font-medium">
                                            {result.name}
                                        </span>
                                        <span className="truncate text-sm text-muted-foreground">
                                            {result.plans.length === 0
                                                ? 'Sin plan vigente'
                                                : result.plans
                                                      .map((plan) => plan.name)
                                                      .join(', ')}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

/**
 * Her cash register, next to the title: open since when, or what to do
 * before selling. A filled dot is open; a hollow one, not.
 */
function RegisterStatus({
    slug,
    register,
}: {
    slug: string;
    register: Props['register'];
}) {
    const status = {
        open: {
            label: `Caja abierta · desde las ${register.openedAt}`,
            action: 'Ver caja',
        },
        none: { label: 'Caja cerrada', action: 'Abrir caja' },
        stale: {
            label: `Tu caja del ${register.day} sigue abierta`,
            action: 'Cerrarla',
        },
        closed: { label: 'Ya cerraste tu caja de hoy', action: 'Ver caja' },
    }[register.state];
    const isOpen = register.state === 'open';

    return (
        <div className="flex flex-wrap items-center gap-3 rounded-full border py-1 pr-1 pl-3 text-sm">
            <span className="flex items-center gap-2">
                <span
                    className={cn(
                        'size-2 rounded-full border border-foreground',
                        isOpen && 'bg-foreground',
                    )}
                    aria-hidden="true"
                />
                <span className="first-letter:uppercase">{status.label}</span>
            </span>
            <Button
                asChild
                size="sm"
                variant={isOpen ? 'ghost' : 'default'}
                className="h-7 rounded-full"
            >
                <Link href={cashRegisterIndex(slug)}>{status.action}</Link>
            </Button>
        </div>
    );
}

type Step = 1 | 2 | 3;

const STEPS: { step: Step; title: string; help: string }[] = [
    { step: 1, title: 'Alumna', help: 'A quién le vendes' },
    { step: 2, title: 'Plan', help: 'Qué compra' },
    { step: 3, title: 'Cobro', help: 'Cómo paga y confirmar' },
];

/**
 * The three steps on top: the current one filled, the done ones with a
 * check. A step can be opened once the ones before it are done.
 */
function Steps({
    current,
    reachable,
    onSelect,
}: {
    current: Step;
    reachable: (step: Step) => boolean;
    onSelect: (step: Step) => void;
}) {
    return (
        <ol className="grid grid-cols-3 gap-2 sm:gap-3">
            {STEPS.map(({ step, title, help }) => {
                const isCurrent = step === current;
                const isDone = step < current;

                return (
                    <li key={step}>
                        <button
                            type="button"
                            onClick={() => onSelect(step)}
                            disabled={!reachable(step) || isCurrent}
                            aria-current={isCurrent ? 'step' : undefined}
                            className={cn(
                                'flex w-full items-center gap-2 rounded-xl border p-2 text-left transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:gap-3 sm:p-4',
                                isCurrent
                                    ? 'border-primary shadow-xs'
                                    : 'enabled:hover:bg-muted/50 disabled:cursor-default',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-medium tabular-nums',
                                    isCurrent &&
                                        'border-primary bg-primary text-primary-foreground',
                                    !isCurrent &&
                                        !isDone &&
                                        'text-muted-foreground',
                                )}
                            >
                                {isDone ? (
                                    <Check
                                        className="size-4"
                                        aria-label="Listo"
                                    />
                                ) : (
                                    step
                                )}
                            </span>
                            <span className="grid min-w-0 leading-tight">
                                <span
                                    className={cn(
                                        'truncate text-sm font-medium',
                                        !isCurrent &&
                                            !isDone &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {title}
                                </span>
                                <span className="hidden truncate text-sm text-muted-foreground sm:block">
                                    {help}
                                </span>
                            </span>
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}

export default function SellPlan({
    plans,
    student: initialStudent,
    methods,
    canChangePrice,
    register,
}: Props) {
    const { studio } = usePage().props;
    const [student, setStudent] = useState(initialStudent);
    const [planId, setPlanId] = useState<string>(
        plans.length === 1 ? String(plans[0].id) : '',
    );
    const [step, setStep] = useState<Step>(initialStudent ? 2 : 1);
    const [method, setMethod] = useState('cash');
    const [changesPrice, setChangesPrice] = useState(false);
    const [price, setPrice] = useState('');
    const headings = useRef<Partial<Record<Step, HTMLHeadingElement | null>>>(
        {},
    );
    const moved = useRef(false);

    // On a new step, the focus goes to its title (not on the first render).
    useEffect(() => {
        if (!moved.current) {
            moved.current = true;

            return;
        }

        headings.current[step]?.focus();
    }, [step]);

    if (!studio) {
        return null;
    }

    const plan = plans.find((item) => String(item.id) === planId) ?? null;
    const total =
        changesPrice && price !== ''
            ? Number.parseInt(price, 10) || 0
            : (plan?.price ?? 0);
    const methodLabel =
        methods.find((option) => option.value === method)?.label ?? '';
    const reachable = (target: Step): boolean =>
        target === 1 ||
        (target === 2 && student !== null) ||
        (target === 3 && student !== null && plan !== null);
    const canContinue = reachable((step + 1) as Step);
    const firstName = student?.name.split(' ')[0] ?? '';

    const heading = (target: Step, text: string, help: string) => (
        <div className="grid gap-1">
            <h2
                ref={(element) => {
                    headings.current[target] = element;
                }}
                tabIndex={-1}
                className="font-semibold outline-none"
            >
                {text}
            </h2>
            <p className="text-sm text-muted-foreground">{help}</p>
        </div>
    );
    const panel = 'rounded-xl border p-4 sm:p-6';

    return (
        <>
            <Head title="Vender plan" />

            <div className="flex flex-1 flex-col gap-6 px-4 pt-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="mt-0.5 shrink-0"
                            aria-label="Volver"
                            onClick={() =>
                                window.history.length > 1
                                    ? window.history.back()
                                    : router.visit(dashboard())
                            }
                        >
                            <ArrowLeft aria-hidden="true" />
                        </Button>
                        <Heading
                            className="mb-0"
                            title="Vender plan"
                            description="Elige a la alumna, el plan y cómo paga. Las clases quedan disponibles al tiro."
                        />
                    </div>
                    <RegisterStatus slug={studio.slug} register={register} />
                </div>

                {plans.length === 0 ? (
                    <p className="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                        Todavía no hay planes a la venta. La dueña los crea en
                        «Planes».
                    </p>
                ) : (
                    <Form
                        {...sellStore.form(studio.slug)}
                        onError={(errors) => {
                            // Back to the step of the first field with an
                            // error, then to that field once it shows.
                            setStep(
                                errors.user_id ? 1 : errors.plan_id ? 2 : 3,
                            );
                            window.setTimeout(() =>
                                focusFirstError(errors, {
                                    user_id: 'sale-student',
                                    plan_id: `sale-plan-${plans[0].id}`,
                                    price: 'sale-price',
                                    price_reason: 'sale-price-reason',
                                    receipt: 'sale-receipt',
                                }),
                            );
                        }}
                        className="flex flex-1 flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Steps
                                    current={step}
                                    reachable={reachable}
                                    onSelect={setStep}
                                />

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value={student?.id ?? ''}
                                />
                                <input
                                    type="hidden"
                                    name="method"
                                    value={method}
                                />

                                <section
                                    hidden={step !== 1}
                                    className={cn(panel, 'grid gap-4')}
                                >
                                    {heading(
                                        1,
                                        '¿A quién le vendes?',
                                        'Búscala por nombre, celular o correo.',
                                    )}
                                    <StudentPicker
                                        slug={studio.slug}
                                        student={student}
                                        onChange={(next) => {
                                            setStudent(next);

                                            if (next) {
                                                setStep(2);
                                            }
                                        }}
                                        error={errors.user_id}
                                    />
                                </section>

                                <section
                                    hidden={step !== 2}
                                    className={cn(panel, 'grid gap-4')}
                                >
                                    {heading(
                                        2,
                                        firstName
                                            ? `¿Qué plan compra ${firstName}?`
                                            : '¿Qué plan compra?',
                                        'Los planes a la venta, a su precio de lista.',
                                    )}
                                    {student && (
                                        <div className="flex items-start justify-between gap-3 rounded-xl border bg-muted/40 p-4">
                                            <span className="flex min-w-0 items-start gap-3">
                                                <InitialsAvatar
                                                    name={student.name}
                                                />
                                                <span className="grid min-w-0 gap-0.5">
                                                    <span className="truncate font-medium">
                                                        {student.name}
                                                    </span>
                                                    {student.plans.length ===
                                                    0 ? (
                                                        <span className="text-sm text-muted-foreground">
                                                            No tiene un plan
                                                            vigente.
                                                        </span>
                                                    ) : (
                                                        <ul className="grid gap-0.5 text-sm text-muted-foreground">
                                                            {student.plans.map(
                                                                (current) => (
                                                                    <li
                                                                        key={
                                                                            current.name +
                                                                            current.lastDay
                                                                        }
                                                                    >
                                                                        {currentPlanLabel(
                                                                            current,
                                                                        )}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    )}
                                                </span>
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setStep(1)}
                                            >
                                                Cambiar
                                            </Button>
                                        </div>
                                    )}
                                    <RadioGroup
                                        name="plan_id"
                                        value={planId}
                                        onValueChange={(value) => {
                                            setPlanId(value);
                                            setStep(3);
                                        }}
                                        aria-label="Plan"
                                        className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                    >
                                        {plans.map((item) => {
                                            const id = `sale-plan-${item.id}`;
                                            const selected =
                                                String(item.id) === planId;

                                            return (
                                                <Label
                                                    key={item.id}
                                                    htmlFor={id}
                                                    className={cn(
                                                        'flex cursor-pointer items-start gap-3 rounded-xl border p-4 font-normal transition-colors hover:bg-muted/50',
                                                        selected &&
                                                            'border-foreground bg-muted/40',
                                                    )}
                                                >
                                                    <RadioGroupItem
                                                        id={id}
                                                        value={String(item.id)}
                                                        className="mt-0.5"
                                                    />
                                                    <span className="grid min-w-0 flex-1 gap-0.5">
                                                        <span className="font-medium break-words">
                                                            {item.name}
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {classesLabel(
                                                                item.credits,
                                                            )}{' '}
                                                            ·{' '}
                                                            {item.validityDays}{' '}
                                                            días
                                                        </span>
                                                    </span>
                                                    <span className="font-medium tabular-nums">
                                                        {formatClp(item.price)}
                                                    </span>
                                                </Label>
                                            );
                                        })}
                                    </RadioGroup>
                                    <InputError message={errors.plan_id} />
                                </section>

                                <section
                                    hidden={step !== 3}
                                    className={cn(
                                        panel,
                                        'grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]',
                                    )}
                                >
                                    <div className="grid gap-6">
                                        {heading(
                                            3,
                                            '¿Cómo paga?',
                                            'Cobra antes de vender: la venta entra en tu caja.',
                                        )}
                                        <div className="grid gap-3">
                                            <ToggleGroup
                                                type="single"
                                                variant="outline"
                                                value={method}
                                                onValueChange={(next) => {
                                                    if (next) {
                                                        setMethod(next);
                                                    }
                                                }}
                                                aria-label="Medio de pago"
                                                className="justify-self-start"
                                            >
                                                {methods.map((option) => (
                                                    <ToggleGroupItem
                                                        key={option.value}
                                                        value={option.value}
                                                        className="px-3"
                                                    >
                                                        {option.label}
                                                    </ToggleGroupItem>
                                                ))}
                                            </ToggleGroup>
                                            <p className="text-sm text-muted-foreground">
                                                {METHOD_HELP[method]}
                                            </p>
                                        </div>

                                        {method === 'transfer' && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="sale-receipt">
                                                    Comprobante (opcional)
                                                </Label>
                                                <Input
                                                    id="sale-receipt"
                                                    {...fieldErrorProps(
                                                        'sale-receipt',
                                                        errors.receipt,
                                                    )}
                                                    name="receipt"
                                                    type="file"
                                                    accept="image/jpeg,image/png,application/pdf"
                                                />
                                                <InputError
                                                    id="sale-receipt-error"
                                                    message={errors.receipt}
                                                />
                                            </div>
                                        )}

                                        {canChangePrice && (
                                            <div className="grid gap-4">
                                                <div className="flex items-center gap-3">
                                                    <Checkbox
                                                        id="sale-changes-price"
                                                        checked={changesPrice}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) => {
                                                            setChangesPrice(
                                                                checked ===
                                                                    true,
                                                            );
                                                            setPrice(
                                                                plan
                                                                    ? String(
                                                                          plan.price,
                                                                      )
                                                                    : '',
                                                            );
                                                        }}
                                                    />
                                                    <Label htmlFor="sale-changes-price">
                                                        Cambiar el precio o
                                                        regalar el plan
                                                    </Label>
                                                </div>
                                                {changesPrice && (
                                                    <div className="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                                                        <div className="grid content-start gap-2">
                                                            <Label htmlFor="sale-price">
                                                                Precio (pesos)
                                                            </Label>
                                                            <Input
                                                                id="sale-price"
                                                                {...fieldErrorProps(
                                                                    'sale-price',
                                                                    errors.price,
                                                                )}
                                                                name="price"
                                                                type="number"
                                                                inputMode="numeric"
                                                                min={0}
                                                                value={price}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setPrice(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                required
                                                            />
                                                            <InputError
                                                                id="sale-price-error"
                                                                message={
                                                                    errors.price
                                                                }
                                                            />
                                                        </div>
                                                        <div className="grid content-start gap-2">
                                                            <Label htmlFor="sale-price-reason">
                                                                Por qué
                                                            </Label>
                                                            <Input
                                                                id="sale-price-reason"
                                                                {...fieldErrorProps(
                                                                    'sale-price-reason',
                                                                    errors.price_reason,
                                                                )}
                                                                name="price_reason"
                                                                maxLength={200}
                                                                required
                                                                placeholder="Descuento por traer a una amiga"
                                                            />
                                                            <InputError
                                                                id="sale-price-reason-error"
                                                                message={
                                                                    errors.price_reason
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    <Card className="bg-muted/40 shadow-none lg:sticky lg:top-4">
                                        <CardHeader>
                                            <CardTitle>
                                                <h3>Resumen</h3>
                                            </CardTitle>
                                            <CardDescription>
                                                {student?.name}
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="grid gap-4">
                                            {plan && (
                                                <div className="grid gap-1">
                                                    <p className="font-medium break-words">
                                                        {plan.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {classesLabel(
                                                            plan.credits,
                                                        )}
                                                        {plan.dailyLimit !==
                                                            null &&
                                                            `, hasta ${plan.dailyLimit} al día`}
                                                    </p>
                                                    <p className="text-sm first-letter:uppercase">
                                                        Vale hasta el{' '}
                                                        {plan.lastDay}
                                                    </p>
                                                </div>
                                            )}
                                            <Separator />
                                            <div className="flex items-baseline justify-between gap-4">
                                                <span className="text-sm text-muted-foreground">
                                                    Total · {methodLabel}
                                                </span>
                                                <span className="text-2xl font-semibold tabular-nums">
                                                    {formatClp(total)}
                                                </span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </section>

                                {/* The way forward, always at hand. */}
                                <div className="sticky bottom-0 z-10 -mx-4 mt-auto flex items-center justify-between gap-3 border-t bg-background px-4 py-3">
                                    <p className="flex min-w-0 items-center gap-2 text-sm">
                                        <Badge variant="secondary">
                                            Paso {step} de 3
                                        </Badge>
                                        <span className="hidden truncate text-muted-foreground sm:inline">
                                            {STEPS[step - 1].help}
                                        </span>
                                    </p>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {register.state !== 'open' && (
                                            <span className="hidden text-sm text-muted-foreground md:inline">
                                                Abre tu caja para vender
                                            </span>
                                        )}
                                        {step > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() =>
                                                    setStep((step - 1) as Step)
                                                }
                                            >
                                                Atrás
                                            </Button>
                                        )}
                                        {step < 3 ? (
                                            <Button
                                                type="button"
                                                disabled={!canContinue}
                                                onClick={() =>
                                                    setStep((step + 1) as Step)
                                                }
                                            >
                                                Continuar
                                                <ArrowRight aria-hidden="true" />
                                            </Button>
                                        ) : (
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    !student ||
                                                    !plan ||
                                                    register.state !== 'open'
                                                }
                                            >
                                                {processing && <Spinner />}
                                                Vender {formatClp(total)}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

SellPlan.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Vender plan', href: sellCreate(props.studio.slug) }]
            : []),
    ],
});
