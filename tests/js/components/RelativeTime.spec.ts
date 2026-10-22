import { mount } from '@vue/test-utils';
import RelativeTime from '@/components/forum/RelativeTime.vue';

const page = vi.hoisted(() => ({ props: { locale: 'es' } }));

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

describe('RelativeTime', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-22T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('says how long ago in Spanish', () => {
        page.props.locale = 'es';

        const wrapper = mount(RelativeTime, {
            props: { datetime: '2026-10-22T11:55:00Z' },
        });

        expect(wrapper.text()).toBe('hace 5 minutos');
        expect(wrapper.attributes('datetime')).toBe('2026-10-22T11:55:00Z');
    });

    it('says how long ago in English', () => {
        page.props.locale = 'en';

        const wrapper = mount(RelativeTime, {
            props: { datetime: '2026-10-22T11:55:00Z' },
        });

        expect(wrapper.text()).toBe('5 minutes ago');
    });

    it('moves to bigger units for older dates and keeps the exact date in the title', () => {
        page.props.locale = 'en';

        const wrapper = mount(RelativeTime, {
            props: { datetime: '2026-10-19T12:00:00Z' },
        });

        expect(wrapper.text()).toBe('3 days ago');
        expect(wrapper.attributes('title')).toContain('2026');
    });

    it('keeps up with the clock', async () => {
        page.props.locale = 'en';

        const wrapper = mount(RelativeTime, {
            props: { datetime: '2026-10-22T11:59:30Z' },
        });

        expect(wrapper.text()).toBe('now');

        await vi.advanceTimersByTimeAsync(2 * 60 * 1000);

        expect(wrapper.text()).toBe('2 minutes ago');
    });
});
