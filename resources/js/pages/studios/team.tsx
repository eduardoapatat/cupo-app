import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    destroy as cancelInvitation,
    resend as resendInvitation,
    store as storeInvitation,
} from '@/actions/App/Http/Controllers/Studio/TeamInvitationController';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Studio/TeamController';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

function InviteForm({ slug, roles }: { slug: string; roles: Option[] }) {
    return (
        <Form
            {...storeInvitation.form(slug)}
            resetOnSuccess
            onError={(errors) => focusFirstError(errors)}
            className="grid gap-4 sm:grid-cols-[1fr_12rem_auto] sm:items-start"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Correo</Label>
                        <Input
                            id="email"
                            {...fieldErrorProps('email', errors.email)}
                            name="email"
                            type="email"
                            required
                            autoComplete="off"
                            placeholder="correo@ejemplo.com"
                        />
                        <InputError id="email-error" message={errors.email} />
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

                    <Button
                        type="submit"
                        disabled={processing}
                        className="sm:mt-[1.375rem]"
                    >
                        {processing && <Spinner />}
                        Invitar
                    </Button>
                </>
            )}
        </Form>
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
            <TableCell className="max-w-64 truncate" title={invitation.email}>
                {invitation.email}
            </TableCell>
            <TableCell>{invitation.role}</TableCell>
            <TableCell>
                {invitation.expired ? (
                    <Badge variant="outline">Vencida</Badge>
                ) : (
                    <span className="text-muted-foreground">
                        Vence el {invitation.expiresOn}
                    </span>
                )}
            </TableCell>
            <TableCell className="text-right">
                <div className="flex justify-end gap-2">
                    <Form
                        {...resendInvitation.form([slug, invitation.id])}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                disabled={processing}
                                aria-label={`Reenviar la invitación a ${invitation.email}`}
                            >
                                {processing && <Spinner />}
                                Reenviar
                            </Button>
                        )}
                    </Form>
                    <ConfirmDialog
                        open={confirming}
                        onOpenChange={setConfirming}
                        trigger={
                            <Button
                                variant="ghost"
                                size="sm"
                                aria-label={`Cancelar la invitación a ${invitation.email}`}
                            >
                                Cancelar
                            </Button>
                        }
                        title="¿Cancelar la invitación?"
                        description={`El enlace que le llegó a ${invitation.email} dejará de servir.`}
                        action={cancelInvitation.form([slug, invitation.id])}
                        confirmLabel="Cancelar invitación"
                    />
                </div>
            </TableCell>
        </TableRow>
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

            <div className="flex flex-col gap-10 px-4 py-6">
                <Heading
                    title="Equipo"
                    description="Quienes atienden el mesón y dan clases en tu estudio. Las alumnas están en «Alumnas»."
                />

                <section className="grid gap-4">
                    <Heading
                        variant="small"
                        title="Invitar a alguien"
                        description="Le llega un correo con un enlace para unirse. Si no tiene cuenta, la crea en el camino."
                    />
                    <InviteForm slug={studio.slug} roles={roles} />
                </section>

                <section className="grid gap-4">
                    <Heading variant="small" title="Quiénes son" />
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>Correo</TableHead>
                                    <TableHead>Rol</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.map((member) => (
                                    <TableRow key={member.id}>
                                        <TableCell className="font-medium">
                                            {member.name}
                                        </TableCell>
                                        <TableCell className="max-w-64 truncate text-muted-foreground">
                                            {member.email}
                                        </TableCell>
                                        <TableCell>{member.role}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section className="grid gap-4">
                    <Heading variant="small" title="Invitaciones pendientes" />
                    {invitations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No hay invitaciones sin aceptar.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Correo</TableHead>
                                        <TableHead>Rol</TableHead>
                                        <TableHead>Estado</TableHead>
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
                </section>
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
