import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import SearchIndex from '@/pages/search/Index.vue';
import type {
    Paginated,
    PostSummary,
    SearchFilters,
    ThreadSummary,
} from '@/types/forum';

const page = vi.hoisted(() => ({ props: { locale: 'en' } }));
const router = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = () => h('div');
    Head.props = ['title'];

    return {
        router,
        usePage: () => page,
        Head,
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

function paginated<T>(data: T[]): Paginated<T> {
    return {
        data,
        links: { first: null, last: null, prev: null, next: null },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: data.length,
            from: null,
            to: null,
            path: '/search',
            links: [],
        },
    };
}

function search(
    filters: Partial<SearchFilters> = {},
    results: {
        threads?: Paginated<ThreadSummary> | null;
        posts?: Paginated<PostSummary> | null;
    } = {},
) {
    return mount(SearchIndex, {
        props: {
            filters: {
                q: '',
                type: 'threads',
                category: null,
                tag: null,
                ...filters,
            },
            threads: null,
            posts: null,
            categories: [
                { name: 'Laravel', slug: 'laravel' },
                { name: 'Vue', slug: 'vue' },
            ],
            tags: [{ name: 'eloquent', slug: 'eloquent' }],
            ...results,
        },
        global: { stubs: { ThreadList: true, PostSummaryList: true } },
    });
}

describe('search/Index', () => {
    beforeEach(() => {
        router.get.mockReset();
    });

    it('invites to search before there is anything to look for', () => {
        const wrapper = search();

        expect(wrapper.find('[data-test="search-prompt"]').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'ThreadList' }).exists()).toBe(
            false,
        );
    });

    it('searches what was typed, narrowed as chosen', async () => {
        const wrapper = search();

        await wrapper.get('input[type="search"]').setValue('  colas ');
        await wrapper.get('[data-test="search-category"]').setValue('laravel');
        router.get.mockReset();
        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith(
            '/search?q=colas&category=laravel',
        );
    });

    it('narrows the search as soon as a tag is chosen', async () => {
        const wrapper = search(
            { q: 'colas' },
            { threads: paginated<ThreadSummary>([]) },
        );

        await wrapper.get('[data-test="search-tag"]').setValue('eloquent');

        expect(router.get).toHaveBeenCalledWith('/search?q=colas&tag=eloquent');
    });

    it('switches between threads and posts, keeping the search', () => {
        const wrapper = search(
            { q: 'colas', category: 'laravel' },
            { threads: paginated<ThreadSummary>([]) },
        );
        const types = wrapper.findAll('[data-test="search-type"]');

        expect(types.map((type) => type.attributes('href'))).toEqual([
            '/search?q=colas&category=laravel',
            '/search?q=colas&type=posts&category=laravel',
        ]);
        expect(types[0].attributes('aria-current')).toBe('page');
    });

    it('shows the threads it found', () => {
        const wrapper = search(
            { q: 'colas' },
            { threads: paginated<ThreadSummary>([]) },
        );

        expect(wrapper.find('[data-test="search-prompt"]').exists()).toBe(
            false,
        );
        expect(wrapper.findComponent({ name: 'ThreadList' }).exists()).toBe(
            true,
        );
    });

    it('shows the posts it found', () => {
        const wrapper = search(
            { q: 'colas', type: 'posts' },
            { posts: paginated<PostSummary>([]) },
        );

        expect(
            wrapper.findComponent({ name: 'PostSummaryList' }).exists(),
        ).toBe(true);
        expect(wrapper.findComponent({ name: 'ThreadList' }).exists()).toBe(
            false,
        );
    });
});
