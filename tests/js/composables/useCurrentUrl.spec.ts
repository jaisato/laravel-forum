import { useCurrentUrl } from '@/composables/useCurrentUrl';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/threads?page=2' }),
}));

describe('useCurrentUrl', () => {
    const { currentUrl, isCurrentUrl, isCurrentOrParentUrl, whenCurrentUrl } =
        useCurrentUrl();

    it('knows the path of the current page', () => {
        expect(currentUrl.value).toBe('/threads');
    });

    it('compares relative, absolute and route addresses', () => {
        expect(isCurrentUrl('/threads')).toBe(true);
        expect(isCurrentUrl({ url: '/threads', method: 'get' })).toBe(true);
        expect(isCurrentUrl('http://localhost/threads')).toBe(true);
        expect(isCurrentUrl('/settings')).toBe(false);
        expect(isCurrentUrl('/threads', '/other')).toBe(false);
    });

    it('treats a malformed absolute address as another page', () => {
        expect(isCurrentUrl('http://[')).toBe(false);
    });

    it('matches parent sections', () => {
        expect(isCurrentOrParentUrl('/thr')).toBe(true);
        expect(isCurrentOrParentUrl('/settings')).toBe(false);
    });

    it('picks a value depending on the current page', () => {
        expect(whenCurrentUrl('/threads', 'active')).toBe('active');
        expect(whenCurrentUrl('/settings', 'active')).toBeNull();
        expect(whenCurrentUrl('/settings', 'active', 'idle')).toBe('idle');
    });
});
