import { Form, Head, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    create as sellCreate,
    store as sellStore,
    students as sellStudents,
} from '@/actions/App/Http/Controllers/Studio/SellPlanController';
import Heading from '@/components/heading';
import InitialsAvatar from '@/components/initials-avatar';
import InputError from '@/components/input-error';
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
                                    {student.phone}
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

export default function SellPlan({
    plans,
    student: initialStudent,
    methods,
    canChangePrice,
}: Props) {
    const { studio } = usePage().props;
    const [student, setStudent] = useState(initialStudent);
    const [planId, setPlanId] = useState<string>(
        plans.length === 1 ? String(plans[0].id) : '',
    );
    const [method, setMethod] = useState('cash');
    const [changesPrice, setChangesPrice] = useState(false);
    const [price, setPrice] = useState('');

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

    return (
        <>
            <Head title="Vender plan" />

            <div className="flex flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Vender plan"
                    description="Elige a la alumna, el plan y cómo paga. Las clases quedan disponibles al tiro."
                />

                {plans.length === 0 ? (
                    <p className="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                        Todavía no hay planes a la venta. La dueña los crea en
                        «Planes».
                    </p>
                ) : (
                    <Form
                        {...sellStore.form(studio.slug)}
                        onError={(errors) =>
                            focusFirstError(errors, {
                                user_id: 'sale-student',
                                price: 'sale-price',
                                price_reason: 'sale-price-reason',
                            })
                        }
                        className="grid grid-cols-[minmax(0,1fr)] items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-8">
                                    <section
                                        aria-labelledby="sale-student-title"
                                        className="grid gap-3"
                                    >
                                        <h2
                                            id="sale-student-title"
                                            className="text-sm font-medium"
                                        >
                                            Alumna
                                        </h2>
                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value={student?.id ?? ''}
                                        />
                                        <StudentPicker
                                            slug={studio.slug}
                                            student={student}
                                            onChange={setStudent}
                                            error={errors.user_id}
                                        />
                                    </section>

                                    <section
                                        aria-labelledby="sale-plan-title"
                                        className="grid gap-3"
                                    >
                                        <h2
                                            id="sale-plan-title"
                                            className="text-sm font-medium"
                                        >
                                            Plan
                                        </h2>
                                        <RadioGroup
                                            name="plan_id"
                                            value={planId}
                                            onValueChange={setPlanId}
                                            aria-labelledby="sale-plan-title"
                                            className="grid gap-3 sm:grid-cols-2"
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
                                                            value={String(
                                                                item.id,
                                                            )}
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
                                                                {
                                                                    item.validityDays
                                                                }{' '}
                                                                días
                                                            </span>
                                                        </span>
                                                        <span className="font-medium tabular-nums">
                                                            {formatClp(
                                                                item.price,
                                                            )}
                                                        </span>
                                                    </Label>
                                                );
                                            })}
                                        </RadioGroup>
                                        <InputError message={errors.plan_id} />
                                    </section>

                                    <section
                                        aria-labelledby="sale-method-title"
                                        className="grid gap-3"
                                    >
                                        <h2
                                            id="sale-method-title"
                                            className="text-sm font-medium"
                                        >
                                            Cómo paga
                                        </h2>
                                        <input
                                            type="hidden"
                                            name="method"
                                            value={method}
                                        />
                                        <ToggleGroup
                                            type="single"
                                            variant="outline"
                                            value={method}
                                            onValueChange={(next) => {
                                                if (next) {
                                                    setMethod(next);
                                                }
                                            }}
                                            aria-labelledby="sale-method-title"
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
                                    </section>

                                    {canChangePrice && (
                                        <section
                                            aria-label="Precio"
                                            className="grid gap-4"
                                        >
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    id="sale-changes-price"
                                                    checked={changesPrice}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        setChangesPrice(
                                                            checked === true,
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
                                                    Cambiar el precio o regalar
                                                    el plan
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
                                                            onChange={(event) =>
                                                                setPrice(
                                                                    event.target
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
                                        </section>
                                    )}
                                </div>

                                <Card className="lg:sticky lg:top-4">
                                    <CardHeader>
                                        <CardTitle>
                                            <h2>Resumen</h2>
                                        </CardTitle>
                                        <CardDescription>
                                            {student
                                                ? student.name
                                                : 'Elige a la alumna.'}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        {plan ? (
                                            <div className="grid gap-1">
                                                <p className="font-medium break-words">
                                                    {plan.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {classesLabel(plan.credits)}
                                                    {plan.dailyLimit !== null &&
                                                        `, hasta ${plan.dailyLimit} al día`}
                                                </p>
                                                <p className="text-sm first-letter:uppercase">
                                                    Vale hasta el {plan.lastDay}
                                                </p>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Elige el plan.
                                            </p>
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
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={
                                                processing || !student || !plan
                                            }
                                        >
                                            {processing && <Spinner />}
                                            Vender
                                        </Button>
                                    </CardContent>
                                </Card>
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
