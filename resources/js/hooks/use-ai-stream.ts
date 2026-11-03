import { useCallback, useEffect, useRef, useState } from 'react';

export type AiStreamStatus = 'idle' | 'streaming' | 'done' | 'error';

type StreamEvent = { event: string; data: string };

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function parseEvents(buffer: string): { events: StreamEvent[]; rest: string } {
    const blocks = buffer.split(/\r?\n\r?\n/);
    const rest = blocks.pop() ?? '';
    const events = blocks.flatMap((block) => {
        let event = 'message';
        const data: string[] = [];

        for (const line of block.split(/\r?\n/)) {
            if (line.startsWith('event:')) {
                event = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                data.push(line.slice(5).trimStart());
            }
        }

        return data.length > 0 ? [{ event, data: data.join('\n') }] : [];
    });

    return { events, rest };
}

/**
 * POSTs to an AiTextStream endpoint and exposes the text as it arrives.
 */
export function useAiStream() {
    const [text, setText] = useState('');
    const [status, setStatus] = useState<AiStreamStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const controllerRef = useRef<AbortController | null>(null);

    useEffect(() => () => controllerRef.current?.abort(), []);

    const start = useCallback(
        async (
            url: string,
            body: Record<string, unknown> = {},
            options: {
                onDone?: (payload: Record<string, unknown>) => void;
            } = {},
        ) => {
            controllerRef.current?.abort();
            const controller = new AbortController();
            controllerRef.current = controller;

            setText('');
            setError(null);
            setStatus('streaming');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/event-stream',
                        'X-XSRF-TOKEN': xsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                    signal: controller.signal,
                });

                if (!response.ok || !response.body) {
                    const payload = (await response
                        .json()
                        .catch(() => null)) as { message?: string } | null;

                    throw new Error(
                        payload?.message ??
                            'Something went wrong. Please try again.',
                    );
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                for (;;) {
                    const { value, done } = await reader.read();

                    if (done) {
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    const parsed = parseEvents(buffer);
                    buffer = parsed.rest;

                    for (const { event, data } of parsed.events) {
                        const payload = JSON.parse(data) as Record<
                            string,
                            unknown
                        > & {
                            text?: string;
                            message?: string;
                        };

                        if (event === 'delta' && payload.text) {
                            setText((current) => current + payload.text);
                        } else if (event === 'done') {
                            setText(payload.text ?? '');
                            setStatus('done');
                            options.onDone?.(payload);
                        } else if (event === 'error') {
                            throw new Error(payload.message);
                        }
                    }
                }

                setStatus((current) =>
                    current === 'streaming' ? 'done' : current,
                );
            } catch (caught) {
                if (controller.signal.aborted) {
                    return;
                }

                setError(
                    caught instanceof Error && caught.message
                        ? caught.message
                        : 'Something went wrong. Please try again.',
                );
                setStatus('error');
            }
        },
        [],
    );

    const cancel = useCallback(() => {
        controllerRef.current?.abort();
        setStatus((current) => (current === 'streaming' ? 'idle' : current));
    }, []);

    return { text, status, error, start, cancel };
}
