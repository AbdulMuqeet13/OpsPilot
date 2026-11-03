import { RelativeTime } from '@/components/relative-time';
import { initials } from '@/lib/brand';
import { cn } from '@/lib/utils';
import type { TicketMessage } from '@/types';

export function Conversation({
    messages,
    demoMode,
}: {
    messages: TicketMessage[];
    demoMode: boolean;
}) {
    return (
        <ol className="space-y-5">
            {messages.map((message) => {
                const isAgent = message.authorType === 'agent';

                return (
                    <li
                        key={message.id}
                        className={cn(
                            'flex gap-3',
                            isAgent && 'flex-row-reverse',
                        )}
                    >
                        <span
                            aria-hidden="true"
                            className={cn(
                                'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                isAgent
                                    ? 'bg-brand text-brand-foreground'
                                    : 'bg-slate-200 text-slate-600',
                            )}
                        >
                            {initials(message.authorName)}
                        </span>
                        <div
                            className={cn(
                                'max-w-[85%] min-w-0',
                                isAgent && 'text-right',
                            )}
                        >
                            <p className="text-xs text-slate-500">
                                <span className="font-medium text-slate-700">
                                    {message.authorName}
                                </span>
                                {isAgent ? ' replied' : ' wrote'} ·{' '}
                                <RelativeTime iso={message.sentAt} />
                            </p>
                            <div
                                className={cn(
                                    'mt-1 rounded-2xl px-4 py-3 text-left text-sm leading-6 whitespace-pre-wrap',
                                    isAgent
                                        ? 'rounded-tr-sm bg-brand-50 text-slate-800 ring-1 ring-brand-100'
                                        : 'rounded-tl-sm bg-white text-slate-800 ring-1 ring-slate-200',
                                )}
                            >
                                {message.body}
                            </div>
                            {isAgent && !message.wasEmailed && demoMode && (
                                <p className="mt-1 text-[11px] text-slate-400">
                                    Saved, not emailed (demo mode)
                                </p>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
