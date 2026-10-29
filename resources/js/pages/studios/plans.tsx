import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    destroy as restorePlan,
    store as archivePlan,
} from '@/actions/App/Http/Controllers/Studio/PlanArchiveController';
import {
    index as plansIndex,
    show as planShow,
    store as storePlan,
    update as updatePlan,
} from '@/actions/App/Http/Controllers/Studio/PlanController';
import ConfirmDialog from '@/components/confirm-dialog';
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
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { formatClp } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type Plan = {
    id: number;
    name: string;
    price: number;
    /** Null for an unlimited plan. */
    credits: number | null;
    validityDays: number;
    /** Null when it allows any number of classes a day. */
    dailyLimit: number | null;
    isArchived: boolean;
    /** Students who have it valid now. */
    validCount: number;
    soldThisMonth: number;
};

type Props = {
    plans: Plan[];
    plan: Plan | null;
    /** What a new unlimited plan allows a day (config/studio.php). */
    defaultDailyLimit: number;
};

type Errors = Partial<Record<string, string>>;

/** The form's values, as typed, so the preview follows them. */
type Draft = {
    name: string;
    price: string;
    unlimited: boolean;
    credits: string;
    validityDays: string;
    dailyLimit: string;
};

function classesLabel(plan: Pick<Plan, 'credits'>): string {
    if (plan.credits === null) {
        return 'Clases ilimitadas';
    }

    return plan.credits === 1 ? '1 clase' : `${plan.credits} clases`;
}

function daysLabel(days: number): string {
    return days === 1 ? '1 día' : `${days} días`;
}

function dailyLimitLabel(limit: number | null): string {
    if (limit === null) {
        return 'Sin máximo diario';
    }

    return limit === 1
        ? 'Hasta 1 clase al día'
        : `Hasta ${limit} clases al día`;
}

/** A new plan starts as a pack of 8 classes for 30 days, with no daily limit. */
function draftOf(plan: Plan | null): Draft {
    return {
        name: plan?.name ?? '',
        price: plan ? String(plan.price) : '',
        unlimited: plan ? plan.credits === null : false,
        credits: plan?.credits ? String(plan.credits) : '8',
        validityDays: String(plan?.validityDays ?? 30),
        dailyLimit: plan?.dailyLimit ? String(plan.dailyLimit) : '',
    };
}

/** The plan as the draft describes it, for the preview. */
function planOf(
    draft: Draft,
): Omit<Plan, 'id' | 'isArchived' | 'validCount' | 'soldThisMonth'> {
    const number = (value: string) => {
        const parsed = Number.parseInt(value, 10);

        return Number.isNaN(parsed) ? 0 : parsed;
    };

    return {
        name: draft.name.trim() || 'Nuevo plan',
        price: number(draft.price),
        credits: draft.unlimited ? null : number(draft.credits),
        validityDays: number(draft.validityDays),
        dailyLimit: draft.dailyLimit === '' ? null : number(draft.dailyLimit),
    };
}

/** How the plan reads for a student, while the owner types it. */
function PlanPreview({ draft }: { draft: Draft }) {
    const plan = planOf(draft);

    return (
        <aside
            aria-label="Así lo ve la alumna"
            className="grid content-start gap-4 rounded-xl border bg-muted/40 p-4"
        >
            <p className="text-xs font-medium text-muted-foreground">
                Así lo ve la alumna
            </p>
            <div className="grid gap-1">
                <p className="text-lg font-semibold break-words">{plan.name}</p>
                <p className="text-2xl font-semibold tabular-nums">
                    {formatClp(plan.price)}
                </p>
            </div>
            <ul className="grid gap-1 text-sm">
                <li>{classesLabel(plan)}</li>
                <li>Vale {daysLabel(plan.validityDays)} desde la compra</li>
                <li className="text-muted-foreground">
                    {dailyLimitLabel(plan.dailyLimit)}
                </li>
            </ul>
        </aside>
    );
}

