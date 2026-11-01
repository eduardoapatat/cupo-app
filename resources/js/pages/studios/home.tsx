import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { create as buyPlan } from '@/actions/App/Http/Controllers/Studio/BuyPlanController';
import { index as myPlanIndex } from '@/actions/App/Http/Controllers/Studio/MyPlanController';
import { index as mySessionsIndex } from '@/actions/App/Http/Controllers/Studio/MySessionsController';
import Heading from '@/components/heading';
import Arrivals from '@/components/home/arrivals';
import CashRegisterSummary from '@/components/home/cash-register-summary';
import NextBooking from '@/components/home/next-booking';
import OwnerAlerts from '@/components/home/owner-alerts';
import ReceiptsToReview from '@/components/home/receipts-to-review';
import TodaySessions, { occupancy } from '@/components/home/today-sessions';
import type {
    Arrival,
    CashRegisterSummary as RegisterSummary,
    HomeSession,
    NextBookingSummary,
    OwnerAlerts as Alerts,
    ReceiptsToReviewSummary,
} from '@/components/home/types';
import TeachingSessionCard from '@/components/teaching-session-card';
import type { TeachingSession } from '@/components/teaching-session-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { PlanSummary } from '@/types';

type BlockKey =
    | 'today-occupancy'
    | 'alerts'
    | 'month-income'
    | 'today-sessions'
    | 'arrivals'
    | 'receipts-to-review'
    | 'cash-register'
    | 'teaching-today'
    | 'next-booking'
    | 'classes-left'
    | 'plan-expiry';

/** The role's blocks, in order (StudioRole::homeBlocks()). */
type Block = { key: BlockKey; isBuilt: boolean };

/** Each prop arrives only when one of the role's blocks needs it. */
type Props = {
    blocks: Block[];
    todaySessions: HomeSession[] | null;
    arrivals: Arrival | null;
    teachingToday: (TeachingSession & HomeSession)[] | null;
    nextBooking: NextBookingSummary | null;
    waitingCount: number | null;
    planSummary: PlanSummary | null;
    receiptsToReview: ReceiptsToReviewSummary | null;
    cashRegister: RegisterSummary | null;
    alerts: Alerts | null;
};

/** For a student without a valid plan, where to see hers. */
function NoPlan({ slug }: { slug: string }) {
    return (
        <p className="text-sm text-muted-foreground">
            No tienes un plan vigente.{' '}
            <Link
                href={myPlanIndex(slug)}
                className="text-foreground underline underline-offset-4"
            >
                Ver cómo comprarlo
            </Link>
        </p>
    );
}

/*
 * What each block is about (docs/roles-y-permisos.md). The month's income
 * shows as «Pronto» until the reports of checkpoint 3.3.
 */
const BLOCK_TEXT: Record<BlockKey, { title: string; description: string }> = {
    'today-occupancy': {
        title: 'Ocupación de hoy',
        description: 'Cuántos cupos se llenaron en cada clase del día.',
    },
    alerts: {
        title: 'Alertas',
        description:
            'Comprobantes pendientes, cajas con diferencia y anulaciones del día.',
    },
    'month-income': {
        title: 'Ingresos del mes',
        description: 'Lo que entró este mes, por medio de pago.',
    },
    'today-sessions': {
        title: 'Clases de hoy',
        description: 'Las clases del día, con sus cupos.',
    },
    arrivals: {
        title: 'Alumnas que llegan',
        description: 'Quiénes vienen a la clase en curso o a la próxima.',
    },
    'receipts-to-review': {
        title: 'Comprobantes por validar',
        description: 'Las transferencias que esperan tu revisión.',
    },
    'cash-register': {
        title: 'Tu caja',
        description: 'Si está abierta y cuánto llevas cobrado, por medio.',
    },
    'teaching-today': {
        title: 'Tus clases de hoy',
        description: 'Quiénes vienen y, desde que empieza, su asistencia.',
    },
    'next-booking': {
        title: 'Tu próxima clase',
        description: 'Cuándo es y hasta qué hora puedes cancelarla gratis.',
    },
    'classes-left': {
        title: 'Clases que te quedan',
        description: 'Las clases disponibles en tu plan.',
    },
    'plan-expiry': {
        title: 'Vencimiento de tu plan',
        description: 'Hasta cuándo puedes usar tus clases.',
    },
};

function spotsLabel(sessions: HomeSession[]): string {
    const { booked, capacity } = occupancy(sessions);

    if (capacity === 0) {
        return BLOCK_TEXT['today-occupancy'].description;
    }

    return `${booked} de ${capacity} cupos (${Math.round((booked / capacity) * 100)} %).`;
}

