import { mount } from '@vue/test-utils';
import Pagination from '@/components/forum/Pagination.vue';
import type { PaginationLinks, PaginationMeta } from '@/types/forum';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

function paginator(
    current: number,
    last: number,
): {
    links: PaginationLinks;
    meta: PaginationMeta;
} {
    const url = (page: number) => `/threads?page=${page}`;

    return {
        links: {
            first: url(1),
            last: url(last),
            prev: current > 1 ? url(current - 1) : null,
            next: current < last ? url(current + 1) : null,
        },
        meta: {
            current_page: current,
            last_page: last,
            per_page: 20,
            total: last * 20,
            from: (current - 1) * 20 + 1,
            to: current * 20,
            path: '/threads',
            links: [
                {
                    url: current > 1 ? url(current - 1) : null,
                    label: '&laquo; Previous',
                    active: false,
                },
                ...Array.from({ length: last }, (_, index) => ({
                    url: url(index + 1),
                    label: String(index + 1),
                    active: index + 1 === current,
                })),
                {
                    url: current < last ? url(current + 1) : null,
                    label: 'Next &raquo;',
                    active: false,
                },
            ],
        },
    };
}

describe('Pagination', () => {
    it('disables the previous page on the first page', () => {
        const wrapper = mount(Pagination, { props: paginator(1, 3) });

        expect(
            wrapper
                .get('[data-test="previous-page"]')
                .attributes('aria-disabled'),
        ).toBe('true');
        expect(wrapper.get('[data-test="previous-page"]').element.tagName).toBe(
            'SPAN',
        );
        expect(wrapper.get('[data-test="next-page"]').attributes('href')).toBe(
            '/threads?page=2',
        );
    });

    it('disables the next page on the last page', () => {
        const wrapper = mount(Pagination, { props: paginator(3, 3) });

        expect(
            wrapper.get('[data-test="next-page"]').attributes('aria-disabled'),
        ).toBe('true');
        expect(
            wrapper.get('[data-test="previous-page"]').attributes('href'),
        ).toBe('/threads?page=2');
    });

    it('marks the current page and links the others', () => {
        const wrapper = mount(Pagination, { props: paginator(2, 3) });
        const pages = wrapper.findAll('[data-test="page"]');

        expect(pages.map((page) => page.text())).toEqual(['1', '2', '3']);
        expect(pages[1].attributes('aria-current')).toBe('page');
        expect(pages[0].attributes('href')).toBe('/threads?page=1');
    });

    it('renders nothing for a single page', () => {
        const wrapper = mount(Pagination, { props: paginator(1, 1) });

        expect(wrapper.find('nav').exists()).toBe(false);
    });
});
