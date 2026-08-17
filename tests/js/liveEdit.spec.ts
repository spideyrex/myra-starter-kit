import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { reactive } from 'vue';
import { applyPath } from '@/composables/useLiveEditHost';
import { envelope, isInlineKind, unwrap, LIVE_EDIT_CHANNEL } from '@/pagebuilder/liveEditProtocol';
import { startLiveEditAgent } from '@/pagebuilder/liveEditAgent';

describe('applyPath — repeater items reach a single root field', () => {
    it('returns a scalar field unchanged', () => {
        expect(applyPath({ title: 'a' }, 'title', 'b')).toEqual({ field: 'title', value: 'b' });
    });

    it('rewrites one item without mutating the source', () => {
        const data = { items: [{ title: 'one' }, { title: 'two' }] };
        const applied = applyPath(data, 'items.1.title', 'changed');

        expect(applied).toEqual({ field: 'items', value: [{ title: 'one' }, { title: 'changed' }] });

        // The composable's history depends on the previous value staying intact.
        expect(data.items[1].title).toBe('two');
    });

    it('reaches a nested path', () => {
        const data = { plans: [{ meta: { label: 'old' } }] };

        expect(applyPath(data, 'plans.0.meta.label', 'new')).toEqual({
            field: 'plans',
            value: [{ meta: { label: 'new' } }],
        });
    });

    /**
     * The real caller passes Vue reactive state, never a plain object, and a
     * reactive Proxy cannot be structured-cloned — DataCloneError. Cloning that
     * way broke every repeater edit in the browser while scalar fields kept
     * working, which is exactly what made it look fine. The plain-object cases
     * above all pass with the broken clone; only this one fails.
     */
    it('handles reactive state, which is what the builder actually passes', () => {
        const data = reactive({ items: [{ title: 'one' }, { title: 'two' }] });

        expect(applyPath(data, 'items.1.title', 'changed')).toEqual({
            field: 'items',
            value: [{ title: 'one' }, { title: 'changed' }],
        });

        expect(data.items[1].title).toBe('two');
    });

    it('drops a path that does not resolve rather than inventing structure', () => {
        expect(applyPath({}, 'items.0.title', 'x')).toBeNull();
        expect(applyPath({ items: 'not-a-list' }, 'items.0.title', 'x')).toBeNull();
        expect(applyPath({ items: [] }, '', 'x')).toBeNull();
    });
});

describe('the wire protocol only answers to its own channel', () => {
    it('round-trips through the envelope', () => {
        expect(unwrap<{ type: string }>(envelope({ type: 'ready' }))).toEqual({
            type: 'ready',
            channel: LIVE_EDIT_CHANNEL,
        });
    });

    it('ignores anything else on the message bus', () => {
        expect(unwrap({ type: 'ready' })).toBeNull();
        expect(unwrap({ channel: 'someone-else', type: 'ready' })).toBeNull();
        expect(unwrap({ channel: LIVE_EDIT_CHANNEL })).toBeNull();
        expect(unwrap(null)).toBeNull();
        expect(unwrap('ready')).toBeNull();
    });

    it('edits text in place and never an image or rich text', () => {
        expect(isInlineKind('text')).toBe(true);
        expect(isInlineKind('multiline')).toBe(true);
        expect(isInlineKind('image')).toBe(false);
        expect(isInlineKind('html')).toBe(false);
        expect(isInlineKind('link')).toBe(false);
    });
});