export default function StudioHome(props: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    const slug = studio.slug;

    /** The content of a block that already shows real data. */
    function content(key: BlockKey): ReactNode {
        switch (key) {
            case 'today-occupancy':
            case 'today-sessions':
                return (
                    <TodaySessions
                        slug={slug}
                        sessions={props.todaySessions ?? []}
                    />
                );
            case 'arrivals':
                return <Arrivals slug={slug} arrival={props.arrivals} />;
            case 'alerts':
                return props.alerts ? (
                    <OwnerAlerts slug={slug} alerts={props.alerts} />
                ) : null;
            case 'receipts-to-review':
                return props.receiptsToReview ? (
                    <ReceiptsToReview
                        slug={slug}
                        receipts={props.receiptsToReview}
                    />
                ) : null;
            case 'cash-register':
                return props.cashRegister ? (
                    <CashRegisterSummary
                        slug={slug}
                        register={props.cashRegister}
                    />
                ) : null;
            case 'teaching-today':
                return props.teachingToday?.length ? (
                    <ul className="grid gap-3">
                        {props.teachingToday.map((session) => (
                            <TeachingSessionCard
                                key={session.id}
                                slug={slug}
                                session={session}
                            />
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Hoy no tienes clases.{' '}
                        <Link
                            href={mySessionsIndex(slug)}
                            className="text-foreground underline underline-offset-4"
                        >
                            Ver mis clases
                        </Link>
                    </p>
                );
            case 'next-booking':
                return (
                    <NextBooking
                        slug={slug}
                        booking={props.nextBooking}
                        waitingCount={props.waitingCount ?? 0}
                    />
                );
            case 'classes-left':
                return props.planSummary === null ? (
                    <NoPlan slug={slug} />
                ) : (
                    <p className="text-4xl font-semibold tabular-nums">
                        {props.planSummary.isUnlimited
                            ? 'Ilimitadas'
                            : props.planSummary.classesLeft}
                    </p>
                );
            case 'plan-expiry':
                return props.planSummary === null ? (
                    <NoPlan slug={slug} />
                ) : (
                    <div className="grid gap-1">
                        <p className="text-lg font-semibold first-letter:uppercase">
                            {props.planSummary.lastDay}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {props.planSummary.daysLeft === 1
                                ? 'Hoy es su último día.'
                                : `Quedan ${props.planSummary.daysLeft} días.`}
                            {props.planSummary.expiresSoon && (
                                <>
                                    {' '}
                                    <Link
                                        href={buyPlan(slug)}
                                        className="text-foreground underline underline-offset-4"
                                    >
                                        Renovar
                                    </Link>
                                </>
                            )}
                        </p>
                    </div>
                );
            default:
                return null;
        }
    }

    function description(key: BlockKey): string {
        if (key === 'today-occupancy' && props.todaySessions) {
            return spotsLabel(props.todaySessions);
        }

        if (key === 'plan-expiry' && props.planSummary?.expiresSoon) {
            return 'Vence pronto: renuévalo para seguir reservando.';
        }

        return BLOCK_TEXT[key].description;
    }

    const built = props.blocks.filter((block) => block.isBuilt);
    const pending = props.blocks.filter((block) => !block.isBuilt);

    return (
        <>
            <Head title="Inicio" />

            <div className="flex flex-col gap-6 px-4 py-6">
                <Heading className="mb-0" title="Inicio" />

                {/* What works today takes the room; what arrives later, a narrow column. */}
                <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <div
                        className={cn(
                            'grid items-start gap-4',
                            built.length > 1 && 'lg:grid-cols-2',
                        )}
                    >
                        {built.map((block) => (
                            <Card key={block.key}>
                                <CardHeader>
                                    <CardTitle>
                                        <h2>{BLOCK_TEXT[block.key].title}</h2>
                                    </CardTitle>
                                    <CardDescription>
                                        {description(block.key)}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>{content(block.key)}</CardContent>
                            </Card>
                        ))}
                    </div>

                    {pending.length > 0 && (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            {pending.map((block) => (
                                <Card key={block.key}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-2">
                                            <CardTitle>
                                                <h2>
                                                    {
                                                        BLOCK_TEXT[block.key]
                                                            .title
                                                    }
                                                </h2>
                                            </CardTitle>
                                            <Badge variant="outline">
                                                Pronto
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            {BLOCK_TEXT[block.key].description}{' '}
                                            Llega en la Fase 3.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

StudioHome.layout = {
    breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
};
