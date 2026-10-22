import { mount } from '@vue/test-utils';
import { update } from '@/actions/App/Http/Controllers/LocaleController';
import LocaleSwitcher from '@/components/forum/LocaleSwitcher.vue';

const { post, page } = vi.hoisted(() => ({
    post: vi.fn(),
    page: { props: { locale: 'es', locales: ['es', 'en'] } },
}));

vi.mock('@inertiajs/vue3', () => ({ router: { post }, usePage: () => page }));

describe('LocaleSwitcher', () => {
    beforeEach(() => post.mockClear());

    it('posts the chosen language', async () => {
        const wrapper = mount(LocaleSwitcher);

        await wrapper.get('[data-locale="en"]').trigger('click');

        expect(post).toHaveBeenCalledWith(
            update.url(),
            { locale: 'en' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('marks the current language and does not post it again', async () => {
        const wrapper = mount(LocaleSwitcher);

        expect(
            wrapper.get('[data-locale="es"]').attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper.get('[data-locale="en"]').attributes('aria-pressed'),
        ).toBe('false');

        await wrapper.get('[data-locale="es"]').trigger('click');

        expect(post).not.toHaveBeenCalled();
    });
});
