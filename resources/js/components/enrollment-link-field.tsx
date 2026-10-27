import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';

/**
 * The studio's enrollment link, ready to copy and share. Used where the
 * owner manages it («Estudio») and where the front desk enrolls students.
 */
export default function EnrollmentLinkField({ url }: { url: string }) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === url;

    async function copyLink() {
        if (await copy(url)) {
            toast.success('Enlace copiado. Compártelo con tus alumnas.');
        } else {
            toast.error('No pudimos copiarlo. Selecciónalo y cópialo a mano.');
        }
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor="enrollment-link">Enlace de inscripción</Label>
            <div className="flex gap-2">
                <Input
                    id="enrollment-link"
                    value={url}
                    readOnly
                    onFocus={(event) => event.currentTarget.select()}
                    className="font-mono text-xs"
                />
                <Button
                    type="button"
                    variant="secondary"
                    onClick={copyLink}
                    aria-label="Copiar el enlace de inscripción"
                >
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copiado' : 'Copiar'}
                </Button>
            </div>
        </div>
    );
}
