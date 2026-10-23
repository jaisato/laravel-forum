import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';

const http = vi.hoisted(() => ({ submit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ useHttp: () => http }));

vi.mock('@/routes/two-factor', () => ({
    qrCode: () => ({ url: '/user/two-factor-qr-code', method: 'get' }),
    secretKey: () => ({ url: '/user/two-factor-secret-key', method: 'get' }),
    recoveryCodes: () => ({
        url: '/user/two-factor-recovery-codes',
        method: 'get',
    }),
}));

function respond(responses: Record<string, unknown>): void {
    http.submit.mockImplementation(async ({ url }: { url: string }) => {
        const response = responses[url];

        if (response instanceof Error) {
            throw response;
        }

        return response;
    });
}

describe('useTwoFactorAuth', () => {
    beforeEach(() => {
        http.submit.mockReset();
        useTwoFactorAuth().clearTwoFactorAuthData();
    });

    it('fetches the QR code and the setup key', async () => {
        respond({
            '/user/two-factor-qr-code': { svg: '<svg/>', url: 'otpauth://' },
            '/user/two-factor-secret-key': { secretKey: 'ABCD' },
        });
        const twoFactor = useTwoFactorAuth();

        await twoFactor.fetchSetupData();

        expect(twoFactor.qrCodeSvg.value).toBe('<svg/>');
        expect(twoFactor.manualSetupKey.value).toBe('ABCD');
        expect(twoFactor.hasSetupData.value).toBe(true);
        expect(twoFactor.errors.value).toEqual([]);
    });

    it('reports what it could not fetch', async () => {
        respond({
            '/user/two-factor-qr-code': new Error('500'),
            '/user/two-factor-secret-key': new Error('500'),
        });
        const twoFactor = useTwoFactorAuth();

        await twoFactor.fetchSetupData();

        expect(twoFactor.hasSetupData.value).toBe(false);
        expect(twoFactor.errors.value).toEqual([
            'Failed to fetch QR code',
            'Failed to fetch a setup key',
        ]);

        twoFactor.clearSetupData();

        expect(twoFactor.errors.value).toEqual([]);
    });

    it('fetches the recovery codes', async () => {
        respond({ '/user/two-factor-recovery-codes': ['one', 'two'] });
        const twoFactor = useTwoFactorAuth();

        await twoFactor.fetchRecoveryCodes();

        expect(twoFactor.recoveryCodesList.value).toEqual(['one', 'two']);

        respond({ '/user/two-factor-recovery-codes': new Error('500') });
        await twoFactor.fetchRecoveryCodes();

        expect(twoFactor.recoveryCodesList.value).toEqual([]);
        expect(twoFactor.errors.value).toEqual([
            'Failed to fetch recovery codes',
        ]);
    });

    it('forgets everything on demand', async () => {
        respond({ '/user/two-factor-recovery-codes': ['one'] });
        const twoFactor = useTwoFactorAuth();
        await twoFactor.fetchRecoveryCodes();

        twoFactor.clearTwoFactorAuthData();

        expect(twoFactor.recoveryCodesList.value).toEqual([]);
        expect(twoFactor.qrCodeSvg.value).toBeNull();
    });
});
