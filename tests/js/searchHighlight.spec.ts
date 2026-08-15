import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SearchHighlight from '@/components/admin/SearchHighlight.vue';
import { highlightRuns } from '@/composables/useGlobalSearch';

describe('highlightRuns', () => {
    it('splits a title into plain and marked runs', () => {
        expect(highlightRuns('Alice Anderson', [{ field: 'title', start: 0, length: 5 }]))
            .toEqual([
                { text: 'Alice', mark: true },
                { text: ' Anderson', mark: false },
            ]);
    });

    it('returns the whole string when there is nothing to mark', () => {
        expect(highlightRuns('Alice', [])).toEqual([{ text: 'Alice', mark: false }]);
        expect(highlightRuns('Alice', undefined)).toEqual([{ text: 'Alice', mark: false }]);
    });

    it('ignores ranges belonging to another field or out of bounds', () => {
        expect(highlightRuns('Alice', [{ field: 'description', start: 0, length: 5 }]))
            .toEqual([{ text: 'Alice', mark: false }]);
        expect(highlightRuns('Alice', [{ field: 'title', start: 99, length: 5 }]))
            .toEqual([{ text: 'Alice', mark: false }]);
    });

    it('handles a multibyte offset', () => {
        expect(highlightRuns('Zhang 北京 Li', [{ field: 'title', start: 6, length: 2 }]))
            .toEqual([
                { text: 'Zhang ', mark: false },
                { text: '北京', mark: true },
                { text: ' Li', mark: false },
            ]);
    });
});

describe('SearchHighlight rendering', () => {
    it('renders hostile user content as literal text, never as markup', () => {
        const wrapper = mount(SearchHighlight, {
            props: {
                text: '<img src=x onerror=alert(1)>',
                matches: [{ field: 'title', start: 0, length: 4 }],
            },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toBe('<img src=x onerror=alert(1)>');
        expect(wrapper.get('mark').text()).toBe('<img');
        expect(wrapper.html()).toContain('&lt;img');
    });

    it('wraps only the matched run in a real <mark> element', () => {
        const wrapper = mount(SearchHighlight, {
            props: {
                text: 'Alice Anderson',
                matches: [{ field: 'title', start: 6, length: 8 }],
            },
        });

        const marks = wrapper.findAll('mark');
        expect(marks).toHaveLength(1);
        expect(marks[0].text()).toBe('Anderson');
        expect(wrapper.text()).toBe('Alice Anderson');
    });

    it('renders the description field independently', () => {
        const wrapper = mount(SearchHighlight, {
            props: {
                text: 'alice@example.com',
                field: 'description',
                matches: [{ field: 'description', start: 0, length: 5 }],
            },
        });

        expect(wrapper.get('mark').text()).toBe('alice');
    });
});
