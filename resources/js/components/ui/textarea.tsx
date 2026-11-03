import type { TextareaHTMLAttributes } from 'react';
import { fieldClasses } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export function Textarea({
    className,
    ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea className={cn(fieldClasses, className)} {...props} />;
}
