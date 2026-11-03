import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ChevronDownIcon, LogoutIcon, UserIcon } from '@/components/icons';
import { Badge } from '@/components/ui/badge';
import { initials } from '@/lib/brand';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

export function UserMenu() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        function close(event: MouseEvent | KeyboardEvent) {
            if (
                event instanceof KeyboardEvent
                    ? event.key === 'Escape'
                    : !menuRef.current?.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    if (!auth.user) {
        return null;
    }

    return (
        <div className="relative" ref={menuRef}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="menu"
                className="flex items-center gap-2 rounded-lg p-1.5 text-left hover:bg-slate-100"
            >
                <span className="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800">
                    {initials(auth.user.name)}
                </span>
                <span className="hidden min-w-0 sm:block">
                    <span className="block truncate text-sm font-medium text-slate-900">
                        {auth.user.name}
                    </span>
                    <span className="block text-xs text-slate-500">
                        {auth.user.roleLabel}
                    </span>
                </span>
                <ChevronDownIcon className="hidden size-4 text-slate-400 sm:block" />
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute right-0 z-40 mt-2 w-60 rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-slate-200"
                >
                    <div className="px-3 py-2">
                        <p className="truncate text-sm font-medium text-slate-900">
                            {auth.user.name}
                        </p>
                        <p className="truncate text-xs text-slate-500">
                            {auth.user.email}
                        </p>
                        <Badge tone="brand" className="mt-2">
                            {auth.user.roleLabel}
                        </Badge>
                    </div>
                    <div className="my-1 border-t border-slate-100" />
                    <Link
                        href={editProfile()}
                        role="menuitem"
                        onClick={() => setOpen(false)}
                        className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        <UserIcon className="size-4 text-slate-400" />
                        Your profile
                    </Link>
                    <Link
                        href={logout()}
                        as="button"
                        role="menuitem"
                        className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        <LogoutIcon className="size-4 text-slate-400" />
                        Sign out
                    </Link>
                </div>
            )}
        </div>
    );
}