describe('the in-frame agent', () => {
    let posted: any[] = [];
    let stop: (() => void) | null = null;

    function mount(html: string): void {
        document.body.innerHTML = html;
    }

    beforeEach(() => {
        posted = [];
        vi.stubGlobal('parent', { postMessage: (msg: unknown) => posted.push(msg) } as unknown as Window);
        document.documentElement.removeAttribute('data-myra-live');
    });

    afterEach(() => {
        stop?.();
        stop = null;
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    function enable(): void {
        stop = startLiveEditAgent();

        window.dispatchEvent(
            new MessageEvent('message', {
                data: envelope({ type: 'enable', on: true }),
                origin: window.location.origin,
            }),
        );
    }

    it('does nothing at all when the page is not framed', () => {
        vi.stubGlobal('parent', window);
        mount('<section data-myra-block="a"><h1 data-myra-field="title">Hi</h1></section>');

        stop = startLiveEditAgent();

        expect(posted).toHaveLength(0);
        expect(document.querySelector('h1')!.getAttribute('contenteditable')).toBeNull();
    });

    it('announces itself and makes text fields editable once enabled', () => {
        mount('<section data-myra-block="a"><h1 data-myra-field="title" data-myra-kind="text">Hi</h1></section>');

        enable();

        expect(posted[0]).toMatchObject({ type: 'ready', channel: LIVE_EDIT_CHANNEL });
        expect(document.querySelector('h1')!.getAttribute('contenteditable')).not.toBeNull();
        expect(document.documentElement.hasAttribute('data-myra-live')).toBe(true);
    });

    it('leaves an unenabled frame completely inert', () => {
        mount('<section data-myra-block="a"><h1 data-myra-field="title" data-myra-kind="text">Hi</h1></section>');

        stop = startLiveEditAgent();

        expect(document.querySelector('h1')!.getAttribute('contenteditable')).toBeNull();
    });

    it('reports an edit against the block that owns the field', async () => {
        mount(
            '<section data-myra-block="blk-7"><h1 data-myra-field="title" data-myra-kind="text">Hi</h1></section>',
        );

        enable();

        const heading = document.querySelector('h1') as HTMLElement;
        heading.textContent = 'Changed';
        heading.dispatchEvent(new Event('blur'));

        const change = posted.find(m => m.type === 'change');

        expect(change).toMatchObject({ block: 'blk-7', field: 'title', value: 'Changed' });
    });

    it('carries the repeater path straight through', () => {
        mount(
            '<section data-myra-block="b"><p data-myra-field="items.2.description" data-myra-kind="multiline">x</p></section>',
        );

        enable();

        const p = document.querySelector('p') as HTMLElement;
        p.textContent = 'y';
        p.dispatchEvent(new Event('blur'));

        expect(posted.find(m => m.type === 'change')).toMatchObject({
            block: 'b',
            field: 'items.2.description',
            value: 'y',
        });
    });

    it('asks the builder to handle an image rather than editing it in place', () => {
        mount(
            '<section data-myra-block="c"><div data-myra-field="image_url" data-myra-kind="image"></div></section>',
        );

        enable();

        const box = document.querySelector('[data-myra-field]') as HTMLElement;

        expect(box.getAttribute('contenteditable')).toBeNull();

        box.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(posted.find(m => m.type === 'activate')).toMatchObject({
            block: 'c',
            field: 'image_url',
            kind: 'image',
        });
    });

    /** A field outside any block has no row to write back to. */
    it('ignores a marked field with no owning block', () => {
        mount('<h1 data-myra-field="title" data-myra-kind="text">Orphan</h1>');

        enable();

        expect(document.querySelector('h1')!.getAttribute('contenteditable')).toBeNull();
    });

    it('stops a link from navigating the preview away from the draft', () => {
        mount('<section data-myra-block="d"><a href="/elsewhere">Go</a></section>');

        enable();

        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        document.querySelector('a')!.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it('puts every field back when editing is turned off', () => {
        mount('<section data-myra-block="e"><h1 data-myra-field="title" data-myra-kind="text">Hi</h1></section>');

        enable();

        window.dispatchEvent(
            new MessageEvent('message', {
                data: envelope({ type: 'enable', on: false }),
                origin: window.location.origin,
            }),
        );

        expect(document.querySelector('h1')!.getAttribute('contenteditable')).toBeNull();
        expect(document.documentElement.hasAttribute('data-myra-live')).toBe(false);
    });

    it('refuses a message from another origin', () => {
        mount('<section data-myra-block="f"><h1 data-myra-field="title" data-myra-kind="text">Hi</h1></section>');

        stop = startLiveEditAgent();

        window.dispatchEvent(
            new MessageEvent('message', {
                data: envelope({ type: 'enable', on: true }),
                origin: 'https://evil.example',
            }),
        );

        expect(document.querySelector('h1')!.getAttribute('contenteditable')).toBeNull();
    });
});
