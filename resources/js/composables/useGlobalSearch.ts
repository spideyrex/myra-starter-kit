import { ref, watch } from 'vue';
import axios from 'axios';

interface SearchItem {
    id: number | string;
    title: string;
    description: string;
    url: string;
}

interface SearchGroup {
    group: string;
    items: SearchItem[];
}

export function useGlobalSearch() {
    const query = ref('');
    const results = ref<SearchGroup[]>([]);
    const loading = ref(false);
    const hasSearched = ref(false);

    let debounceTimer: ReturnType<typeof setTimeout>;

    watch(query, (val) => {
        clearTimeout(debounceTimer);

        if (val.length < 2) {
            results.value = [];
            hasSearched.value = false;
            return;
        }

        loading.value = true;
        debounceTimer = setTimeout(async () => {
            try {
                const response = await axios.get(route('admin.search'), {
                    params: { q: val },
                });
                results.value = response.data.results;
                hasSearched.value = true;
            } catch {
                results.value = [];
            } finally {
                loading.value = false;
            }
        }, 300);
    });

    function reset() {
        query.value = '';
        results.value = [];
        hasSearched.value = false;
    }

    return {
        query,
        results,
        loading,
        hasSearched,
        reset,
    };
}
