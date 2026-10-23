import { mount } from '@vue/test-utils';
import type { FunctionalComponent } from 'vue';
import ErrorPage from '@/pages/Error.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const Head: FunctionalComponent<{ title: string }> = (props) =>
        h('div', { 'data-test': 'head', title: props.title });
    Head.props = ['title'];

    return {
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

describe('Error', () => {
    it.each([
        [403, 'You may not see this page'],
        [404, 'Page not found'],
        [429, 'Too many requests'],
        [500, 'Something went wrong'],
        [503, 'Back very soon'],
    ])('explains a %i', (status, title) => {
        const wrapper = mount(ErrorPage, { props: { status } });

        expect(wrapper.get('[data-test="head"]').attributes('title')).toBe(
            title,
        );
        expect(wrapper.get('h1').text()).toBe(title);
        expect(wrapper.get('[data-test="error-status"]').text()).toBe(
            String(status),
        );
        expect(wrapper.get('[data-test="error-description"]').text()).not.toBe(
            '',
        );
    });

    it('takes the member back home', () => {
        const wrapper = mount(ErrorPage, { props: { status: 404 } });

        expect(wrapper.get('[data-test="error-home"]').attributes('href')).toBe(
            '/',
        );
    });
});
