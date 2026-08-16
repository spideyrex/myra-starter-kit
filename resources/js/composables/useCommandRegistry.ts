import {
    computed,
    getCurrentScope,
    onScopeDispose,
    shallowRef,
    toValue,
    type Component,
    type ComputedRef,
    type MaybeRefOrGetter,
} from 'vue';
import { usePage } from '@inertiajs/vue3';
import i18n from '@/i18n';
import { highlightRuns, matchOffsets, type HighlightRun, type SearchMatch } from './useGlobalSearch';

export interface Command {
    id: string;
    titleKey: string;
    groupKey: string;
    icon?: Component;
    keywords?: string[];
    shortcut?: string;
    permission?: string;
    when?: () => boolean;
    run: (ctx: { close(): void }) => void | Promise<void>;
}

export interface CommandMatch extends Command {
    title: string;
    /** Offsets, so any host can render them through SearchHighlight.vue. */
    matches: SearchMatch[];
    runs: HighlightRun[];
}

interface RegisteredCommand {
    token: symbol;
    command: Command;
}

interface CommandSource {
    token: symbol;
    get: () => Command[];
}

const registered = shallowRef<RegisteredCommand[]>([]);
const sources = shallowRef<CommandSource[]>([]);

/**
 * Local permission helper. Bundle isolation forbids importing another bundle's
 * useCan, and duplicating six lines is cheaper than a cross-bundle dependency.
 *
 * This filtering is UX ONLY. Every command that does something privileged still
 * lands on a Gate-checked route; hiding it here is courtesy, not authority.
 */
function can(ability: string): boolean {
    try {
        const user = (usePage().props as any)?.auth?.user;
        if (!user) return false;

        return Boolean(user.roles?.includes('super-admin') || user.permissions?.includes(ability));
    } catch {
        return false;
    }
}

function translate(key: string): string {
    try {
        const out = (i18n.global.t as (k: string) => string)(key);
        return out || key;
    } catch {
        return key;
    }
}

/** Module-level registration. Returns its own unregister. */
export function registerCommands(...commands: Command[]): () => void {
    const token = Symbol('myra.commands');

    registered.value = [
        ...registered.value,
        ...commands.filter(c => c && typeof c.id === 'string').map(command => ({ token, command })),
    ];

    return () => {
        registered.value = registered.value.filter(entry => entry.token !== token);
    };
}

/**
 * Page-scoped and reactive: pass a getter and the palette follows it live.
 * Auto-unregisters with the owning effect scope.
 */
export function useCommandScope(commands: MaybeRefOrGetter<Command[]>): void {
    const token = Symbol('myra.commands.scope');

    sources.value = [
        ...sources.value,
        {
            token,
            get: () => {
                try {
                    const value = toValue(commands);
                    return Array.isArray(value) ? value : [];
                } catch {
                    return [];
                }
            },
        },
    ];

    if (getCurrentScope()) {
        onScopeDispose(() => {
            sources.value = sources.value.filter(source => source.token !== token);
        });
    }
}

/** Test-only reset; the palette never calls it. */
export function flushCommands(): void {
    registered.value = [];
    sources.value = [];
}

export function useCommandRegistry(): {
    commands: ComputedRef<Command[]>;
    match: (query: string) => CommandMatch[];
} {
    const commands = computed<Command[]>(() => {
        try {
            const all = [
                ...registered.value.map(entry => entry.command),
                ...sources.value.flatMap(source => source.get()),
            ];

            const seen = new Set<string>();
            const out: Command[] = [];

            for (const command of all) {
                if (!command || typeof command.id !== 'string' || seen.has(command.id)) continue;
                if (command.permission && !can(command.permission)) continue;

                try {
                    if (command.when && !command.when()) continue;
                } catch {
                    continue;
                }

                seen.add(command.id);
                out.push(command);
            }

            return out;
        } catch {
            return [];
        }
    });

    function match(query: string): CommandMatch[] {
        const q = (query ?? '').trim();

        return commands.value
            .map(command => {
                const title = translate(command.titleKey);
                const matches = q === '' ? [] : matchOffsets(title, q, 'title');
                const keywordHit = q !== ''
                    && (command.keywords ?? []).some(k => k.toLowerCase().includes(q.toLowerCase()));

                if (q !== '' && matches.length === 0 && !keywordHit) return null;

                return { ...command, title, matches, runs: highlightRuns(title, matches, 'title') };
            })
            .filter((c): c is CommandMatch => c !== null);
    }

    return { commands, match };
}