function PlanFields({
    draft,
    setDraft,
    errors,
    defaultDailyLimit,
}: {
    draft: Draft;
    setDraft: (draft: Draft) => void;
    errors: Errors;
    defaultDailyLimit: number;
}) {
    const update = (changes: Partial<Draft>) =>
        setDraft({ ...draft, ...changes });

    return (
        <div className="grid content-start gap-5">
            <div className="grid gap-2">
                <Label htmlFor="plan-name">Nombre</Label>
                <Input
                    id="plan-name"
                    {...fieldErrorProps('plan-name', errors.name)}
                    name="name"
                    value={draft.name}
                    onChange={(event) => update({ name: event.target.value })}
                    required
                    maxLength={60}
                    placeholder="8 clases al mes"
                />
                <InputError id="plan-name-error" message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="plan-price">Precio (pesos)</Label>
                <Input
                    id="plan-price"
                    {...fieldErrorProps('plan-price', errors.price)}
                    name="price"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={10000000}
                    step={1}
                    value={draft.price}
                    onChange={(event) => update({ price: event.target.value })}
                    required
                    placeholder="45000"
                />
                <InputError id="plan-price-error" message={errors.price} />
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">Clases</legend>
                <input
                    type="hidden"
                    name="unlimited"
                    value={draft.unlimited ? '1' : '0'}
                />
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={draft.unlimited ? 'unlimited' : 'pack'}
                    onValueChange={(value) => {
                        if (value === 'pack') {
                            update({ unlimited: false, dailyLimit: '' });
                        } else if (value === 'unlimited') {
                            update({
                                unlimited: true,
                                dailyLimit: String(defaultDailyLimit),
                            });
                        }
                    }}
                    aria-label="Tipo de plan"
                    className="justify-self-start"
                >
                    <ToggleGroupItem value="pack" className="px-3">
                        Un número de clases
                    </ToggleGroupItem>
                    <ToggleGroupItem value="unlimited" className="px-3">
                        Ilimitado
                    </ToggleGroupItem>
                </ToggleGroup>
                {!draft.unlimited && (
                    <div className="grid gap-2 pt-2">
                        <Label htmlFor="plan-credits">Cuántas clases</Label>
                        <Input
                            id="plan-credits"
                            {...fieldErrorProps('plan-credits', errors.credits)}
                            name="credits"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={200}
                            value={draft.credits}
                            onChange={(event) =>
                                update({ credits: event.target.value })
                            }
                            required
                        />
                        <InputError
                            id="plan-credits-error"
                            message={errors.credits}
                        />
                    </div>
                )}
            </fieldset>

            <div className="grid gap-6 sm:grid-cols-2">
                <div className="grid content-start gap-2">
                    <Label htmlFor="plan-validity">Vigencia (días)</Label>
                    <Input
                        id="plan-validity"
                        {...fieldErrorProps(
                            'plan-validity',
                            errors.validity_days,
                            'plan-validity-help',
                        )}
                        name="validity_days"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={365}
                        value={draft.validityDays}
                        onChange={(event) =>
                            update({ validityDays: event.target.value })
                        }
                        required
                    />
                    <p
                        id="plan-validity-help"
                        className="text-sm text-muted-foreground"
                    >
                        Contados desde el día de la compra.
                    </p>
                    <InputError
                        id="plan-validity-error"
                        message={errors.validity_days}
                    />
                </div>
                <div className="grid content-start gap-2">
                    <Label htmlFor="plan-daily-limit">Máximo al día</Label>
                    <Input
                        id="plan-daily-limit"
                        {...fieldErrorProps(
                            'plan-daily-limit',
                            errors.daily_limit,
                            'plan-daily-limit-help',
                        )}
                        name="daily_limit"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={10}
                        value={draft.dailyLimit}
                        onChange={(event) =>
                            update({ dailyLimit: event.target.value })
                        }
                        placeholder="Sin máximo"
                    />
                    <p
                        id="plan-daily-limit-help"
                        className="text-sm text-muted-foreground"
                    >
                        Vacío, sin máximo.
                    </p>
                    <InputError
                        id="plan-daily-limit-error"
                        message={errors.daily_limit}
                    />
                </div>
            </div>
        </div>
    );
}

