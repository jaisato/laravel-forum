import { cn, toUrl } from '@/lib/utils';

describe('cn', () => {
    it('merges class lists, the last Tailwind utility winning', () => {
        expect(cn('p-2 text-sm', { hidden: false, flex: true }, 'p-4')).toBe(
            'text-sm flex p-4',
        );
    });
});

describe('toUrl', () => {
    it('accepts plain addresses and route definitions', () => {
        expect(toUrl('/threads')).toBe('/threads');
        expect(toUrl({ url: '/threads', method: 'get' })).toBe('/threads');
    });
});
