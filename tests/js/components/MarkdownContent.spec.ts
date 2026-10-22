import { mount } from '@vue/test-utils';
import MarkdownContent from '@/components/forum/MarkdownContent.vue';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: { visit } }));

describe('MarkdownContent', () => {
    beforeEach(() => {
        visit.mockClear();
        window.history.replaceState({}, '', '/t/current-thread');
    });

    it('renders the sanitised HTML it receives', () => {
        const wrapper = mount(MarkdownContent, {
            props: { html: '<p>Hola <strong>mundo</strong></p>' },
        });

        expect(wrapper.get('strong').text()).toBe('mundo');
    });

    it('opens links to the forum as Inertia visits', async () => {
        const wrapper = mount(MarkdownContent, {
            props: { html: '<p><a class="mention" href="/u/ana">@ana</a></p>' },
            attachTo: document.body,
        });

        await wrapper.get('a').trigger('click', { button: 0 });

        expect(visit).toHaveBeenCalledWith('/u/ana');
        wrapper.unmount();
    });

    it('leaves external links and new tabs to the browser', async () => {
        // Runs after the component's handler: keeps happy-dom from really
        // navigating once the browser default is allowed through.
        const stopNavigation = (event: Event) => event.preventDefault();
        document.addEventListener('click', stopNavigation);

        const wrapper = mount(MarkdownContent, {
            props: {
                html: '<a href="https://example.org" target="_blank">fuera</a> <a href="/t/other">dentro</a>',
            },
            attachTo: document.body,
        });

        await wrapper.get('a[target="_blank"]').trigger('click', { button: 0 });
        await wrapper
            .get('a[href="/t/other"]')
            .trigger('click', { button: 0, ctrlKey: true });

        expect(visit).not.toHaveBeenCalled();
        wrapper.unmount();
        document.removeEventListener('click', stopNavigation);
    });
});
