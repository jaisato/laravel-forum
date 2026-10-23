import { mount } from '@vue/test-utils';
import SimilarThreads from '@/components/forum/SimilarThreads.vue';
import type { SimilarThread } from '@/types/forum';

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

const threads: SimilarThread[] = [
    {
        id: 2,
        title: 'Horizon no procesa nada',
        slug: 'horizon-no-procesa-nada',
        replies_count: 3,
        is_solved: true,
    },
    {
        id: 3,
        title: 'Las colas se quedan pendientes',
        slug: 'las-colas-se-quedan-pendientes',
        replies_count: 0,
        is_solved: false,
    },
];

describe('SimilarThreads', () => {
    it('links the related threads, with their replies and whether they are solved', () => {
        const wrapper = mount(SimilarThreads, { props: { threads } });

        const links = wrapper.findAll('[data-test="related-thread"]');
        expect(links).toHaveLength(2);
        expect(links[0].attributes('href')).toBe('/t/horizon-no-procesa-nada');
        expect(links[0].text()).toContain('Horizon no procesa nada');
        expect(wrapper.findAll('[data-test="related-solved"]')).toHaveLength(1);
    });

    it('shows nothing without related threads', () => {
        const wrapper = mount(SimilarThreads, { props: { threads: [] } });

        expect(wrapper.find('[data-test="related-threads"]').exists()).toBe(
            false,
        );
    });
});
