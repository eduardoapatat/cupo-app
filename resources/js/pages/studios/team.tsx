import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Mail, MoreHorizontal, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Studio/TeamController';
import {
    destroy as cancelInvitation,
    resend as resendInvitation,
    store as storeInvitation,
} from '@/actions/App/Http/Controllers/Studio/TeamInvitationController';
import { destroy as removeMember } from '@/actions/App/Http/Controllers/Studio/TeamMemberController';
import ConfirmDialog from '@/components/confirm-dialog';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
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
import type { CurrentStudio, Option } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string | null;
    role: string;
    removable: boolean;
};

type PendingInvitation = {
    id: number;
    email: string;
    role: string;
    expiresOn: string;
    expired: boolean;
};

type Props = {
    members: Member[];
    invitations: PendingInvitation[];
    roles: Option[];
};

function InviteCard({ slug, roles }: { slug: string; roles: Option[] }) {
    return (
        <Form
            {...storeInvitation.form(slug)}
            resetOnSuccess
            options={{ preserveScroll: true }}
            onError={(errors) => focusFirstError(errors)}
        >
            {({ processing, errors }) => (
                <Card className="gap-0 py-0">
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-4 py-6">
                        <div className="grid gap-1.5">
                            <CardTitle>
                                <h2>Invitar por correo</h2>
                            </CardTitle>
                            <CardDescription>
                                Le llega un enlace para unirse. Si no tiene
                                cuenta, la crea en el camino.
                            </CardDescription>
                        </div>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner /> : <UserPlus />}
                            Invitar
                        </Button>
                    </CardHeader>
                    <Separator />
                    <CardContent className="grid gap-6 py-6 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <div className="relative">
                                <Mail
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="email"
                                    {...fieldErrorProps('email', errors.email)}
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="off"
                                    placeholder="correo@ejemplo.com"
                                    className="pl-9"
                                />
                            </div>
                            <InputError
                                id="email-error"
                                message={errors.email}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">Rol</Label>
                            <Select
                                name="role"
                                defaultValue={roles[0]?.value}
                                required
                            >
                                <SelectTrigger
                                    id="role"
                                    {...fieldErrorProps('role', errors.role)}
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError id="role-error" message={errors.role} />
                        </div>
                    </CardContent>
                </Card>
            )}
        </Form>
    );
}

/** The «⋯» button of a row, named after the person or email it acts on. */
function RowActionsTrigger({ label }: { label: string }) {
    return (
        <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label={label}>
                <MoreHorizontal />
            </Button>
        </DropdownMenuTrigger>
    );
}

function MemberRow({ slug, member }: { slug: string; member: Member }) {
    const [confirming, setConfirming] = useState(false);

    return (
        <TableRow>
            <TableCell className="max-w-0 sm:max-w-none">
                <div className="flex min-w-0 items-center gap-3">
                    <InitialsAvatar name={member.name} />
                    <div className="grid min-w-0 leading-tight">
                        <span className="truncate font-medium">
                            {member.name}
                        </span>
                        <span
                            className="truncate text-sm text-muted-foreground"
                            title={member.email ?? undefined}
                        >
                            {member.email}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell className="text-muted-foreground">
                {member.role}
            </TableCell>
            <TableCell className="w-12 text-right">
                {member.removable && (
                    <>
                        <DropdownMenu>
                            <RowActionsTrigger
                                label={`Acciones para ${member.name}`}
                            />
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setConfirming(true)}
                                >
                                    Quitar del equipo
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <ConfirmDialog
                            open={confirming}
                            onOpenChange={setConfirming}
                            title={`¿Quitar a ${member.name} del equipo?`}
                            description="Pierde el acceso al estudio de inmediato y sus clases quedan sin instructor. Para que vuelva, envíale una invitación nueva."
                            action={removeMember.form([slug, member.id])}
                            confirmLabel="Quitar del equipo"
                        />
                    </>
                )}
            </TableCell>
        </TableRow>
    );
}

function InvitationRow({
    slug,
    invitation,
}: {
    slug: string;
    invitation: PendingInvitation;
}) {
    const [confirming, setConfirming] = useState(false);

    return (
        <TableRow>
            <TableCell className="max-w-0 sm:max-w-none">
                <div className="grid min-w-0 leading-tight">
                    <span
                        className="truncate font-medium"
                        title={invitation.email}
                    >
                        {invitation.email}
                    </span>
                    <span className="truncate text-sm text-muted-foreground">
                        {invitation.expired
                            ? 'Venció; reenvíala para darle otros días'
                            : `Vence el ${invitation.expiresOn}`}
                    </span>
                </div>
            </TableCell>
            <TableCell className="text-muted-foreground">
                <div className="flex flex-wrap items-center gap-2">
                    {invitation.role}
                    {invitation.expired && (
                        <Badge variant="outline">Vencida</Badge>
                    )}
                </div>
            </TableCell>
            <TableCell className="w-12 text-right">
                <DropdownMenu>
                    <RowActionsTrigger
                        label={`Acciones para la invitación a ${invitation.email}`}
                    />
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link
                                href={resendInvitation([slug, invitation.id])}
                                as="button"
                                preserveScroll
                                className="w-full"
                            >
                                Reenviar invitación
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setConfirming(true)}
                        >
                            Cancelar invitación
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <ConfirmDialog
                    open={confirming}
                    onOpenChange={setConfirming}
                    title="¿Cancelar la invitación?"
                    description={`El enlace que le llegó a ${invitation.email} dejará de servir.`}
                    action={cancelInvitation.form([slug, invitation.id])}
                    confirmLabel="Cancelar invitación"
                />
            </TableCell>
        </TableRow>
    );
}

function Count({ value }: { value: number }) {
    return (
        <span className="text-muted-foreground tabular-nums">({value})</span>
    );
}

export default function Team({ members, invitations, roles }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    return (
        <>
            <Head title="Equipo" />

            <div className="flex flex-col gap-8 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Equipo"
                    description="Quienes atienden el mesón y dan clases en tu estudio. Las alumnas están en «Alumnas»."
                />

                <InviteCard slug={studio.slug} roles={roles} />

                <Tabs defaultValue="members" className="gap-4">
                    <TabsList variant="line">
                        <TabsTrigger value="members">
                            Equipo <Count value={members.length} />
                        </TabsTrigger>
                        <TabsTrigger value="invitations">
                            Invitaciones pendientes{' '}
                            <Count value={invitations.length} />
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="members">
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Persona</TableHead>
                                        <TableHead>Rol</TableHead>
                                        <TableHead className="text-right">
                                            <span className="sr-only">
                                                Acciones
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {members.map((member) => (
                                        <MemberRow
                                            key={member.id}
                                            slug={studio.slug}
                                            member={member}
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </TabsContent>

                    <TabsContent value="invitations">
                        {invitations.length === 0 ? (
                            <p className="rounded-lg border px-4 py-8 text-center text-sm text-muted-foreground">
                                No hay invitaciones sin aceptar.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Correo</TableHead>
                                            <TableHead>Rol</TableHead>
                                            <TableHead className="text-right">
                                                <span className="sr-only">
                                                    Acciones
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invitations.map((invitation) => (
                                            <InvitationRow
                                                key={invitation.id}
                                                slug={studio.slug}
                                                invitation={invitation}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

Team.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Equipo', href: teamIndex(props.studio.slug) }]
            : []),
    ],
});
