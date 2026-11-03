import type { ReactNode } from 'react';
import { FieldError } from '@/components/ui/field-error';
import { Label } from '@/components/ui/label';

type FormFieldProps = {
    label: string;
    htmlFor: string;
    error?: string;
    hint?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
};

export function FormField({
    label,
    htmlFor,
    error,
    hint,
    action,
    children,
}: FormFieldProps) {
    return (
        <div>
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <Label htmlFor={htmlFor}>{label}</Label>
                {action}
            </div>
            {children}
            {hint && !error && (
                <p className="mt-1.5 text-xs text-slate-500">{hint}</p>
            )}
            <FieldError message={error} id={`${htmlFor}-error`} />
        </div>
    );
}
