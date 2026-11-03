import { SparklesIcon } from '@/components/icons';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useAiStream } from '@/hooks/use-ai-stream';
import { store as connectionTest } from '@/routes/ai-connection-test';

export function AiConnectionTest() {
    const { text, status, error, start, cancel } = useAiStream();

    return (
        <div className="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-slate-900">
                        Connection test
                    </p>
                    <p className="text-xs text-slate-500">
                        Streams a two-sentence reply. Costs a fraction of a
                        cent.
                    </p>
                </div>
                {status === 'streaming' ? (
                    <Button variant="secondary" size="sm" onClick={cancel}>
                        Stop
                    </Button>
                ) : (
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => void start(connectionTest.url())}
                    >
                        <SparklesIcon className="size-4 text-brand" />
                        {status === 'idle' ? 'Run test' : 'Run again'}
                    </Button>
                )}
            </div>

            {(status === 'streaming' || status === 'done') && (
                <p
                    aria-live="polite"
                    className="mt-3 min-h-10 rounded-md bg-white p-3 text-sm whitespace-pre-wrap text-slate-700 ring-1 ring-slate-200"
                >
                    {text}
                    {status === 'streaming' && (
                        <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse rounded-sm bg-brand align-text-bottom" />
                    )}
                </p>
            )}

            {status === 'error' && error && (
                <Alert tone="error" className="mt-3">
                    {error}
                </Alert>
            )}
        </div>
    );
}
