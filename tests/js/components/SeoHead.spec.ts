import { mount } from '@vue/test-utils';
import SeoHead from '@/components/SeoHead.vue';

const page = vi.hoisted(() => ({
    props: {
        name: 'Foro',
        canonical: 'https://foro.example.com/t/colas',
    } as Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        usePage: () => page,
        // The head tags render in place, where the test can read them.
        Head: defineComponent({
            setup:
                (_, { slots }) =>
                () =>
                    h('div', slots.default?.()),
        }),
    };
});

describe('SeoHead', () => {
    beforeEach(() => {
        delete page.props.meta;
    });

    it('gives every page its canonical address', () => {
        const wrapper = mount(SeoHead);

        expect(wrapper.get('link[rel="canonical"]').attributes('href')).toBe(
            'https://foro.example.com/t/colas',
        );
        expect(
            wrapper.get('meta[property="og:url"]').attributes('content'),
        ).toBe('https://foro.example.com/t/colas');
        expect(
            wrapper.get('meta[property="og:site_name"]').attributes('content'),
        ).toBe('Foro');
        expect(wrapper.find('meta[property="og:title"]').exists()).toBe(false);
    });

    it('describes pages that say what they are about', () => {
        page.props.meta = {
            title: '¿Cómo testear colas?',
            description: 'Mis colas no procesan los jobs.',
        };

        const wrapper = mount(SeoHead);

        expect(
            wrapper.get('meta[property="og:title"]').attributes('content'),
        ).toBe('¿Cómo testear colas?');
        expect(
            wrapper
                .get('meta[property="og:description"]')
                .attributes('content'),
        ).toBe('Mis colas no procesan los jobs.');
        expect(
            wrapper.get('meta[name="twitter:card"]').attributes('content'),
        ).toBe('summary');
    });
});