/** Create a plan, or edit one, with the preview beside the form. */
function PlanDialog({
    slug,
    plan,
    defaultDailyLimit,
}: {
    slug: string;
    plan: Plan | null;
    defaultDailyLimit: number;
}) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(() => draftOf(plan));

    // Opening it again starts from what is saved, not from an unsaved change.
    function onOpenChange(next: boolean) {
        if (next) {
            setDraft(draftOf(plan));
        }

        setOpen(next);
    }

    const action = plan
        ? updatePlan.form([slug, plan.id])
        : storePlan.form(slug);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                {plan ? (
                    <Button variant="outline">Editar</Button>
                ) : (
                    <Button>
                        <Plus />
                        Nuevo plan
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogTitle>
                    {plan ? `Editar ${plan.name}` : 'Nuevo plan'}
                </DialogTitle>
                <DialogDescription>
                    {plan
                        ? 'Los planes que ya se vendieron quedan como estaban; el cambio rige para las próximas ventas.'
                        : 'Recepción lo vende a este precio en el mesón.'}
                </DialogDescription>
                <Form
                    {...action}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) =>
                        focusFirstError(errors, {
                            name: 'plan-name',
                            price: 'plan-price',
                            credits: 'plan-credits',
                            validity_days: 'plan-validity',
                            daily_limit: 'plan-daily-limit',
                        })
                    }
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 sm:grid-cols-[minmax(0,1fr)_16rem]">
                                <PlanFields
                                    draft={draft}
                                    setDraft={setDraft}
                                    errors={errors}
                                    defaultDailyLimit={defaultDailyLimit}
                                />
                                <PlanPreview draft={draft} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {plan ? 'Guardar' : 'Agregar plan'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PlanRow({
    slug,
    plan,
    selected,
}: {
    slug: string;
    plan: Plan;
    selected: boolean;
}) {
    return (
        <li>
            <Link
                href={planShow([slug, plan.id])}
                preserveScroll
                aria-current={selected ? 'true' : undefined}
                className={cn(
                    'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 border-l-2 border-transparent px-4 py-3 transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset',
                    selected && 'border-foreground bg-muted/60',
                    plan.isArchived && 'text-muted-foreground',
                )}
            >
                <span className="grid min-w-0 gap-0.5">
                    <span className="flex min-w-0 items-center gap-2">
                        <span className="truncate font-medium">
                            {plan.name}
                        </span>
                        {plan.isArchived && (
                            <Badge variant="outline">Archivado</Badge>
                        )}
                    </span>
                    <span className="truncate text-sm text-muted-foreground">
                        {classesLabel(plan)} · {daysLabel(plan.validityDays)}
                        {plan.validCount > 0 &&
                            ` · ${plan.validCount === 1 ? '1 vigente' : `${plan.validCount} vigentes`}`}
                    </span>
                </span>
                <span className="font-medium tabular-nums">
                    {formatClp(plan.price)}
                </span>
            </Link>
        </li>
    );
}

function PlanDetail({
    slug,
    plan,
    defaultDailyLimit,
}: {
    slug: string;
    plan: Plan;
    defaultDailyLimit: number;
}) {
    return (
        <Card className="lg:sticky lg:top-4">
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <CardTitle>
                        <h2 className="break-words">{plan.name}</h2>
                    </CardTitle>
                    {plan.isArchived && (
                        <Badge variant="outline">Archivado</Badge>
                    )}
                </div>
                <CardDescription>
                    {plan.isArchived
                        ? 'Ya no se vende. Quien lo compró lo sigue usando.'
                        : 'Se vende en el mesón a este precio.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6">
                <p className="text-3xl font-semibold tabular-nums">
                    {formatClp(plan.price)}
                </p>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1 rounded-lg border p-3">
                        <span className="text-xs text-muted-foreground">
                            Vigentes hoy
                        </span>
                        <span className="text-xl font-semibold tabular-nums">
                            {plan.validCount}
                        </span>
                    </div>
                    <div className="grid gap-1 rounded-lg border p-3">
                        <span className="text-xs text-muted-foreground">
                            Vendidos este mes
                        </span>
                        <span className="text-xl font-semibold tabular-nums">
                            {plan.soldThisMonth}
                        </span>
                    </div>
                </div>
                <dl className="grid gap-3 text-sm">
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">Clases</dt>
                        <dd className="font-medium">{classesLabel(plan)}</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">Vigencia</dt>
                        <dd className="font-medium">
                            {daysLabel(plan.validityDays)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">Máximo al día</dt>
                        <dd className="font-medium">
                            {plan.dailyLimit === null
                                ? 'Sin máximo'
                                : plan.dailyLimit}
                        </dd>
                    </div>
                </dl>
                <div className="flex flex-wrap gap-2">
                    {plan.isArchived ? (
                        <Form
                            {...restorePlan.form([slug, plan.id])}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <div className="grid gap-2">
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Volver a venderlo
                                    </Button>
                                    <InputError message={errors.name} />
                                </div>
                            )}
                        </Form>
                    ) : (
                        <>
                            <PlanDialog
                                key={plan.id}
                                slug={slug}
                                plan={plan}
                                defaultDailyLimit={defaultDailyLimit}
                            />
                            <ConfirmDialog
                                trigger={
                                    <Button variant="ghost">Archivar</Button>
                                }
                                title={`¿Archivar ${plan.name}?`}
                                description="Deja de venderse. Quien ya lo compró lo sigue usando hasta que venza."
                                action={archivePlan.form([slug, plan.id])}
                                confirmLabel="Archivar"
                                dismissLabel="Volver"
                            />
                        </>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Plans({ plans, plan, defaultDailyLimit }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    const slug = studio.slug;
    const onSale = plans.filter((item) => !item.isArchived).length;

    return (
        <>
            <Head title={plan ? `${plan.name} · Planes` : 'Planes'} />

            <div className="flex flex-col gap-6 px-4 py-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div className="grid gap-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-tight">
                                Planes
                            </h1>
                            <Badge variant="secondary" className="tabular-nums">
                                {onSale === 1
                                    ? '1 a la venta'
                                    : `${onSale} a la venta`}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Lo que vende el estudio: el precio, las clases y
                            cuánto dura cada plan.
                        </p>
                    </div>
                    <PlanDialog
                        slug={slug}
                        plan={null}
                        defaultDailyLimit={defaultDailyLimit}
                    />
                </header>

                <div className="grid grid-cols-[minmax(0,1fr)] items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    {/* On a phone, one at a time: the list or the chosen plan. */}
                    <section
                        aria-label="Planes del estudio"
                        className={cn(
                            'overflow-hidden rounded-xl border',
                            plan && 'hidden lg:block',
                        )}
                    >
                        {plans.length === 0 ? (
                            <p className="px-4 py-10 text-center text-sm text-muted-foreground">
                                Todavía no hay planes. Crea el primero, por
                                ejemplo «8 clases al mes».
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {plans.map((item) => (
                                    <PlanRow
                                        key={item.id}
                                        slug={slug}
                                        plan={item}
                                        selected={item.id === plan?.id}
                                    />
                                ))}
                            </ul>
                        )}
                    </section>

                    <div
                        className={cn('grid gap-3', !plan && 'hidden lg:grid')}
                    >
                        {plan ? (
                            <>
                                <Link
                                    href={plansIndex(slug)}
                                    className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground lg:hidden"
                                >
                                    <ArrowLeft className="size-4" />
                                    Planes
                                </Link>
                                <PlanDetail
                                    slug={slug}
                                    plan={plan}
                                    defaultDailyLimit={defaultDailyLimit}
                                />
                            </>
                        ) : (
                            <p className="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                                Elige un plan para ver su detalle.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Plans.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Planes', href: plansIndex(props.studio.slug) }]
            : []),
        ...(props.studio && props.plan
            ? [
                  {
                      title: props.plan.name,
                      href: planShow([props.studio.slug, props.plan.id]),
                  },
              ]
            : []),
    ],
});
