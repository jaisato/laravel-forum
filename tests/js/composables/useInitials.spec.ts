import { getInitials, useInitials } from '@/composables/useInitials';

describe('getInitials', () => {
    it('uses the first letter of the first and last name', () => {
        expect(getInitials('Ada Lovelace')).toBe('AL');
    });

    it('uses a single letter for a single name', () => {
        expect(getInitials('Ada')).toBe('A');
    });

    it('returns an empty string without a name', () => {
        expect(getInitials('')).toBe('');
        expect(getInitials(undefined)).toBe('');
    });

    it('ignores extra spaces and blank names', () => {
        expect(getInitials('  Ada   King  Lovelace ')).toBe('AL');
        expect(getInitials('   ')).toBe('');
    });

    it('is also available as a composable', () => {
        expect(useInitials().getInitials('Grace Hopper')).toBe('GH');
    });
});
