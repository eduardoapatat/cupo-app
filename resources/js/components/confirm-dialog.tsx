import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { RouteFormDefinition } from '@/wayfinder';

type ConfirmDialogProps = {
    /** Element that opens the dialog; omit it to control `open` from outside. */
    trigger?: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    title: string;
    description: string;
    /** The destructive request, from a Wayfinder `.form()` helper. */
    action: RouteFormDefinition<'post'>;
    confirmLabel: string;
    /** The button that closes without doing anything, when «Cancelar» would read as the action itself. */
    dismissLabel?: string;
};

/**
 * Asks before a destructive request. The red button only lives here. It
 * closes once the request succeeds, also when the page stays the same
 * (for example after renewing the enrollment link).
 */
export default function ConfirmDialog({
    trigger,
    open,
    onOpenChange,
    title,
    description,
    action,
    confirmLabel,
    dismissLabel = 'Cancelar',
}: ConfirmDialogProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const isOpen = open ?? uncontrolledOpen;

    function setOpen(next: boolean) {
        setUncontrolledOpen(next);
        onOpenChange?.(next);
    }

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form
                    {...action}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">
                                    {dismissLabel}
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {confirmLabel}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
