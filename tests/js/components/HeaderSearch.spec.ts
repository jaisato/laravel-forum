import { mount } from '@vue/test-utils';
import HeaderSearch from '@/components/forum/HeaderSearch.vue';

const router = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        router,
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots, attrs }) =>
                () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.()),
        }),
    };
});

describe('HeaderSearch', () => {
    beforeEach(() => {
        router.get.mockReset();
    });

    it('searches the forum for what was typed', async () => {
        const wrapper = mount(HeaderSearch);

        await wrapper.get('input[type="search"]').setValue('  colas  ');
        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith('/search?q=colas');
    });

    it('opens the search page when nothing was typed', async () => {
        const wrapper = mount(HeaderSearch);

        await wrapper.get('form').trigger('submit');

        expect(router.get).toHaveBeenCalledWith('/search');
    });

    it('gives small screens a link to the search page', () => {
        const wrapper = mount(HeaderSearch);

        expect(
            wrapper.get('[data-test="search-link"]').attributes('href'),
        ).toBe('/search');
    });
});
