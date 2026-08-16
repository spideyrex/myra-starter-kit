import { onScopeDispose, ref, type Ref } from 'vue';

export interface UseSseStream {
    text: Ref<string>;
    streaming: Ref<boolean>;
    error: Ref<string | null>;
    start(url: string, body: Record<string, unknown>): Promise<void>;
    abort(): void;
}

/**
 * Generalises the ad-hoc `data: {...}` reader the editor assist already uses.
 * One stream at a time; starting a second aborts the first.
 */
export function useSseStream(): UseSseStream {
    const text = ref('');
    const streaming = ref(false);
    const error = ref<string | null>(null);

    let controller: AbortController | null = null;

    function abort(): void {
        controller?.abort();
        controller = null;
        streaming.value = false;
    }

    async function start(url: string, body: Record<string, unknown>): Promise<void> {
        abort();

        text.value = '';
        error.value = null;
        streaming.value = true;
        controller = new AbortController();

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
                signal: controller.signal,
            });

            if (!response.ok || !response.body) {
                error.value = 'assistant.errors.providerDown';
                streaming.value = false;

                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    if (!line.startsWith('data: ')) continue;

                    const payload = line.slice(6).trim();
                    if (payload === '[DONE]') continue;

                    try {
                        const parsed = JSON.parse(payload) as { content?: string; error?: string };
                        if (parsed.error) error.value = parsed.error;
                        if (parsed.content) text.value += parsed.content;
                    } catch {
                        // A partial frame — the next chunk completes it.
                    }
                }
            }
        } catch (e) {
            if ((e as { name?: string })?.name !== 'AbortError') {
                error.value = 'assistant.errors.providerDown';
            }
        } finally {
            streaming.value = false;
            controller = null;
        }
    }

    onScopeDispose(abort);

    return { text, streaming, error, start, abort };
}
