import { initializeFlashToast } from '@/lib/flashToast';

const handlers = vi.hoisted(() => new Map<string, (event: Event) => void>());
const toast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: (name: string, handler: (event: Event) => void) => {
            handlers.set(name, handler);
        },
    },
}));

vi.mock('vue-sonner', () => ({ toast }));

function flash(data: unknown): void {
    handlers.get('flash')?.(
        new CustomEvent('flash', { detail: { flash: data } }),
    );
}

describe('initializeFlashToast', () => {
    beforeEach(() => {
        handlers.clear();
        toast.success.mockClear();
        toast.error.mockClear();
        initializeFlashToast();
    });

    it('shows the toast the server flashed', () => {
        flash({ toast: { type: 'success', message: 'Perfil actualizado.' } });
        flash({ toast: { type: 'error', message: 'Algo salió mal.' } });

        expect(toast.success).toHaveBeenCalledWith('Perfil actualizado.');
        expect(toast.error).toHaveBeenCalledWith('Algo salió mal.');
    });

    it('ignores flash data without a toast', () => {
        flash({ token: 'secret' });
        flash(undefined);

        expect(toast.success).not.toHaveBeenCalled();
        expect(toast.error).not.toHaveBeenCalled();
    });
});
