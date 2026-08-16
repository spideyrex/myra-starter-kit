import { ref, toValue, type MaybeRefOrGetter, type Ref } from 'vue';
import axios from 'axios';
import type { QueryGroup, QueryRule } from '@/types/admin';
import type { QueryConstraintSchema } from '@/types/query-builder';

export type AskBarState = 'idle' | 'compiling' | 'review' | 'error';

export interface AskBarSchema {
    maxRules?: number;
    maxDepth?: number;
    fields?: Array<QueryConstraintSchema | { name: string; type: string; operators: string[] }>;
}

export interface UseAskBar {
    prompt: Ref<string>;
    state: Ref<AskBarState>;
    /** EDITABLE before it runs. The server minted it; the user still approves it. */
    draft: Ref<QueryGroup | null>;
    error: Ref<string | null>;
    compile(): Promise<void>;
    editRule(path: number[], patch: Partial<QueryRule>): void;
    dropRule(path: number[]): void;
    apply(): QueryGroup;
    discard(): void;
}

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}

/**
 * `path` addresses one rule: every element but the last indexes into `groups`,
 * the last indexes into that group's `rules`.
 */
function groupAt(root: QueryGroup, path: number[]): QueryGroup | null {
    let node: QueryGroup = root;

    for (const index of path.slice(0, -1)) {
        const child = node.groups?.[index];
        if (!child) return null;
        node = child;
    }

    return node;
}

/**
 * Compiles a sentence into a rule tree the SERVER validated, and hands it back
 * for review. Nothing here applies a filter: `apply()` returns the tree and the
 * caller sends it down the normal filter path, where RuleTree::parse() runs
 * again. The transport has no authority.
 */
export function useAskBar(options: {
    scope: MaybeRefOrGetter<string>;
    schema: MaybeRefOrGetter<AskBarSchema | null | undefined>;
}): UseAskBar {
    const prompt = ref('');
    const state = ref<AskBarState>('idle');
    const draft = ref<QueryGroup | null>(null);
    const error = ref<string | null>(null);

    let controller: AbortController | null = null;

    async function compile(): Promise<void> {
        const text = prompt.value.trim();

        if (text === '') {
            error.value = 'assistant.errors.promptLength';
            state.value = 'error';

            return;
        }

        controller?.abort();
        controller = new AbortController();

        state.value = 'compiling';
        error.value = null;

        try {
            const { data } = await axios.post(
                route('admin.ai.filter'),
                { prompt: text, scope: toValue(options.scope) },
                { signal: controller.signal },
            );

            draft.value = (data?.tree ?? null) as QueryGroup | null;
            state.value = draft.value ? 'review' : 'error';

            if (!draft.value) error.value = 'assistant.errors.couldNotUnderstand';
        } catch (e) {
            const status = (e as { response?: { status?: number } })?.response?.status;
            const body = (e as { response?: { data?: { message?: string } } })?.response?.data;

            error.value = status === 429
                ? 'assistant.errors.rateLimited'
                : (body?.message || 'assistant.errors.couldNotUnderstand');
            state.value = 'error';
        } finally {
            controller = null;
        }
    }

    function editRule(path: number[], patch: Partial<QueryRule>): void {
        if (!draft.value || path.length === 0) return;

        const next = clone(draft.value);
        const group = groupAt(next, path);
        const index = path[path.length - 1];
        const rule = group?.rules?.[index];
        if (!group || !rule) return;

        group.rules[index] = { ...rule, ...patch };
        draft.value = next;
    }

    function dropRule(path: number[]): void {
        if (!draft.value || path.length === 0) return;

        const next = clone(draft.value);
        const group = groupAt(next, path);
        const index = path[path.length - 1];
        if (!group || !group.rules?.[index]) return;

        group.rules.splice(index, 1);
        draft.value = next;
    }

    function apply(): QueryGroup {
        return draft.value ? clone(draft.value) : { conjunction: 'and', rules: [], groups: [] };
    }

    function discard(): void {
        controller?.abort();
        controller = null;
        draft.value = null;
        error.value = null;
        state.value = 'idle';
    }

    return { prompt, state, draft, error, compile, editRule, dropRule, apply, discard };
}
